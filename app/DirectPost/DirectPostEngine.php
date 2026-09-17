<?php
declare(strict_types=1);

namespace LeadFlow\DirectPost;

use LeadFlow\Core\Database;
use LeadFlow\Repositories\LeadRepository;

/**
 * Posts a lead to active Direct Post buyers in priority order, walking each buyer's
 * minimum_price waterfall until APPROVED.
 */
final class DirectPostEngine
{
    public function __construct(
        private DirectPostBuyerRepository $buyers,
        private RoundSkyClient $client,
        private LeadRepository $leads,
        private Database $db,
    ) {}

    public function hasActiveBuyers(): bool
    {
        return !empty($this->buyers->activeOrdered());
    }

    /**
     * @return array{status:string, buyer_id?:int, buyer_name?:string, price?:float, redirect_url?:string, attempts:array}
     */
    public function process(array $lead, array $raw, string $ip, ?string $ua): array
    {
        $attemptsOut = [];
        foreach ($this->buyers->activeOrdered() as $buyer) {
            if (!empty($buyer['daily_cap']) && $this->buyers->approvedToday((int)$buyer['id']) >= (int)$buyer['daily_cap']) {
                $attemptsOut[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'daily_cap'];
                continue;
            }
            $applicant = $this->client->applicant($lead, $raw);
            if ($reason = $this->client->filterReason($applicant, $buyer['filters'])) {
                $attemptsOut[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'filter: ' . $reason];
                continue;
            }
            if ($missing = $this->client->missing($applicant)) {
                $attemptsOut[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'missing: ' . implode(',', $missing)];
                continue;
            }

            $result = $this->walkTiers($buyer, $applicant, (int)$lead['id'], $ip, $ua, false);
            $attemptsOut = array_merge($attemptsOut, $result['attempts']);
            if ($result['approved']) {
                $this->markSold($lead, $buyer, $result['approved']);
                return [
                    'status' => 'sold',
                    'buyer_id' => (int)$buyer['id'],
                    'buyer_name' => $buyer['name'],
                    'price' => (float)$result['approved']['price'],
                    'buyer_lead_id' => $result['approved']['buyer_lead_id'],
                    'redirect_url' => $result['approved']['redirect_url'],
                    'attempts' => $attemptsOut,
                ];
            }
        }
        return ['status' => 'unsold', 'attempts' => $attemptsOut];
    }

    /** Admin test: sends Round Sky's sample applicant with first_name approved/declined to the TEST url. */
    public function runTest(array $buyer, string $kind): array
    {
        $applicant = self::sampleApplicant($kind === 'approved' ? 'approved' : 'declined');
        $buyer['test_mode'] = 1;
        $tiers = $buyer['price_tiers'] ?: DirectPostBuyerRepository::DEFAULT_TIERS;
        $buyer['price_tiers'] = [reset($tiers)];
        $r = $this->walkTiers($buyer, $applicant, null, '80.135.98.61', 'Mozilla/5.0 (LeadFlow integration test)', true);
        return end($r['attempts']) ?: [];
    }

