<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Services\ReportService;

final class ReportsController
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $req): Response
    {
        $from = $req->query['from'] ?? date('Y-m-01 00:00:00');
        $to   = $req->query['to']   ?? date('Y-m-d 23:59:59');
        return View::render('reports.index', [
            'from' => $from, 'to' => $to,
            'byBuyer' => $this->reports->revenueByBuyer($from, $to),
            'bySource' => $this->reports->revenueBySource($from, $to),
            'user' => $req->user,
        ]);
    }

    public function exportRevenueCsv(Request $req): Response
    {
        $from = $req->query['from'] ?? date('Y-m-01 00:00:00');
        $to   = $req->query['to']   ?? date('Y-m-d 23:59:59');
        $rows = $this->reports->revenueByBuyer($from, $to);
        $out = "buyer_id,buyer_name,sold_count,revenue,avg_payout\n";
        foreach ($rows as $r) {
            $out .= sprintf("%d,%s,%d,%.4f,%.4f\n",
                (int)$r['id'], str_replace(',', ' ', (string)$r['name']),
                (int)$r['sold'], (float)$r['revenue'], (float)$r['avg']);
        }
        return (new Response($out, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="revenue.csv"',
        ]));
    }
}
