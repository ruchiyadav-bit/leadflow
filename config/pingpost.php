<?php
use LeadFlow\Core\Config;
return [
    'ping_default_timeout_ms' => (int)Config::env('PING_DEFAULT_TIMEOUT_MS', 3000),
    'ping_max_concurrency' => (int)Config::env('PING_MAX_CONCURRENCY', 25),
    'post_default_timeout_ms' => (int)Config::env('POST_DEFAULT_TIMEOUT_MS', 8000),
    'auction_min_bid' => (float)Config::env('AUCTION_MIN_BID', 0.01),
    'auction_tie_break' => Config::env('AUCTION_TIE_BREAK', 'response_time'),
    'rate_limit_per_minute' => (int)Config::env('RATE_LIMIT_PER_MINUTE', 600),
];
