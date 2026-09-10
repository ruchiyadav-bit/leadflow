<?php
declare(strict_types=1);

namespace LeadFlow\Core;

final class Request
{
    public array $query = [];
    public array $body = [];
    public array $headers = [];
    public array $server = [];
    public array $routeParams = [];
    public array $cookies = [];
    public ?array $user = null;

    public static function capture(): self
    {
        $r = new self();
        $r->query = $_GET;
        $r->server = $_SERVER;
        $r->cookies = $_COOKIE;
        $r->headers = self::readHeaders();
        $ct = strtolower($r->headers['content-type'] ?? '');
        $raw = file_get_contents('php://input') ?: '';
        if (str_contains($ct, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            $r->body = is_array($decoded) ? $decoded : [];
        } else {
            $r->body = $_POST;
        }
        return $r;
    }

    private static function readHeaders(): array
    {
        $h = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $h[$name] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $h['content-length'] = $_SERVER['CONTENT_LENGTH'];
        return $h;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function withRouteParams(array $params): self
    {
        $c = clone $this;
        $c->routeParams = $params;
        return $c;
    }

    public function route(string $k, mixed $default = null): mixed
    {
        return $this->routeParams[$k] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if (!$auth) return null;
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
        return null;
    }
}
