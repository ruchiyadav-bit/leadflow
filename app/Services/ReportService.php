<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Core\Database;

final class ReportService
{
    public function __construct(private Database $db) {}

    public function dashboard(): array
    {
        $r = $this->db->one("SELECT
            (SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()) AS today_leads,
            (SELECT COUNT(*) FROM leads WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS yesterday_leads,
            (SELECT COUNT(*) FROM leads WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS monthly_leads,
            (SELECT COUNT(*) FROM leads WHERE status = 'SOLD') AS sold_leads,
            (SELECT COUNT(*) FROM leads WHERE status = 'REJECTED') AS rejected_leads,
            (SELECT COUNT(*) FROM leads WHERE status IN ('NEW','VALIDATING','VALID','PINGING','BIDS_RECEIVED','WINNER_SELECTED','POSTING')) AS pending_leads,
            (SELECT COALESCE(SUM(revenue),0) FROM revenue_records WHERE DATE(created_at) = CURDATE()) AS revenue_today,
            (SELECT COALESCE(SUM(revenue),0) FROM revenue_records WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS revenue_yesterday,
            (SELECT COALESCE(SUM(revenue),0) FROM revenue_records WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS revenue_month
        ") ?? [];
        $ml = max(1, (int)($r['monthly_leads'] ?? 1));
        $r['avg_revenue_per_lead'] = round(((float)($r['revenue_month'] ?? 0)) / $ml, 4);
        $r['sell_rate'] = round(((int)($r['sold_leads'] ?? 0)) / $ml, 4);
        return $r;
    }

    public function revenueByBuyer(?string $from = null, ?string $to = null): array
    {
        $where = []; $params = [];
        if ($from) { $where[] = 'rr.created_at >= :f'; $params['f'] = $from; }
        if ($to)   { $where[] = 'rr.created_at <= :t'; $params['t'] = $to; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $this->db->all("SELECT b.id, b.name, COUNT(*) AS sold, SUM(rr.revenue) AS revenue, AVG(rr.revenue) AS avg
                               FROM revenue_records rr JOIN buyers b ON b.id = rr.buyer_id $w GROUP BY b.id ORDER BY revenue DESC", $params);
    }

    public function revenueBySource(?string $from = null, ?string $to = null): array
    {
        $where = []; $params = [];
        if ($from) { $where[] = 'l.created_at >= :f'; $params['f'] = $from; }
        if ($to)   { $where[] = 'l.created_at <= :t'; $params['t'] = $to; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $this->db->all("SELECT s.id, s.name, COUNT(*) AS sold, SUM(l.revenue) AS revenue
                               FROM leads l LEFT JOIN sources s ON s.id = l.source_id
                               $w AND l.status = 'SOLD' GROUP BY s.id ORDER BY revenue DESC", $params);
    }
}
