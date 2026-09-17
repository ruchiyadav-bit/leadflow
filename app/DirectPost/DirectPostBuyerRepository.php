<?php
declare(strict_types=1);

namespace LeadFlow\DirectPost;

use LeadFlow\Core\Database;
use LeadFlow\Support\Crypto;

final class DirectPostBuyerRepository
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

    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->all(
            "SELECT b.*,
               (SELECT COUNT(DISTINCT lead_id) FROM direct_post_attempts a WHERE a.buyer_id = b.id AND a.is_test = 0) AS leads_posted,
               (SELECT COUNT(*) FROM direct_post_attempts a WHERE a.buyer_id = b.id AND a.is_test = 0 AND a.decision = 'APPROVED') AS approved,
               (SELECT COALESCE(SUM(price),0) FROM direct_post_attempts a WHERE a.buyer_id = b.id AND a.is_test = 0 AND a.decision = 'APPROVED') AS revenue
             FROM direct_post_buyers b ORDER BY b.priority ASC, b.id ASC"
        );
    }

    public function activeOrdered(): array
    {
        $rows = $this->db->all('SELECT * FROM direct_post_buyers WHERE active = 1 ORDER BY priority ASC, id ASC');
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function findById(int $id): ?array
    {
        $r = $this->db->one('SELECT * FROM direct_post_buyers WHERE id = :id', ['id' => $id]);
        return $r ? $this->hydrate($r) : null;
    }

    private function hydrate(array $r): array
    {
        $r['price_tiers'] = json_decode($r['price_tiers_json'] ?? '[]', true) ?: [];
        $r['filters'] = array_merge(self::DEFAULT_FILTERS, json_decode($r['filters_json'] ?? '{}', true) ?: []);
        $creds = Crypto::decrypt($r['credentials_enc'] ?? null);
        $r['credentials'] = $creds ? (json_decode($creds, true) ?: []) : [];
        unset($r['credentials_enc']);
        return $r;
    }

    public function save(?int $id, array $data, ?array $credentials): int
    {
        $row = [
            'name' => $data['name'],
            'provider' => 'roundsky',
            'active' => (int)$data['active'],
            'test_mode' => (int)$data['test_mode'],
            'test_url' => $data['test_url'],
            'live_url' => $data['live_url'],
            'sub_id' => $data['sub_id'],
            'domain' => $data['domain'],
            'time_allowed' => max(20, (int)$data['time_allowed']),
            'total_budget_s' => max(20, (int)$data['total_budget_s']),
            'price_tiers_json' => json_encode(array_values($data['price_tiers'])),
            'filters_json' => json_encode($data['filters']),
            'priority' => (int)$data['priority'],
            'daily_cap' => $data['daily_cap'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($credentials !== null) {
            $row['credentials_enc'] = Crypto::encrypt(json_encode($credentials));
        }
        if ($id) {
            $this->db->update('direct_post_buyers', $row, 'id = :id', ['id' => $id]);
            return $id;
        }
        $row['created_at'] = $row['updated_at'];
        return (int)$this->db->insert('direct_post_buyers', $row);
    }

    public function toggleActive(int $id): void
    {
        $this->db->query('UPDATE direct_post_buyers SET active = 1 - active, updated_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function approvedToday(int $buyerId): int
    {
        return (int)($this->db->one(
            "SELECT COUNT(*) c FROM direct_post_attempts WHERE buyer_id = :b AND is_test = 0 AND decision = 'APPROVED' AND DATE(created_at) = CURDATE()",
            ['b' => $buyerId]
        )['c'] ?? 0);
    }

    public function recordAttempt(array $a): int
    {
        $a['created_at'] = date('Y-m-d H:i:s');
        return (int)$this->db->insert('direct_post_attempts', $a);
    }

    public function attemptsForBuyer(int $buyerId, int $limit = 50): array
    {
        return $this->db->all(
            "SELECT a.*, l.lead_id AS lead_ref FROM direct_post_attempts a LEFT JOIN leads l ON l.id = a.lead_id
             WHERE a.buyer_id = :b ORDER BY a.id DESC LIMIT $limit",
            ['b' => $buyerId]
        );
    }

    public function attemptsForLead(int $leadId): array
    {
        return $this->db->all(
            'SELECT a.*, b.name AS buyer_name FROM direct_post_attempts a JOIN direct_post_buyers b ON b.id = a.buyer_id
             WHERE a.lead_id = :l ORDER BY a.id ASC',
            ['l' => $leadId]
        );
    }
}
