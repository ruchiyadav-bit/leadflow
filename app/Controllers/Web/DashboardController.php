<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Services\ReportService;

final class DashboardController
{
    public function __construct(private ReportService $reports) {}
    public function index(Request $req): Response
    {
        return View::render('dashboard.index', [
            'stats' => $this->reports->dashboard(),
            'topBuyers' => $this->reports->revenueByBuyer(date('Y-m-01 00:00:00'), date('Y-m-d 23:59:59')),
            'user' => $req->user,
        ]);
    }
}
