<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Services\AuthService;

final class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function showLogin(Request $req): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return View::render('auth.login', ['error' => $_SESSION['flash_error'] ?? null]);
    }

    public function login(Request $req): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $u = $this->auth->attempt((string)$req->input('email'), (string)$req->input('password'));
        if (!$u) {
            $_SESSION['flash_error'] = 'Invalid credentials';
            return Response::redirect('/login');
        }
        unset($_SESSION['flash_error']);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['user_email'] = $u['email'];
        $_SESSION['user_role'] = $u['role'];
        return Response::redirect('/dashboard');
    }

    public function logout(Request $req): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_destroy();
        return Response::redirect('/login');
    }
}
