<?php
declare(strict_types=1);

namespace LeadFlow\Repositories;

use LeadFlow\Core\Database;

final class BuyerRepository
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->all('SELECT * FROM buyers ORDER BY name ASC');
    }

    public function findById(int $id): ?array
    {
        $b = $this->db->one('SELECT * FROM buyers WHERE id = :id', ['id' => $id]);
        if (!$b) return null;
        $b['credentials'] = json_decode($b['credentials_json'] ?? '{}', true) ?: [];
        $b['headers']     = json_decode($b['headers_json'] ?? '{}', true) ?: [];
        $b['field_map']   = json_decode($b['field_map_json'] ?? '{}', true) ?: [];
        $b['transformations'] = json_decode($b['transformations_json'] ?? '[]', true) ?: [];
        $b['response_rules']  = json_decode($b['response_rules_json'] ?? '{}', true) ?: [];
        return $b;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('buyers', [
            'name' => $data['name'],
            'active' => (int)($data['active'] ?? 1),
            'ping_url' => $data['ping_url'],
            'post_url' => $data['post_url'],
            'ping_method' => $data['ping_method'] ?? 'POST',
            'post_method' => $data['post_method'] ?? 'POST',
            'request_format' => $data['request_format'] ?? 'json',
            'timeout_ms' => (int)($data['timeout_ms'] ?? 3000),
            'credentials_json' => json_encode($data['credentials'] ?? []),
            'headers_json'     => json_encode($data['headers'] ?? []),
            'field_map_json'   => json_encode($data['field_map'] ?? []),
            'transformations_json' => json_encode($data['transformations'] ?? []),
            'response_rules_json'  => json_encode($data['response_rules'] ?? []),
            'priority' => (int)($data['priority'] ?? 100),
            'weight'   => (int)($data['weight'] ?? 1),
            'daily_cap'   => $data['daily_cap'] ?? null,
            'hourly_cap'  => $data['hourly_cap'] ?? null,
            'monthly_cap' => $data['monthly_cap'] ?? null,
            'total_cap'   => $data['total_cap'] ?? null,
            'schedule_json' => json_encode($data['schedule'] ?? []),
            'rules_json'    => json_encode($data['rules'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $update = [];
        $allowed = ['name','active','ping_url','post_url','ping_method','post_method','request_format','timeout_ms','priority','weight','daily_cap','hourly_cap','monthly_cap','total_cap'];
        foreach ($allowed as $k) if (array_key_exists($k, $data)) $update[$k] = $data[$k];
        $jsonFields = ['credentials'=>'credentials_json','headers'=>'headers_json','field_map'=>'field_map_json','transformations'=>'transformations_json','response_rules'=>'response_rules_json','schedule'=>'schedule_json','rules'=>'rules_json'];
        foreach ($jsonFields as $k => $col) if (array_key_exists($k, $data)) $update[$col] = json_encode($data[$k]);
        $update['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('buyers', $update, 'id = :id', ['id' => $id]);
    }

    public function toggleActive(int $id): void
    {
        $this->db->query('UPDATE buyers SET active = 1 - active, updated_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function counts(int $buyerId): array
    {
        $today = $this->db->one("SELECT COUNT(*) c FROM post_transactions WHERE buyer_id = :b AND success = 1 AND DATE(created_at) = CURDATE()", ['b' => $buyerId])['c'] ?? 0;
        $hour  = $this->db->one("SELECT COUNT(*) c FROM post_transactions WHERE buyer_id = :b AND success = 1 AND created_at > NOW() - INTERVAL 1 HOUR", ['b' => $buyerId])['c'] ?? 0;
        $month = $this->db->one("SELECT COUNT(*) c FROM post_transactions WHERE buyer_id = :b AND success = 1 AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", ['b' => $buyerId])['c'] ?? 0;
        $total = $this->db->one("SELECT COUNT(*) c FROM post_transactions WHERE buyer_id = :b AND success = 1", ['b' => $buyerId])['c'] ?? 0;
        return ['today' => (int)$today, 'hour' => (int)$hour, 'month' => (int)$month, 'total' => (int)$total];
    }

    public function stats(int $buyerId): array
    {
        return $this->db->one(
            "SELECT
              (SELECT COUNT(*) FROM ping_transactions WHERE buyer_id = :b) AS pinged,
              (SELECT COUNT(*) FROM ping_transactions WHERE buyer_id = :b AND accepted = 1) AS accepted,
              (SELECT COUNT(*) FROM ping_transactions WHERE buyer_id = :b AND status = 'timeout') AS timeouts,
              (SELECT COUNT(*) FROM post_transactions WHERE buyer_id = :b AND success = 1) AS sold,
              (SELECT COALESCE(SUM(revenue),0) FROM revenue_records WHERE buyer_id = :b) AS revenue,
              (SELECT AVG(response_time_ms) FROM ping_transactions WHERE buyer_id = :b AND response_time_ms IS NOT NULL) AS avg_response
             ",
            ['b' => $buyerId]
        ) ?? [];
    }
}
