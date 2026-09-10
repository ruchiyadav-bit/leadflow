<?php
declare(strict_types=1);

namespace LeadFlow\Support;

/**
 * Minimal dot-path JSON extractor.
 * Supports:  a.b.c   a.b.0.c   a.*.c (returns first)
 */
final class JsonPath
{
    public static function get(mixed $data, string $path, mixed $default = null): mixed
    {
        if ($path === '' || $path === '.') return $data;
        $segments = explode('.', $path);
        foreach ($segments as $seg) {
            if ($seg === '*' && is_array($data)) {
                $first = reset($data);
                $data = $first === false ? null : $first;
                continue;
            }
            if (is_array($data) && array_key_exists($seg, $data)) {
                $data = $data[$seg];
            } elseif (is_object($data) && isset($data->{$seg})) {
                $data = $data->{$seg};
            } else {
                return $default;
            }
        }
        return $data;
    }
}
