<?php
declare(strict_types=1);

namespace LeadFlow\Middleware;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\RedisClient;
use LeadFlow\Core\Config;

final class RateLimitMiddleware
{
    public function __construct(private RedisClient $redis, private Config $config) {}

    public function handle(Request $req, callable $next): Response
    {
        $bucket = 'ip:' . $req->ip();
        $max = (int)$this->config->get('pingpost.rate_limit_per_minute', 600);
        if ($this->redis->rateLimitHit($bucket, 60, $max)) {
            return Response::json(['error' => 'rate_limited'], 429);
        }
        return $next($req);
    }
}
