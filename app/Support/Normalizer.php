<?php
declare(strict_types=1);

namespace LeadFlow\Support;

final class Normalizer
{
    public static function phone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '1') $digits = substr($digits, 1);
        return strlen($digits) === 10 ? $digits : null;
    }

    public static function email(?string $email): ?string
    {
        if (!$email) return null;
        $e = strtolower(trim($email));
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    public static function state(?string $state): ?string
    {
        if (!$state) return null;
        return strtoupper(substr(trim($state), 0, 2));
    }

    public static function zip(?string $zip): ?string
    {
        if (!$zip) return null;
        return preg_match('/^\d{5}(-\d{4})?$/', trim($zip)) ? trim($zip) : null;
    }

    public static function money(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        $clean = preg_replace('/[^0-9.\-]/', '', (string)$v) ?? '';
        return $clean === '' ? null : (float)$clean;
    }

    public static function date(?string $d): ?string
    {
        if (!$d) return null;
        $ts = strtotime($d);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    public static function age(?string $dob): ?int
    {
        $d = self::date($dob);
        if (!$d) return null;
        return (int)((time() - strtotime($d)) / (365.25 * 86400));
    }
}
