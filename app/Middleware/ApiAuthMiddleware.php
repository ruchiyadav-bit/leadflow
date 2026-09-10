<?php
declare(strict_types=1);

namespace LeadFlow\Middleware;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Services\AuthService;

final class ApiAuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function handle(Request $req, callable $next): Response
    {
        $token = $req->bearerToken();
        if (!$token) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $user = $this->auth->verifyJwt($token);
        if (!$user) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $req->user = $user;
        return $next($req);
    }
}
