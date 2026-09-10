<?php
use LeadFlow\Core\Config;
return [
    'host' => Config::env('REDIS_HOST', '127.0.0.1'),
    'port' => (int)Config::env('REDIS_PORT', 6379),
    'password' => Config::env('REDIS_PASSWORD', ''),
];
