<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Core\Database;
use LeadFlow\Core\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthService
{
    public function __construct(private Database $db, private Config $config) {}

    public function attempt(string $email, string $password): ?array
    {
        $u = $this->db->one('SELECT * FROM users WHERE email = :e AND active = 1 LIMIT 1', ['e' => $email]);
        if (!$u) return null;
        if (!password_verify($password, $u['password_hash'])) return null;
        $this->db->query('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $u['id']]);
        return $u;
    }

    public function getUser(int $id): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE id = :id AND active = 1', ['id' => $id]);
    }

    public function issueJwt(array $user): string
    {
        $secret = $this->config->get('app.jwt_secret');
        $payload = [
            'sub' => (int)$user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 3600 * 8,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    public function verifyJwt(string $token): ?array
    {
        try {
            $secret = $this->config->get('app.jwt_secret');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $data = (array)$decoded;
            $user = $this->getUser((int)$data['sub']);
            return $user;
        } catch (\Throwable) {
            return null;
        }
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => (int)$this->config->get('app.bcrypt_cost', 12)]);
    }

    public function hasRole(array $user, array $allowed): bool
    {
        return in_array($user['role'] ?? '', $allowed, true);
    }
}
