<?php
declare(strict_types=1);

namespace LeadFlow\Repositories;

use LeadFlow\Core\Database;

final class FormPartnerRepository
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->all(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.partner_id = p.id AND s.status = 'submitted') AS submitted,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.partner_id = p.id AND s.status = 'failed') AS failed,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.partner_id = p.id AND s.status IN ('queued','running')) AS pending
             FROM form_partners p ORDER BY p.sort_order ASC, p.id ASC"
        );
    }

    public function active(): array
    {
        return $this->db->all('SELECT * FROM form_partners WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM form_partners WHERE id = :id', ['id' => $id]);
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        return (int)$this->db->insert('form_partners', $data);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('form_partners', $data, 'id = :id', ['id' => $id]);
    }

    public function toggleActive(int $id): void
    {
        $this->db->query('UPDATE form_partners SET active = 1 - active, updated_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    /** Queue a new lead for every active partner. Never throws into the intake path. */
    public function enqueueLead(int $leadRowId): int
    {
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach ($this->active() as $p) {
            $this->db->query(
                'INSERT IGNORE INTO form_submissions (lead_id, partner_id, status, attempts, next_attempt_at, created_at, updated_at)
                 VALUES (:l, :p, :s, 0, :n, :c, :u)',
                ['l' => $leadRowId, 'p' => (int)$p['id'], 's' => 'queued', 'n' => $now, 'c' => $now, 'u' => $now]
            );
            $n++;
        }
        return $n;
    }

    public function retry(int $submissionId): void
    {
        $this->db->query(
            "UPDATE form_submissions SET status = 'queued', attempts = 0, next_attempt_at = NOW(), locked_at = NULL, error_message = NULL, updated_at = NOW()
             WHERE id = :id AND status = 'failed'",
            ['id' => $submissionId]
        );
    }

    /** Recent leads with one row each and per-partner submission status. */
    public function log(int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $leads = $this->db->all(
            "SELECT DISTINCT l.id, l.lead_id, l.created_at
             FROM form_submissions s JOIN leads l ON l.id = s.lead_id
             ORDER BY l.id DESC LIMIT $limit OFFSET $offset"
        );
        if (!$leads) return [];
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $leads));
        $subs = $this->db->all("SELECT * FROM form_submissions WHERE lead_id IN ($ids)");
        $byLead = [];
        foreach ($subs as $s) $byLead[(int)$s['lead_id']][(int)$s['partner_id']] = $s;
        foreach ($leads as &$l) $l['submissions'] = $byLead[(int)$l['id']] ?? [];
        return $leads;
    }
}
