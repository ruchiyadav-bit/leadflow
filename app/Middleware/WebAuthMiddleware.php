<?php
declare(strict_types=1);

namespace LeadFlow\Middleware;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Services\AuthService;

final class WebAuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function handle(Request $req, callable $next): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
        }
        if (empty($_SESSION['user_id'])) {
            return Response::redirect('/login');
        }
        $user = $this->auth->getUser((int)$_SESSION['user_id']);
        if (!$user) {
            session_destroy();
            return Response::redirect('/login');
        }
        $req->user = $user;
        return $next($req);
    }
}
