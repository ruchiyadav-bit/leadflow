<?php
declare(strict_types=1);

namespace LeadFlow\DirectPost;

use GuzzleHttp\Client;
use LeadFlow\Support\Normalizer;

/**
 * Round Sky (LeadHorizon) payday post API: payload building, filters, request and response parsing.
 */
final class RoundSkyClient
{
    /** Fields that must never be stored in plaintext or logged unmasked. */
    public const SENSITIVE = [
        'social_security_number', 'ssn', 'account_number', 'routing_number',
        'driving_license_number', 'partner_password',
    ];

    private const OPTIONAL = [
        'city', 'second_pay_date', 'occupation', 'high_debt', 'creditScore', 'has_clean_title',
    ];

    private const REQUIRED_INPUT = [
        'first_name', 'last_name', 'email', 'home_phone', 'zip', 'address', 'state', 'housing', 'monthly_income',
        'account_type', 'direct_deposit', 'pay_period', 'next_pay_date', 'requested_loan_amount',
        'months_at_residence', 'income_type', 'active_military', 'employer', 'work_phone', 'months_employed',
        'bank_name', 'account_number', 'routing_number', 'months_with_bank', 'driving_license_state',
        'driving_license_number', 'birth_date', 'social_security_number',
    ];

    /** Build the applicant part of the payload from LeadFlow lead row + raw intake data. */
    public function applicant(array $lead, array $raw): array
    {
        $v = fn(string ...$keys) => $this->first($raw, $lead, $keys);

        $loan = Normalizer::money($v('requested_loan_amount', 'loan_amount'));
        if ($loan !== null) $loan = (int)min(50000, max(50, round($loan / 50) * 50));

        $income = Normalizer::money($v('monthly_income'));
        $out = [
            'state' => strtoupper((string)$v('state')),
            'first_name' => $v('first_name'),
            'last_name' => $v('last_name'),
            'email' => $v('email'),
            'home_phone' => Normalizer::phone((string)$v('home_phone', 'phone')),
            'zip' => substr((string)$v('zip'), 0, 5),
            'address' => $v('address'),
            'city' => $v('city'),
            'housing' => strtolower((string)$v('housing')),
            'monthly_income' => $income !== null ? (int)round($income) : null,
            'account_type' => strtolower((string)$v('account_type')),
            'direct_deposit' => $this->boolStr($v('direct_deposit')),
            'pay_period' => $this->payPeriod((string)$v('pay_period', 'pay_frequency')),
            'next_pay_date' => Normalizer::date((string)$v('next_pay_date')),
            'second_pay_date' => Normalizer::date((string)$v('second_pay_date')),
            'requested_loan_amount' => $loan,
            'months_at_residence' => $this->months($v('months_at_residence')),
            'income_type' => strtolower((string)$v('income_type')),
            'active_military' => $this->boolStr($v('active_military')),
            'occupation' => $v('occupation'),
            'employer' => $v('employer'),
            'work_phone' => Normalizer::phone((string)$v('work_phone')),
            'months_employed' => $this->months($v('months_employed')),
            'bank_name' => $v('bank_name'),
            'account_number' => preg_replace('/\D+/', '', (string)$v('account_number')),
            'routing_number' => preg_replace('/\D+/', '', (string)$v('routing_number')),
            'months_with_bank' => $this->months($v('months_with_bank')),
            'driving_license_state' => strtoupper((string)$v('driving_license_state')),
            'driving_license_number' => $v('driving_license_number'),
            'birth_date' => Normalizer::date((string)$v('birth_date', 'date_of_birth')),
            'social_security_number' => preg_replace('/\D+/', '', (string)$v('social_security_number', 'ssn')),
            'high_debt' => $this->boolStr($v('high_debt')),
            'creditScore' => $v('creditScore', 'credit_score'),
            'has_clean_title' => $this->boolStr($v('has_clean_title')),
        ];
        foreach ($out as $k => $val) {
            if (($val === null || $val === '') && in_array($k, self::OPTIONAL, true)) unset($out[$k]);
        }
        return $out;
    }

    /** @return string[] missing required fields */
    public function missing(array $applicant): array
    {
        $missing = [];
        foreach (self::REQUIRED_INPUT as $f) {
            if (!isset($applicant[$f]) || $applicant[$f] === '' || $applicant[$f] === null) $missing[] = $f;
        }
        return $missing;
    }

    /** Buyer-side filters. Returns a rejection reason, or null if the lead passes. */
    public function filterReason(array $a, array $f): ?string
    {
        if (!empty($f['account_types']) && !in_array($a['account_type'] ?? '', array_map('strtolower', $f['account_types']), true)) {
            return 'account_type not allowed';
        }
        if (!empty($f['exclude_military']) && ($a['active_military'] ?? 'false') === 'true') return 'active military';
        if (!empty($f['excluded_states']) && in_array($a['state'] ?? '', array_map('strtoupper', $f['excluded_states']), true)) {
            return 'state excluded';
        }
        $age = Normalizer::age($a['birth_date'] ?? null);
        if ($age === null) return 'birth_date missing';
        if (isset($f['min_age']) && $age < (int)$f['min_age']) return 'age below minimum';
        if (isset($f['max_age']) && $age > (int)$f['max_age']) return 'age above maximum';
        $income = $a['monthly_income'] ?? null;
        if ($income === null) return 'monthly_income missing';
        if (isset($f['min_income']) && $income < (float)$f['min_income']) return 'income below minimum';
        if (isset($f['max_income']) && $income > (float)$f['max_income']) return 'income above maximum';
        if (!empty($f['work_phone_not_home_phone']) && ($a['work_phone'] ?? '') !== '' && ($a['work_phone'] ?? '') === ($a['home_phone'] ?? '')) {
            return 'work_phone equals home_phone';
        }
        return null;
    }

