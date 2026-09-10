<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Core\Database;

final class PingTreeService
{
    public function __construct(private Database $db) {}

    public function all(): array { return $this->db->all('SELECT * FROM ping_trees ORDER BY name'); }
    public function find(int $id): ?array { return $this->db->one('SELECT * FROM ping_trees WHERE id = :id', ['id' => $id]); }

    public function findForLead(array $lead): ?array
    {
        $sid = $lead['source_id'] ?? null; $cid = $lead['campaign_id'] ?? null;
        if ($cid) {
            $tree = $this->db->one('SELECT * FROM ping_trees WHERE active = 1 AND (campaign_id = :cid OR campaign_id IS NULL) ORDER BY campaign_id IS NULL, id LIMIT 1', ['cid' => $cid]);
            if ($tree) return $tree;
        }
        if ($sid) {
            $tree = $this->db->one('SELECT * FROM ping_trees WHERE active = 1 AND (source_id = :sid OR source_id IS NULL) ORDER BY source_id IS NULL, id LIMIT 1', ['sid' => $sid]);
            if ($tree) return $tree;
        }
        return $this->db->one('SELECT * FROM ping_trees WHERE active = 1 AND source_id IS NULL AND campaign_id IS NULL ORDER BY id LIMIT 1');
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ping_trees', [
            'name' => $data['name'], 'active' => (int)($data['active'] ?? 1),
            'routing_mode' => $data['routing_mode'] ?? 'highest_bid',
            'min_bid' => (float)($data['min_bid'] ?? 0),
            'allow_fallback' => (int)($data['allow_fallback'] ?? 1),
            'max_wait_ms' => (int)($data['max_wait_ms'] ?? 5000),
            'max_buyers' => (int)($data['max_buyers'] ?? 25),
            'source_id' => $data['source_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'buyer_ids_json' => json_encode($data['buyer_ids'] ?? []),
            'fallback_ids_json' => json_encode($data['fallback_ids'] ?? []),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $set = [];
        foreach (['name','active','routing_mode','min_bid','allow_fallback','max_wait_ms','max_buyers','source_id','campaign_id'] as $k) {
            if (array_key_exists($k, $data)) $set[$k] = $data[$k];
        }
        if (array_key_exists('buyer_ids', $data)) $set['buyer_ids_json'] = json_encode($data['buyer_ids']);
        if (array_key_exists('fallback_ids', $data)) $set['fallback_ids_json'] = json_encode($data['fallback_ids']);
        $set['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('ping_trees', $set, 'id = :id', ['id' => $id]);
    }
}
