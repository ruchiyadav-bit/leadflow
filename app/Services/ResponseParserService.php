<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Support\JsonPath;

/**
 * Parses a buyer response.
 * rules = {
 *   accepted_path: "status",
 *   accepted_values: ["accepted", "ok", "matched", "1", "true"],
 *   rejected_values: ["rejected", "no", "0", "false"],
 *   bid_path: "bid",
 *   transaction_id_path: "transaction_id",
 *   error_path: "message"
 * }
 */
final class ResponseParserService
{
    public function parse(string $rawBody, string $contentType, array $rules): array
    {
        $data = $this->decode($rawBody, $contentType);

        $acceptedRaw = JsonPath::get($data, $rules['accepted_path'] ?? 'status');
        $acceptedStr = is_bool($acceptedRaw) ? ($acceptedRaw ? '1' : '0') : strtolower((string)$acceptedRaw);
        $acceptedValues = array_map('strtolower', $rules['accepted_values'] ?? ['accepted','ok','matched','1','true','success']);
        $rejectedValues = array_map('strtolower', $rules['rejected_values'] ?? ['rejected','error','no','0','false','fail']);

        $accepted = null;
        if (in_array($acceptedStr, $acceptedValues, true)) $accepted = true;
        elseif (in_array($acceptedStr, $rejectedValues, true)) $accepted = false;

        $bid = JsonPath::get($data, $rules['bid_path'] ?? 'bid');
        $bid = is_numeric($bid) ? (float)$bid : null;

        $txnId = JsonPath::get($data, $rules['transaction_id_path'] ?? 'transaction_id');
        $txnId = ($txnId === null || $txnId === '') ? null : (string)$txnId;

        $error = JsonPath::get($data, $rules['error_path'] ?? 'message');
        $error = ($error === null || $error === '') ? null : (string)$error;

        if ($accepted === null && $bid !== null && $bid > 0) $accepted = true;

        return [
            'accepted' => $accepted,
            'bid' => $bid,
            'transaction_id' => $txnId,
            'error' => $error,
            'raw' => $data,
        ];
    }

    private function decode(string $body, string $ct): mixed
    {
        $ct = strtolower($ct);
        if (str_contains($ct, 'json') || (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '['))) {
            $d = json_decode($body, true);
            if (is_array($d)) return $d;
        }
        if (str_contains($ct, 'xml')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            if ($xml !== false) return json_decode(json_encode($xml), true);
        }
        parse_str($body, $parsed);
        return $parsed;
    }
}
