<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Services\AuthService;

final class AuthApiController
{
    public function __construct(private AuthService $auth) {}
    public function login(Request $req): Response
    {
        $u = $this->auth->attempt((string)$req->input('email'), (string)$req->input('password'));
        if (!$u) return Response::json(['error' => 'invalid_credentials'], 401);
        return Response::json(['token' => $this->auth->issueJwt($u), 'user' => ['id'=>$u['id'],'email'=>$u['email'],'role'=>$u['role']]]);
    }
}
