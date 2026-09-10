<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Services\ReportService;

final class ReportApiController
{
    public function __construct(private ReportService $reports) {}
    public function revenue(Request $req): Response { return Response::json($this->reports->revenueByBuyer($req->query['from'] ?? null, $req->query['to'] ?? null)); }
    public function buyers(Request $req): Response { return Response::json($this->reports->revenueByBuyer($req->query['from'] ?? null, $req->query['to'] ?? null)); }
}
