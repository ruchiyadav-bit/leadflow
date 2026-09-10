<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Support\Normalizer;

/**
 * Rule schema:
 * {
 *   "op": "AND" | "OR",
 *   "rules": [
 *     { "field": "state", "operator": "in", "value": ["TX","FL"] },
 *     { "field": "monthly_income", "operator": ">=", "value": 2000 },
 *     { "op": "OR", "rules": [ ... ] }
 *   ]
 * }
 */
final class RuleEngine
{
    public function evaluate(array $lead, array $rules): bool
    {
        if (empty($rules)) return true;
        return $this->evalGroup($lead, $rules);
    }

    private function evalGroup(array $lead, array $group): bool
    {
        $op = strtoupper($group['op'] ?? 'AND');
        $items = $group['rules'] ?? [];
        if (empty($items)) return true;
        foreach ($items as $item) {
            $result = isset($item['op']) ? $this->evalGroup($lead, $item) : $this->evalRule($lead, $item);
            if ($op === 'AND' && !$result) return false;
            if ($op === 'OR' && $result) return true;
        }
        return $op === 'AND';
    }

    private function evalRule(array $lead, array $rule): bool
    {
        $field = $rule['field'] ?? '';
        $operator = strtolower($rule['operator'] ?? '=');
        $expected = $rule['value'] ?? null;
        $actual = $lead[$field] ?? null;

        if ($field === 'age' && $actual === null && !empty($lead['date_of_birth'])) {
            $actual = Normalizer::age($lead['date_of_birth']);
        }

        return match ($operator) {
            '=', '==', 'equals' => $this->str($actual) === $this->str($expected),
            '!=', 'not_equals' => $this->str($actual) !== $this->str($expected),
            '>'  => is_numeric($actual) && is_numeric($expected) && (float)$actual >  (float)$expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && (float)$actual >= (float)$expected,
            '<'  => is_numeric($actual) && is_numeric($expected) && (float)$actual <  (float)$expected,
            '<=' => is_numeric($actual) && is_numeric($expected) && (float)$actual <= (float)$expected,
            'in'      => is_array($expected) && in_array($this->str($actual), array_map([$this,'str'], $expected), true),
            'not_in'  => is_array($expected) && !in_array($this->str($actual), array_map([$this,'str'], $expected), true),
            'between' => is_array($expected) && is_numeric($actual) && (float)$actual >= (float)$expected[0] && (float)$actual <= (float)$expected[1],
            'exists', 'not_empty' => $actual !== null && $actual !== '',
            'empty' => $actual === null || $actual === '',
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            default => false,
        };
    }

    private function str(mixed $v): string
    {
        if (is_bool($v)) return $v ? '1' : '0';
        return (string)$v;
    }
}
