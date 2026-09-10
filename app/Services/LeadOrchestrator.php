<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Repositories\LeadRepository;
use LeadFlow\PingEngine\PingEngine;
use LeadFlow\PingEngine\AuctionEngine;
use LeadFlow\PostEngine\PostEngine;
use LeadFlow\Core\Logger;
use LeadFlow\Core\Application;

final class LeadOrchestrator
{
    public function __construct(
        private LeadRepository $leadRepo,
        private BuyerEligibilityService $eligibility,
        private PingEngine $ping,
        private AuctionEngine $auction,
        private PostEngine $post,
        private Logger $log,
    ) {}

    /**
     * Run the full lifecycle synchronously.
     * $pingTree: array with buyers list, routing_mode, min_bid, allow_fallback, max_wait_ms
     */
    public function process(array $lead, ?array $pingTree = null): array
    {
        $db = Application::instance()->container()->get(\LeadFlow\Core\Database::class);

        $this->leadRepo->updateStatus((int)$lead['id'], $lead['status'], 'VALIDATING');
        $this->leadRepo->updateStatus((int)$lead['id'], 'VALIDATING', 'VALID');

        $buyerIds = [];
        $routing = 'highest_bid';
        $minBid = 0.0;
        $allowFallback = true;
        $maxWaitMs = 5000;
        if ($pingTree) {
            $buyerIds = json_decode($pingTree['buyer_ids_json'] ?? '[]', true) ?: [];
            $routing = $pingTree['routing_mode'] ?? 'highest_bid';
            $minBid = (float)($pingTree['min_bid'] ?? 0.0);
            $allowFallback = (bool)($pingTree['allow_fallback'] ?? 1);
            $maxWaitMs = (int)($pingTree['max_wait_ms'] ?? 5000);
        }

        $eligible = $this->eligibility->eligibleBuyers($lead, $buyerIds);
        if (empty($eligible)) {
            $this->leadRepo->updateStatus((int)$lead['id'], 'VALID', 'REJECTED', 'No eligible buyers');
            return ['status' => 'no_eligible_buyers', 'lead_id' => $lead['lead_id']];
        }

        $this->leadRepo->updateStatus((int)$lead['id'], 'VALID', 'PINGING', 'Pinging ' . count($eligible) . ' buyers');
        $pingResults = $this->ping->pingAll($lead, $eligible, $maxWaitMs);
        $this->leadRepo->updateStatus((int)$lead['id'], 'PINGING', 'BIDS_RECEIVED');

        $ordered = $this->auction->selectOrder($pingResults, $routing, $minBid);
        if (empty($ordered)) {
            $this->leadRepo->updateStatus((int)$lead['id'], 'BIDS_RECEIVED', 'REJECTED', 'No qualifying bids');
            return ['status' => 'no_bids', 'lead_id' => $lead['lead_id'], 'pings' => $pingResults];
        }
        $this->leadRepo->updateStatus((int)$lead['id'], 'BIDS_RECEIVED', 'WINNER_SELECTED', 'Winner: buyer ' . $ordered[0]['buyer']['id']);
        $this->leadRepo->updateStatus((int)$lead['id'], 'WINNER_SELECTED', 'POSTING');

        $postResult = $this->post->post($lead, $ordered, $allowFallback);

        if ($postResult['success']) {
            $this->leadRepo->setWinner((int)$lead['id'], (int)$postResult['buyer']['id'], (float)$postResult['payout'], $postResult['transaction_id']);
            $this->leadRepo->updateStatus((int)$lead['id'], 'POSTING', 'SOLD', 'Sold for $' . number_format((float)$postResult['payout'], 2));
            return [
                'status' => 'sold',
                'lead_id' => $lead['lead_id'],
                'winner_buyer_id' => (int)$postResult['buyer']['id'],
                'winner_buyer_name' => $postResult['buyer']['name'],
                'revenue' => $postResult['payout'],
                'transaction_id' => $postResult['transaction_id'],
                'pings' => array_map(fn($r) => ['buyer_id' => $r['buyer']['id'], 'status' => $r['status'], 'bid' => $r['bid'], 'rt_ms' => $r['rt_ms']], $pingResults),
                'post_attempts' => $postResult['attempts'],
            ];
        }

        $this->leadRepo->updateStatus((int)$lead['id'], 'POSTING', 'FAILED', 'All POST attempts failed');
        return [
            'status' => 'post_failed',
            'lead_id' => $lead['lead_id'],
            'pings' => array_map(fn($r) => ['buyer_id' => $r['buyer']['id'], 'status' => $r['status'], 'bid' => $r['bid'], 'rt_ms' => $r['rt_ms']], $pingResults),
            'post_attempts' => $postResult['attempts'],
        ];
    }
}
