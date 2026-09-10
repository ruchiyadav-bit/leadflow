<?php
declare(strict_types=1);

namespace LeadFlow\Repositories;

use LeadFlow\Core\Database;
use LeadFlow\Support\Uuid;

final class LeadRepository
{
    public function __construct(private Database $db) {}

    public function create(array $core, array $custom, array $consent, array $attribution): array
    {
        return $this->db->transaction(function (Database $db) use ($core, $custom, $consent, $attribution) {
            $leadId = Uuid::leadId();
            $uuid = Uuid::v4();
            $now = date('Y-m-d H:i:s');

            $db->insert('leads', [
                'lead_id' => $leadId,
                'uuid' => $uuid,
                'status' => 'NEW',
                'first_name' => $core['first_name'],
                'last_name'  => $core['last_name'],
                'email'      => $core['email'],
                'phone'      => $core['phone'],
                'address'    => $core['address'] ?? null,
                'city'       => $core['city'] ?? null,
                'state'      => $core['state'],
                'zip'        => $core['zip'],
                'date_of_birth' => $core['date_of_birth'] ?? null,
                'employment_status' => $core['employment_status'] ?? null,
                'monthly_income'    => $core['monthly_income'] ?? null,
                'pay_frequency'     => $core['pay_frequency'] ?? null,
                'loan_amount'       => $core['loan_amount'] ?? null,
                'source_id'   => $attribution['source_id'] ?? null,
                'campaign_id' => $attribution['campaign_id'] ?? null,
                'sub_id'      => $attribution['sub_id'] ?? null,
                'fb_campaign_id' => $attribution['fb_campaign_id'] ?? null,
                'fb_adset_id'    => $attribution['fb_adset_id'] ?? null,
                'fb_ad_id'       => $attribution['fb_ad_id'] ?? null,
                'utm_source'     => $attribution['utm_source'] ?? null,
                'utm_medium'     => $attribution['utm_medium'] ?? null,
                'utm_campaign'   => $attribution['utm_campaign'] ?? null,
                'utm_content'    => $attribution['utm_content'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int)$db->pdo()->lastInsertId();

            foreach ($custom as $k => $v) {
                if ($v === null || $v === '') continue;
                $db->insert('lead_custom_fields', [
                    'lead_id' => $id,
                    'field_key' => (string)$k,
                    'field_value' => is_scalar($v) ? (string)$v : json_encode($v),
                    'created_at' => $now,
                ]);
            }

            $db->insert('lead_consents', [
                'lead_id' => $id,
                'trustedform_cert' => $consent['trustedform_cert'] ?? null,
                'jornaya_lead_id'  => $consent['jornaya_lead_id'] ?? null,
                'consent_version'  => $consent['consent_version'] ?? '1.0',
                'disclosure_version' => $consent['disclosure_version'] ?? '1.0',
                'privacy_version' => $consent['privacy_version'] ?? '1.0',
                'terms_version' => $consent['terms_version'] ?? '1.0',
                'landing_url'   => $consent['landing_url'] ?? null,
                'ip_address'    => $consent['ip_address'] ?? null,
                'user_agent'    => $consent['user_agent'] ?? null,
                'consent_timestamp' => $consent['consent_timestamp'] ?? $now,
                'created_at'    => $now,
            ]);

            $this->recordStatus($db, $id, null, 'NEW', 'Lead intake accepted');

            return $this->findById($id);
        });
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM leads WHERE id = :id', ['id' => $id]);
    }

    public function findByLeadId(string $leadId): ?array
    {
        return $this->db->one('SELECT * FROM leads WHERE lead_id = :l', ['l' => $leadId]);
    }

    public function findDuplicate(string $email, string $phone, int $windowDays = 30): ?array
    {
        return $this->db->one(
            'SELECT * FROM leads
             WHERE (email = :e OR phone = :p)
               AND created_at > DATE_SUB(NOW(), INTERVAL :d DAY)
             ORDER BY id DESC LIMIT 1',
            ['e' => $email, 'p' => $phone, 'd' => $windowDays]
        );
    }

    public function updateStatus(int $leadId, string $from, string $to, ?string $note = null): void
    {
        $this->db->query('UPDATE leads SET status = :to, updated_at = NOW() WHERE id = :id', ['to' => $to, 'id' => $leadId]);
        $this->recordStatus($this->db, $leadId, $from, $to, $note);
    }

    public function setWinner(int $leadId, int $buyerId, float $revenue, ?string $transactionId): void
    {
        $this->db->query(
            'UPDATE leads SET winning_buyer_id = :b, revenue = :r, buyer_transaction_id = :t, sold_at = NOW(), updated_at = NOW() WHERE id = :id',
            ['b' => $buyerId, 'r' => $revenue, 't' => $transactionId, 'id' => $leadId]
        );
    }

    public function search(array $filters, int $page = 1, int $limit = 50): array
    {
        $where = ['1=1'];
        $params = [];
        foreach (['lead_id', 'email', 'phone', 'state', 'status'] as $f) {
            if (!empty($filters[$f])) { $where[] = "$f = :$f"; $params[$f] = $filters[$f]; }
        }
        if (!empty($filters['source_id']))   { $where[] = 'source_id = :sid';   $params['sid']   = (int)$filters['source_id']; }
        if (!empty($filters['campaign_id'])) { $where[] = 'campaign_id = :cid'; $params['cid']   = (int)$filters['campaign_id']; }
        if (!empty($filters['buyer_id']))    { $where[] = 'winning_buyer_id = :bid'; $params['bid'] = (int)$filters['buyer_id']; }
        if (!empty($filters['date_from']))   { $where[] = 'created_at >= :df'; $params['df'] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'created_at <= :dt'; $params['dt'] = $filters['date_to']; }

        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT id, lead_id, status, first_name, last_name, state, zip, source_id, campaign_id,
                       winning_buyer_id, revenue, created_at, sold_at
                FROM leads WHERE ' . implode(' AND ', $where) .
               " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $rows = $this->db->all($sql, $params);
        $count = (int)($this->db->one('SELECT COUNT(*) c FROM leads WHERE ' . implode(' AND ', $where), $params)['c'] ?? 0);
        return ['data' => $rows, 'total' => $count, 'page' => $page, 'limit' => $limit];
    }

    public function statusHistory(int $leadId): array
    {
        return $this->db->all('SELECT * FROM lead_status_history WHERE lead_id = :id ORDER BY id ASC', ['id' => $leadId]);
    }

    private function recordStatus(Database $db, int $leadId, ?string $from, string $to, ?string $note): void
    {
        $db->insert('lead_status_history', [
            'lead_id' => $leadId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
