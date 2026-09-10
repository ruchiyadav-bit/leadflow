<?php
/** @var \LeadFlow\Core\Router $router */

use LeadFlow\Controllers\Api\LeadApiController;
use LeadFlow\Controllers\Api\BuyerApiController;
use LeadFlow\Controllers\Api\PingTreeApiController;
use LeadFlow\Controllers\Api\ReportApiController;
use LeadFlow\Controllers\Api\AuthApiController;
use LeadFlow\Middleware\ApiAuthMiddleware;
use LeadFlow\Middleware\RateLimitMiddleware;
use LeadFlow\Middleware\LeadIntakeAuthMiddleware;

$router->group(['prefix' => '/api/v1'], function ($r) {
    // Public - lead intake (accepts either API key or public source key)
    $r->post('/leads', [LeadApiController::class, 'create'], [RateLimitMiddleware::class, LeadIntakeAuthMiddleware::class]);

    // Auth
    $r->post('/auth/login', [AuthApiController::class, 'login'], [RateLimitMiddleware::class]);

    // Protected (JWT bearer)
    $r->group(['middleware' => [ApiAuthMiddleware::class]], function ($r) {
        $r->get('/leads', [LeadApiController::class, 'index']);
        $r->get('/leads/{id}', [LeadApiController::class, 'show']);
        $r->get('/leads/{id}/journey', [LeadApiController::class, 'journey']);

        $r->get('/buyers', [BuyerApiController::class, 'index']);
        $r->post('/buyers', [BuyerApiController::class, 'store']);
        $r->get('/buyers/{id}', [BuyerApiController::class, 'show']);
        $r->put('/buyers/{id}', [BuyerApiController::class, 'update']);
        $r->post('/buyers/{id}/rules', [BuyerApiController::class, 'addRule']);
        $r->post('/buyers/{id}/test-ping', [BuyerApiController::class, 'testPing']);

        $r->get('/ping-trees', [PingTreeApiController::class, 'index']);
        $r->post('/ping-trees', [PingTreeApiController::class, 'store']);
        $r->get('/ping-trees/{id}', [PingTreeApiController::class, 'show']);
        $r->put('/ping-trees/{id}', [PingTreeApiController::class, 'update']);

        $r->get('/reports/revenue', [ReportApiController::class, 'revenue']);
        $r->get('/reports/buyers', [ReportApiController::class, 'buyers']);
    });
});
