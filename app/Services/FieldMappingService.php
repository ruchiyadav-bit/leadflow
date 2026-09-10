<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Support\Normalizer;

/**
 * Applies buyer-specific field mappings and transformations.
 * field_map: { "buyer_field": "internal_field" }
 * transformations: [ { field: "phone_out", op: "phone_format", format: "e164"|"digits"|"dashed" }, ... ]
 */
final class FieldMappingService
{
    public function apply(array $lead, array $fieldMap, array $transformations, array $extras = []): array
    {
        $out = [];
        $source = array_merge($lead, $extras);
        if (empty($fieldMap)) {
            $out = $source;
        } else {
            foreach ($fieldMap as $buyerField => $internalField) {
                $out[$buyerField] = $source[$internalField] ?? null;
            }
        }
        foreach ($transformations as $t) {
            $field = $t['field'] ?? null;
            $op = $t['op'] ?? null;
            if (!$field || !$op || !array_key_exists($field, $out)) continue;
            $val = $out[$field];
            $out[$field] = match ($op) {
                'phone_format' => $this->phoneFormat($val, $t['format'] ?? 'digits'),
                'date_format'  => $val ? date($t['format'] ?? 'Y-m-d', strtotime($val)) : $val,
                'upper'        => is_string($val) ? strtoupper($val) : $val,
                'lower'        => is_string($val) ? strtolower($val) : $val,
                'default'      => ($val === null || $val === '') ? ($t['value'] ?? '') : $val,
                'replace'      => is_string($val) ? str_replace($t['search'] ?? '', $t['replace'] ?? '', $val) : $val,
                'concat'       => trim(($out[$t['left'] ?? ''] ?? '') . ($t['separator'] ?? ' ') . ($out[$t['right'] ?? ''] ?? '')),
                'bool'         => (bool)$val ? ($t['true_value'] ?? 'Y') : ($t['false_value'] ?? 'N'),
                'number_format'=> is_numeric($val) ? number_format((float)$val, (int)($t['decimals'] ?? 2), '.', '') : $val,
                default        => $val,
            };
        }
        return $out;
    }

    private function phoneFormat(mixed $val, string $format): mixed
    {
        $digits = Normalizer::phone((string)$val);
        if (!$digits) return $val;
        return match ($format) {
            'e164'   => '+1' . $digits,
            'dashed' => substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6),
            'paren'  => '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6),
            default  => $digits,
        };
    }
}
