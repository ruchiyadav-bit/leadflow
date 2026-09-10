<?php
declare(strict_types=1);

namespace LeadFlow\Repositories;

use LeadFlow\Core\Database;

final class PingRepository
{
    public function __construct(private Database $db) {}

    public function recordPing(int $leadId, int $buyerId, string $status, ?bool $accepted, ?float $bid, ?string $txnId, int $rtMs, ?string $reqBody, ?string $respBody, ?string $error): int
    {
        $this->db->insert('ping_transactions', [
            'lead_id' => $leadId,
            'buyer_id' => $buyerId,
            'status' => $status,
            'accepted' => $accepted === null ? null : (int)$accepted,
            'bid' => $bid,
            'transaction_id' => $txnId,
            'response_time_ms' => $rtMs,
            'error_message' => $error ? substr($error, 0, 512) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pingId = (int)$this->db->pdo()->lastInsertId();
        $this->db->insert('ping_responses', [
            'ping_transaction_id' => $pingId,
            'request_body' => $reqBody ? substr($reqBody, 0, 65535) : null,
            'response_body' => $respBody ? substr($respBody, 0, 65535) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $pingId;
    }

    public function recordPost(int $leadId, int $buyerId, bool $success, ?string $txnId, ?float $payout, int $rtMs, ?string $reqBody, ?string $respBody, ?string $error, string $idempotencyKey): int
    {
        $this->db->insert('post_transactions', [
            'lead_id' => $leadId,
            'buyer_id' => $buyerId,
            'success' => (int)$success,
            'transaction_id' => $txnId,
            'payout' => $payout,
            'response_time_ms' => $rtMs,
            'request_body' => $reqBody ? substr($reqBody, 0, 65535) : null,
            'response_body' => $respBody ? substr($respBody, 0, 65535) : null,
            'error_message' => $error ? substr($error, 0, 512) : null,
            'idempotency_key' => $idempotencyKey,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $postId = (int)$this->db->pdo()->lastInsertId();
        if ($success && $payout !== null) {
            $this->db->insert('revenue_records', [
                'lead_id' => $leadId,
                'buyer_id' => $buyerId,
                'post_transaction_id' => $postId,
                'revenue' => $payout,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $postId;
    }

    public function pingsForLead(int $leadId): array
    {
        return $this->db->all(
            'SELECT pt.*, b.name AS buyer_name, pr.request_body, pr.response_body
             FROM ping_transactions pt
             JOIN buyers b ON b.id = pt.buyer_id
             LEFT JOIN ping_responses pr ON pr.ping_transaction_id = pt.id
             WHERE pt.lead_id = :l ORDER BY pt.id ASC',
            ['l' => $leadId]
        );
    }

    public function postsForLead(int $leadId): array
    {
        return $this->db->all(
            'SELECT po.*, b.name AS buyer_name
             FROM post_transactions po
             JOIN buyers b ON b.id = po.buyer_id
             WHERE po.lead_id = :l ORDER BY po.id ASC',
            ['l' => $leadId]
        );
    }
}
