<?php
declare(strict_types=1);

namespace LeadFlow\Repositories;

use LeadFlow\Core\Database;

final class OfferRepository
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->all(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM offer_clicks c WHERE c.offer_id = o.id) AS clicks,
                    (SELECT COUNT(*) FROM offer_clicks c WHERE c.offer_id = o.id AND c.converted = 1) AS conversions,
                    (SELECT COALESCE(SUM(c.payout),0) FROM offer_clicks c WHERE c.offer_id = o.id AND c.converted = 1) AS revenue
             FROM offers o ORDER BY o.id DESC'
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM offers WHERE id = :id', ['id' => $id]);
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['postback_key'] = bin2hex(random_bytes(16));
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        return (int)$this->db->insert('offers', $data);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('offers', $data, 'id = :id', ['id' => $id]);
    }

    public function toggleActive(int $id): void
    {
        $this->db->query('UPDATE offers SET active = 1 - active, updated_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function createClick(int $offerId, ?int $leadId, ?string $subId, string $ip, ?string $ua): string
    {
        $clickId = bin2hex(random_bytes(12));
        $this->db->insert('offer_clicks', [
            'click_id' => $clickId,
            'offer_id' => $offerId,
            'lead_id' => $leadId,
            'sub_id' => $subId,
            'ip_address' => substr($ip, 0, 64),
            'user_agent' => $ua !== null ? substr($ua, 0, 500) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $clickId;
    }

    public function findClick(string $clickId): ?array
    {
        return $this->db->one('SELECT * FROM offer_clicks WHERE click_id = :c', ['c' => $clickId]);
    }

    public function markConverted(int $clickRowId, float $payout, ?string $txid): void
    {
        $this->db->query(
            'UPDATE offer_clicks SET converted = 1, payout = :p, conversion_txid = :t, converted_at = NOW() WHERE id = :id',
            ['p' => $payout, 't' => $txid, 'id' => $clickRowId]
        );
    }

    public function logPostback(?int $offerId, ?string $clickId, string $result, string $query, string $ip): void
    {
        $this->db->insert('offer_postbacks', [
            'offer_id' => $offerId,
            'click_id' => $clickId !== null ? substr($clickId, 0, 40) : null,
            'result' => $result,
            'query_string' => substr($query, 0, 2000),
            'ip_address' => substr($ip, 0, 64),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recentClicks(int $offerId, int $limit = 50): array
    {
        return $this->db->all(
            "SELECT c.*, l.lead_id AS lead_ref FROM offer_clicks c LEFT JOIN leads l ON l.id = c.lead_id
             WHERE c.offer_id = :o ORDER BY c.id DESC LIMIT $limit",
            ['o' => $offerId]
        );
    }

    public function recentPostbacks(int $offerId, int $limit = 20): array
    {
        return $this->db->all(
            "SELECT * FROM offer_postbacks WHERE offer_id = :o ORDER BY id DESC LIMIT $limit",
            ['o' => $offerId]
        );
    }

    public function clicksForLead(int $leadId): array
    {
        return $this->db->all(
            'SELECT c.*, o.name AS offer_name, o.delivery_mode FROM offer_clicks c JOIN offers o ON o.id = c.offer_id
             WHERE c.lead_id = :l ORDER BY c.id ASC',
            ['l' => $leadId]
        );
    }
}
