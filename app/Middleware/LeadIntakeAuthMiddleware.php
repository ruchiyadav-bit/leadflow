<?php
declare(strict_types=1);

namespace LeadFlow\Middleware;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\Database;

final class LeadIntakeAuthMiddleware
{
    public function __construct(private Database $db) {}

    public function handle(Request $req, callable $next): Response
    {
        $key = $req->header('x-api-key') ?? $req->input('api_key');
        if (!$key) {
            return Response::json(['error' => 'missing_api_key'], 401);
        }
        $source = $this->db->one(
            'SELECT * FROM sources WHERE api_key = :k AND active = 1 LIMIT 1',
            ['k' => $key]
        );
        if (!$source) {
            return Response::json(['error' => 'invalid_api_key'], 401);
        }
        $req->user = ['source' => $source];
        return $next($req);
    }
}