    private function walkTiers(array $buyer, array $applicant, ?int $leadRowId, string $ip, ?string $ua, bool $isTest): array
    {
        $creds = $buyer['credentials'];
        $url = (int)$buyer['test_mode'] ? $buyer['test_url'] : $buyer['live_url'];
        $deadline = microtime(true) + (int)$buyer['total_budget_s'];
        $attempts = [];
        foreach ($buyer['price_tiers'] as $minPrice) {
            $remaining = $deadline - microtime(true);
            if ($remaining < 8) {
                $attempts[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'time_budget_exhausted'];
                break;
            }
            $payload = array_merge([
                'partner' => $creds['partner'] ?? '',
                'partner_password' => $creds['partner_password'] ?? '',
                'customer_ip' => trim(explode(',', $ip)[0]),
                'minimum_price' => number_format((float)$minPrice, 2, '.', ''),
                'sub_id' => $buyer['sub_id'],
                'domain' => preg_replace('#^https?://#', '', rtrim($buyer['domain'], '/')),
                'time_allowed' => (int)$buyer['time_allowed'],
                'response_type' => 'json',
                'browser_info' => substr((string)$ua, 0, 250),
            ], $applicant);

            $timeout = min((float)$buyer['time_allowed'] + 5.0, $remaining);
            $r = $this->client->send($url, $payload, $timeout);

            $this->buyers->recordAttempt([
                'lead_id' => $leadRowId,
                'buyer_id' => (int)$buyer['id'],
                'is_test' => ($isTest || (int)$buyer['test_mode']) ? 1 : 0,
                'minimum_price' => (float)$minPrice,
                'decision' => $r['decision'],
                'buyer_lead_id' => $r['buyer_lead_id'],
                'price' => $r['price'],
                'message' => $r['message'],
                'redirect_url' => $r['redirect_url'],
                'response_time_ms' => $r['rt_ms'],
                'request_masked' => json_encode(RoundSkyClient::mask($payload)),
                'response_body' => $r['response_body'],
                'error_message' => $r['error'],
            ]);
            $attempt = [
                'buyer_id' => (int)$buyer['id'], 'minimum_price' => (float)$minPrice, 'decision' => $r['decision'],
                'price' => $r['price'], 'message' => $r['message'], 'buyer_lead_id' => $r['buyer_lead_id'],
                'redirect_url' => $r['redirect_url'], 'error' => $r['error'], 'rt_ms' => $r['rt_ms'],
                'response_body' => $r['response_body'],
            ];
            $attempts[] = $attempt;

            if ($r['decision'] === 'APPROVED') {
                return ['approved' => $attempt, 'attempts' => $attempts];
            }
            if ($r['error'] !== null || $this->client->isFinalDecline($r['message'])) break;
        }
        return ['approved' => null, 'attempts' => $attempts];
    }

    private function markSold(array $lead, array $buyer, array $approved): void
    {
        $current = $this->leads->findById((int)$lead['id']);
        $this->db->query(
            'UPDATE leads SET revenue = :r, buyer_transaction_id = :t, sold_at = NOW(), updated_at = NOW() WHERE id = :id',
            ['r' => (float)$approved['price'], 't' => $approved['buyer_lead_id'], 'id' => (int)$lead['id']]
        );
        $this->leads->updateStatus(
            (int)$lead['id'],
            (string)($current['status'] ?? 'VALID'),
            'SOLD',
            'Direct post sold to ' . $buyer['name'] . ' for $' . number_format((float)$approved['price'], 2)
        );
    }

    public static function sampleApplicant(string $firstName): array
    {
        return [
            'state' => 'CA', 'first_name' => $firstName, 'last_name' => 'Kirk', 'email' => 'joe_example@yahoo.com',
            'home_phone' => '3107294518', 'zip' => '90046', 'address' => '123 main st.', 'city' => 'Los Angeles',
            'housing' => 'rent', 'monthly_income' => 3200, 'account_type' => 'checking', 'direct_deposit' => 'true',
            'pay_period' => 'weekly', 'next_pay_date' => date('Y-m-d', strtotime('+3 days')),
            'second_pay_date' => date('Y-m-d', strtotime('+10 days')), 'requested_loan_amount' => 500,
            'months_at_residence' => 60, 'income_type' => 'employment', 'active_military' => 'false',
            'occupation' => 'Cashier', 'employer' => 'LACMA Museum', 'work_phone' => '3106428873',
            'months_employed' => 24, 'bank_name' => 'Bank of America', 'account_number' => '987654321321',
            'routing_number' => '031310206', 'months_with_bank' => 60, 'driving_license_state' => 'CA',
            'driving_license_number' => 'r98465432', 'birth_date' => '1985-06-11', 'social_security_number' => '031310206',
            'high_debt' => 'false', 'creditScore' => 660, 'has_clean_title' => 'false',
        ];
    }
}
