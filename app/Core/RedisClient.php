<?php
declare(strict_types=1);

namespace LeadFlow\Core;

use Predis\Client;

final class RedisClient
{
    private ?Client $client = null;

    public function __construct(private Config $config) {}

    public function client(): Client
    {
        if ($this->client !== null) return $this->client;
        $c = $this->config->get('redis');
        $this->client = new Client([
            'scheme' => 'tcp',
            'host'   => $c['host'],
            'port'   => (int)$c['port'],
            'password' => $c['password'] ?: null,
        ]);
        return $this->client;
    }

    public function lock(string $key, int $ttlSeconds = 10): bool
    {
        return (bool)$this->client()->set("lock:$key", '1', 'EX', $ttlSeconds, 'NX');
    }

    public function unlock(string $key): void
    {
        $this->client()->del(["lock:$key"]);
    }

    public function rateLimitHit(string $bucket, int $windowSeconds, int $max): bool
    {
        $key = "ratelimit:$bucket:" . floor(time() / $windowSeconds);
        $count = $this->client()->incr($key);
        if ($count === 1) $this->client()->expire($key, $windowSeconds);
        return $count > $max;
    }
}
