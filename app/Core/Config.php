<?php
declare(strict_types=1);

namespace LeadFlow\Core;

final class Config
{
    private array $items = [];

    public function __construct(private string $configPath)
    {
        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $this->items[$name] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->items;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') return $default;
        return match (strtolower((string)$v)) {
            'true' => true, 'false' => false, 'null' => null, default => $v,
        };
    }
}
