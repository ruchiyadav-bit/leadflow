<?php
use LeadFlow\Core\Config;
return [
    'host' => Config::env('DB_HOST', '127.0.0.1'),
    'port' => (int)Config::env('DB_PORT', 3306),
    'database' => Config::env('DB_DATABASE', 'leadflow'),
    'username' => Config::env('DB_USERNAME', 'root'),
    'password' => Config::env('DB_PASSWORD', ''),
    'charset' => Config::env('DB_CHARSET', 'utf8mb4'),
];