    /**
     * Send one post. Returns decision data plus raw/masked request for logging.
     */
    public function send(string $url, array $payload, float $timeoutS): array
    {
        $client = new Client(['http_errors' => false, 'connect_timeout' => 5.0, 'verify' => true]);
        $start = microtime(true);
        $result = [
            'decision' => null, 'buyer_lead_id' => null, 'price' => null, 'message' => null,
            'redirect_url' => null, 'response_body' => null, 'error' => null, 'rt_ms' => 0,
        ];
        try {
            $resp = $client->request('POST', $url, ['form_params' => $payload, 'timeout' => $timeoutS]);
            $body = (string)$resp->getBody();
            $result['response_body'] = substr($body, 0, 20000);
            if ($resp->getStatusCode() >= 400) {
                $result['error'] = 'HTTP ' . $resp->getStatusCode();
            } else {
                $result = array_merge($result, $this->parse($body));
            }
        } catch (\Throwable $e) {
            $result['error'] = substr($e->getMessage(), 0, 500);
        }
        $result['rt_ms'] = (int)((microtime(true) - $start) * 1000);
        return $result;
    }

    /** Parses JSON, XML or pipe responses. */
    public function parse(string $body): array
    {
        $body = trim($body);
        $d = null;
        if (str_starts_with($body, '{')) {
            $j = json_decode($body, true);
            if (is_array($j)) $d = array_change_key_case($j, CASE_UPPER);
        } elseif (stripos($body, '<RESPONSE') !== false) {
            libxml_use_internal_errors(true);
            $x = simplexml_load_string($body);
            if ($x !== false) $d = array_change_key_case(json_decode(json_encode($x), true) ?: [], CASE_UPPER);
        } elseif (str_contains($body, '|')) {
            $p = explode('|', $body, 5);
            $d = ['DECISION' => $p[0] ?? null, 'LEADID' => $p[1] ?? null, 'PRICE' => $p[2] ?? null, 'MESSAGE' => $p[3] ?? null, 'URL' => $p[4] ?? null];
        }
        if (!$d || empty($d['DECISION'])) {
            return ['error' => 'unparseable_response'];
        }
        $decision = strtoupper(trim((string)$d['DECISION']));
        $url = isset($d['URL']) && is_string($d['URL']) ? trim($d['URL']) : null;
        return [
            'decision' => in_array($decision, ['APPROVED', 'DECLINED'], true) ? $decision : $decision,
            'buyer_lead_id' => (isset($d['LEADID']) && is_scalar($d['LEADID']) && (string)$d['LEADID'] !== '0' && (string)$d['LEADID'] !== '') ? substr(trim((string)$d['LEADID']), 0, 64) : null,
            'price' => isset($d['PRICE']) && is_numeric($d['PRICE']) ? (float)$d['PRICE'] : null,
            'message' => isset($d['MESSAGE']) && is_string($d['MESSAGE']) ? substr(trim($d['MESSAGE']), 0, 500) : null,
            'redirect_url' => ($url && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) ? $url : null,
        ];
    }

    /** Decline reasons where reposting at a lower price will not help. */
    public function isFinalDecline(?string $message): bool
    {
        return $message !== null && (bool)preg_match('/duplicate|missing|invalid|required|format|not allowed|banned|blocked/i', $message);
    }

    public static function mask(array $payload): array
    {
        foreach ($payload as $k => $v) {
            if (in_array($k, self::SENSITIVE, true) && is_scalar($v) && (string)$v !== '') {
                $s = (string)$v;
                $payload[$k] = $k === 'partner_password' ? '***' : str_repeat('*', max(0, strlen($s) - 4)) . substr($s, -4);
            }
        }
        return $payload;
    }

    private function first(array $raw, array $lead, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (isset($raw[$k]) && $raw[$k] !== '') return is_string($raw[$k]) ? trim($raw[$k]) : $raw[$k];
            if (isset($lead[$k]) && $lead[$k] !== '') return $lead[$k];
        }
        return null;
    }

    private function boolStr(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_bool($v)) return $v ? 'true' : 'false';
        $s = strtolower(trim((string)$v));
        if (in_array($s, ['true', 'yes', 'y', '1', 'on'], true)) return 'true';
        if (in_array($s, ['false', 'no', 'n', '0', 'off'], true)) return 'false';
        return null;
    }

    private function payPeriod(string $v): ?string
    {
        $s = strtolower(str_replace(['-', ' '], '_', trim($v)));
        return match ($s) {
            'weekly' => 'weekly',
            'biweekly', 'bi_weekly', 'every_two_weeks', 'every_other_week' => 'biweekly',
            'twice_monthly', 'semi_monthly', 'semimonthly' => 'twice monthly',
            'monthly' => 'monthly',
            default => null,
        };
    }

    private function months(mixed $v): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (int)min(240, max(0, (int)$v));
    }
}
