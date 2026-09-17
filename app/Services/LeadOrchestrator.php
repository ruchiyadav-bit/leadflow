<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Repositories\LeadRepository;
use LeadFlow\Repositories\OfferRepository;
use LeadFlow\PingEngine\PingEngine;
use LeadFlow\PingEngine\AuctionEngine;
use LeadFlow\PostEngine\PostEngine;
use LeadFlow\DirectPost\PostOnlyEngine;
use LeadFlow\Core\Logger;

final class LeadOrchestrator
{
    public function __construct(
        private LeadRepository $leadRepo,
        private BuyerEligibilityService $eligibility,
        private PingEngine $ping,
        private AuctionEngine $auction,
        private PostEngine $post,
        private PostOnlyEngine $postOnly,
        private OfferRepository $offers,
        private Logger $log,
    ) {}

    /**
     * Run the full lifecycle synchronously.
     *  1. Ping + Post buyers (auction)
     *  2. Post Only buyers (e.g. Round Sky) if still unsold
     *  3. Ping tree offers (S2S / Affiliate Direct) as redirect if still unsold
     * $ctx: raw (intake data incl. sensitive fields, not stored), ip, user_agent, base_url
     */
    public function process(array $lead, ?array $pingTree = null, array $ctx = []): array
    {
        $leadRowId = (int)$lead['id'];
        $this->leadRepo->updateStatus($leadRowId, $lead['status'], 'VALIDATING');
        $this->leadRepo->updateStatus($leadRowId, 'VALIDATING', 'VALID');
        $status = 'VALID';

        $buyerIds = [];
        $offerIds = [];
        $routing = 'highest_bid';
        $minBid = 0.0;
        $allowFallback = true;
        $maxWaitMs = 5000;
        if ($pingTree) {
            $buyerIds = json_decode($pingTree['buyer_ids_json'] ?? '[]', true) ?: [];
            $offerIds = json_decode($pingTree['offer_ids_json'] ?? '[]', true) ?: [];
            $routing = $pingTree['routing_mode'] ?? 'highest_bid';
            $minBid = (float)($pingTree['min_bid'] ?? 0.0);
            $allowFallback = (bool)($pingTree['allow_fallback'] ?? 1);
            $maxWaitMs = (int)($pingTree['max_wait_ms'] ?? 5000);
        }

        $eligible = $this->eligibility->eligibleBuyers($lead, $buyerIds);
        $pingBuyers = array_values(array_filter($eligible, fn($b) => ($b['integration_type'] ?? 'ping_post') !== 'post_only'));
        $postOnlyBuyers = array_values(array_filter($eligible, fn($b) => ($b['integration_type'] ?? 'ping_post') === 'post_only'));
        usort($postOnlyBuyers, fn($a, $b) => (int)$a['priority'] <=> (int)$b['priority']);

        $result = ['status' => 'no_eligible_buyers', 'lead_id' => $lead['lead_id']];

        // 1. Ping + Post
        if ($pingBuyers) {
            $status = $this->move($leadRowId, $status, 'PINGING', 'Pinging ' . count($pingBuyers) . ' buyers');
            $pingResults = $this->ping->pingAll($lead, $pingBuyers, $maxWaitMs);
            $status = $this->move($leadRowId, $status, 'BIDS_RECEIVED');
            $pingSummary = array_map(fn($r) => ['buyer_id' => $r['buyer']['id'], 'status' => $r['status'], 'bid' => $r['bid'], 'rt_ms' => $r['rt_ms']], $pingResults);

            $ordered = $this->auction->selectOrder($pingResults, $routing, $minBid);
            if (empty($ordered)) {
                $status = $this->move($leadRowId, $status, 'REJECTED', 'No qualifying bids');
                $result = ['status' => 'no_bids', 'lead_id' => $lead['lead_id'], 'pings' => $pingResults];
            } else {
                $status = $this->move($leadRowId, $status, 'WINNER_SELECTED', 'Winner: buyer ' . $ordered[0]['buyer']['id']);
                $status = $this->move($leadRowId, $status, 'POSTING');
                $postResult = $this->post->post($lead, $ordered, $allowFallback);

                if ($postResult['success']) {
                    $this->leadRepo->setWinner($leadRowId, (int)$postResult['buyer']['id'], (float)$postResult['payout'], $postResult['transaction_id']);
                    $this->move($leadRowId, $status, 'SOLD', 'Sold for $' . number_format((float)$postResult['payout'], 2));
                    return [
                        'status' => 'sold',
                        'lead_id' => $lead['lead_id'],
                        'winner_buyer_id' => (int)$postResult['buyer']['id'],
                        'winner_buyer_name' => $postResult['buyer']['name'],
                        'revenue' => $postResult['payout'],
                        'transaction_id' => $postResult['transaction_id'],
                        'pings' => $pingSummary,
                        'post_attempts' => $postResult['attempts'],
                    ];
                }
                $status = $this->move($leadRowId, $status, 'FAILED', 'All POST attempts failed');
                $result = ['status' => 'post_failed', 'lead_id' => $lead['lead_id'], 'pings' => $pingSummary, 'post_attempts' => $postResult['attempts']];
            }
        }

        // 2. Post Only buyers
        if ($postOnlyBuyers) {
            $status = $this->move($leadRowId, $status, 'POSTING', 'Posting to ' . count($postOnlyBuyers) . ' post-only buyers');
            $po = $this->postOnly->process($lead, $postOnlyBuyers, $ctx['raw'] ?? [], (string)($ctx['ip'] ?? ''), $ctx['user_agent'] ?? null);
            if ($po['success']) {
                $this->leadRepo->setWinner($leadRowId, (int)$po['buyer']['id'], (float)$po['payout'], $po['transaction_id']);
                $this->move($leadRowId, $status, 'SOLD', 'Sold to ' . $po['buyer']['name'] . ' for $' . number_format((float)$po['payout'], 2));
                return [
                    'status' => 'sold',
                    'lead_id' => $lead['lead_id'],
                    'winner_buyer_id' => (int)$po['buyer']['id'],
                    'winner_buyer_name' => $po['buyer']['name'],
                    'revenue' => $po['payout'],
                    'transaction_id' => $po['transaction_id'],
                    'redirect_url' => $po['redirect_url'],
                    'post_attempts' => $po['attempts'],
                ];
            }
            $status = $this->move($leadRowId, $status, 'REJECTED', 'Post-only buyers did not accept');
            $result = ['status' => 'post_only_unsold', 'lead_id' => $lead['lead_id'], 'post_attempts' => $po['attempts']];
        }

        // 3. Ping tree offers (affiliate link buyers)
        foreach ($offerIds as $offerId) {
            $offer = $this->offers->findById((int)$offerId);
            if (!$offer || !(int)$offer['active']) continue;
            $url = rtrim((string)($ctx['base_url'] ?? ''), '/') . '/go/' . (int)$offer['id'] . '?'
                . http_build_query(['lead_id' => $lead['lead_id'], 'sub_id' => $lead['sub_id'] ?? null]);
            if ($status === 'VALID') $status = $this->move($leadRowId, $status, 'REJECTED', 'No eligible buyers');
            $this->leadRepo->addNote($leadRowId, $status, 'Redirected to offer: ' . $offer['name']);
            return array_merge($result, [
                'status' => 'offer_redirect',
                'offer_id' => (int)$offer['id'],
                'offer_name' => $offer['name'],
                'redirect_url' => $url,
            ]);
        }

        if ($status === 'VALID') {
            $this->move($leadRowId, $status, 'REJECTED', 'No eligible buyers');
        }
        return $result;
    }

    private function move(int $leadRowId, string $from, string $to, ?string $note = null): string
    {
        $this->leadRepo->updateStatus($leadRowId, $from, $to, $note);
        return $to;
    }
}
