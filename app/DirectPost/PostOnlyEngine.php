<?php
declare(strict_types=1);

namespace LeadFlow\DirectPost;

use LeadFlow\Repositories\PingRepository;

/**
 * Post-only buyers (no ping): Round Sky / LeadHorizon format.
 * Walks each buyer's minimum_price waterfall until APPROVED. Attempts are stored in post_transactions.
 */
final class PostOnlyEngine
{
    public const DEFAULT_TIERS = [60, 45, 32, 22, 12, 8, 4.5, 3.5, 2.5, 1];
    public const DEFAULT_FILTERS = [
        'account_types' => ['checking', 'checkings'],
        'exclude_military' => true,
        'excluded_states' => ['NY'],
        'min_age' => 20,
        'max_age' => 80,
        'min_income' => 1200,
        'max_income' => 10000,
        'work_phone_not_home_phone' => true,
    ];

    public function __construct(private RoundSkyClient $client, private PingRepository $pingRepo) {}

    public static function config(array $buyer): array
    {
        $c = $buyer['post_config'] ?? [];
        return [
            'test_mode' => (int)($c['test_mode'] ?? 1),
            'test_url' => (string)($c['test_url'] ?? 'https://www.leadhorizon.com/leads/payday/test.php'),
            'sub_id' => (string)($c['sub_id'] ?? ''),
            'domain' => (string)($c['domain'] ?? ''),
            'time_allowed' => max(20, (int)($c['time_allowed'] ?? 20)),
            'total_budget_s' => max(20, (int)($c['total_budget_s'] ?? 45)),
            'price_tiers' => !empty($c['price_tiers']) ? array_map('floatval', $c['price_tiers']) : self::DEFAULT_TIERS,
            'filters' => array_merge(self::DEFAULT_FILTERS, $c['filters'] ?? []),
            'value_map' => is_array($c['value_map'] ?? null) ? $c['value_map'] : [],
        ];
    }

    /**
     * @param array $buyers post-only buyers, already eligibility-checked, in order
     * @return array{success:bool, buyer:?array, payout:?float, transaction_id:?string, redirect_url:?string, attempts:array}
     */
    public function process(array $lead, array $buyers, array $raw, string $ip, ?string $ua): array
    {
        $attempts = [];
        foreach ($buyers as $buyer) {
            $cfg = self::config($buyer);
            $mapped = RoundSkyClient::applyMapping($lead, $raw, $buyer['field_map'] ?? [], $cfg['value_map']);
            $applicant = $this->client->applicant($lead, $mapped);
            if ($reason = $this->client->filterReason($applicant, $cfg['filters'])) {
                $attempts[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'filter: ' . $reason];
                continue;
            }
            if ($missing = $this->client->missing($applicant)) {
                $attempts[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'missing: ' . implode(',', $missing)];
                continue;
            }
            $r = $this->walkTiers($buyer, $cfg, $applicant, $lead, $ip, $ua);
            $attempts = array_merge($attempts, $r['attempts']);
            if ($r['approved']) {
                return [
                    'success' => true, 'buyer' => $buyer,
                    'payout' => (float)$r['approved']['price'],
                    'transaction_id' => $r['approved']['buyer_lead_id'],
                    'redirect_url' => $r['approved']['redirect_url'],
                    'attempts' => $attempts,
                ];
            }
        }
        return ['success' => false, 'buyer' => null, 'payout' => null, 'transaction_id' => null, 'redirect_url' => null, 'attempts' => $attempts];
    }

    /** Admin integration test: Round Sky sample applicant to the TEST url, nothing stored. */
    public function runTest(array $buyer, string $kind): array
    {
        $cfg = self::config($buyer);
        $payload = $this->payload($buyer, $cfg, self::sampleApplicant($kind), $cfg['price_tiers'][0], '80.135.98.61', 'Mozilla/5.0 (LeadFlow integration test)');
        return $this->client->send($cfg['test_url'], $payload, (float)$cfg['time_allowed'] + 5.0);
    }

    private function walkTiers(array $buyer, array $cfg, array $applicant, array $lead, string $ip, ?string $ua): array
    {
        $url = $cfg['test_mode'] ? $cfg['test_url'] : (string)$buyer['post_url'];
        $deadline = microtime(true) + $cfg['total_budget_s'];
        $attempts = [];
        foreach ($cfg['price_tiers'] as $minPrice) {
            $remaining = $deadline - microtime(true);
            if ($remaining < 8) {
                $attempts[] = ['buyer_id' => (int)$buyer['id'], 'skipped' => 'time_budget_exhausted'];
                break;
            }
            $payload = $this->payload($buyer, $cfg, $applicant, (float)$minPrice, $ip, $ua);
            $r = $this->client->send($url, $payload, min($cfg['time_allowed'] + 5.0, $remaining));
            $approved = $r['decision'] === 'APPROVED';

            $this->pingRepo->recordPost(
                (int)$lead['id'],
                (int)$buyer['id'],
                $approved,
                $r['buyer_lead_id'],
                $approved ? $r['price'] : null,
                $r['rt_ms'],
                json_encode(RoundSkyClient::mask($payload)),
                $r['response_body'],
                $approved ? null : trim(($r['decision'] ?? 'ERROR') . ' @ $' . number_format((float)$minPrice, 2) . ': ' . ($r['message'] ?? $r['error'] ?? '')),
                hash('sha256', $lead['lead_id'] . ':' . $buyer['id'] . ':' . $minPrice)
            );
            $attempt = [
                'buyer_id' => (int)$buyer['id'], 'minimum_price' => (float)$minPrice, 'decision' => $r['decision'],
                'price' => $r['price'], 'message' => $r['message'], 'buyer_lead_id' => $r['buyer_lead_id'],
                'redirect_url' => $r['redirect_url'], 'error' => $r['error'], 'rt_ms' => $r['rt_ms'],
            ];
            $attempts[] = $attempt;
            if ($approved) return ['approved' => $attempt, 'attempts' => $attempts];
            if ($r['error'] !== null || $this->client->isFinalDecline($r['message'])) break;
        }
        return ['approved' => null, 'attempts' => $attempts];
    }

    private function payload(array $buyer, array $cfg, array $applicant, float $minPrice, string $ip, ?string $ua): array
    {
        $creds = $buyer['post_credentials'] ?? [];
        return array_merge([
            'partner' => $creds['partner'] ?? '',
            'partner_password' => $creds['partner_password'] ?? '',
            'customer_ip' => trim(explode(',', $ip)[0]),
            'minimum_price' => number_format($minPrice, 2, '.', ''),
            'sub_id' => $cfg['sub_id'],
            'domain' => preg_replace('#^https?://#', '', rtrim($cfg['domain'], '/')),
            'time_allowed' => $cfg['time_allowed'],
            'response_type' => 'json',
            'browser_info' => substr((string)$ua, 0, 250),
        ], $applicant);
    }

    public static function sampleApplicant(string $kind): array
    {
        return [
            'state' => 'CA', 'first_name' => $kind === 'approved' ? 'approved' : 'declined', 'last_name' => 'Kirk',
            'email' => 'joe_example@yahoo.com', 'home_phone' => '3107294518', 'zip' => '90046', 'address' => '123 main st.',
            'city' => 'Los Angeles', 'housing' => 'rent', 'monthly_income' => 3200, 'account_type' => 'checking',
            'direct_deposit' => 'true', 'pay_period' => 'weekly', 'next_pay_date' => date('Y-m-d', strtotime('+3 days')),
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
