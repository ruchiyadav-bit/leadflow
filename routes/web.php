<?php
/** @var \LeadFlow\Core\Router $router */

use LeadFlow\Controllers\Web\AuthController;
use LeadFlow\Controllers\Web\DashboardController;
use LeadFlow\Controllers\Web\BuyersController;
use LeadFlow\Controllers\Web\LeadsController;
use LeadFlow\Controllers\Web\PingTreesController;
use LeadFlow\Controllers\Web\ReportsController;
use LeadFlow\Middleware\WebAuthMiddleware;

$router->get('/', fn() => \LeadFlow\Core\Response::redirect('/dashboard'));
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/health', fn() => \LeadFlow\Core\Response::json(['status' => 'ok', 'ts' => time()]));

$router->group(['middleware' => [WebAuthMiddleware::class]], function ($r) {
    $r->get('/dashboard', [DashboardController::class, 'index']);
    $r->get('/buyers', [BuyersController::class, 'index']);
    $r->get('/buyers/new', [BuyersController::class, 'create']);
    $r->post('/buyers', [BuyersController::class, 'store']);
    $r->get('/buyers/{id}', [BuyersController::class, 'show']);
    $r->post('/buyers/{id}', [BuyersController::class, 'update']);
    $r->post('/buyers/{id}/toggle', [BuyersController::class, 'toggle']);

    $r->get('/leads', [LeadsController::class, 'index']);
    $r->get('/leads/{id}', [LeadsController::class, 'show']);

    $r->get('/ping-trees', [PingTreesController::class, 'index']);
    $r->get('/ping-trees/new', [PingTreesController::class, 'create']);
    $r->post('/ping-trees', [PingTreesController::class, 'store']);
    $r->get('/ping-trees/{id}', [PingTreesController::class, 'show']);
    $r->post('/ping-trees/{id}', [PingTreesController::class, 'update']);

    $r->get('/reports', [ReportsController::class, 'index']);
    $r->get('/reports/revenue.csv', [ReportsController::class, 'exportRevenueCsv']);
});
