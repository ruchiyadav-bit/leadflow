<?php
use LeadFlow\Core\Config;
return [
    'name' => Config::env('APP_NAME', 'LeadFlow'),
    'env' => Config::env('APP_ENV', 'production'),
    'debug' => (bool)Config::env('APP_DEBUG', false),
    'url' => Config::env('APP_URL', 'http://localhost:8080'),
    'key' => Config::env('APP_KEY', 'change-me'),
    'jwt_secret' => Config::env('JWT_SECRET', 'change-me'),
    'bcrypt_cost' => (int)Config::env('BCRYPT_COST', 12),
    'session_lifetime' => (int)Config::env('SESSION_LIFETIME_MIN', 120),
];
