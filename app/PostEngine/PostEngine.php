<?php
declare(strict_types=1);

namespace LeadFlow\PostEngine;

use GuzzleHttp\Client;
use LeadFlow\Services\FieldMappingService;
use LeadFlow\Services\ResponseParserService;
use LeadFlow\Repositories\PingRepository;
use LeadFlow\Core\RedisClient;
use LeadFlow\Core\Logger;

final class PostEngine
{
    public function __construct(
        private FieldMappingService $mapper,
        private ResponseParserService $parser,
        private PingRepository $pingRepo,
        private RedisClient $redis,
        private Logger $log,
    ) {}

    /**
     * POST lead to winning buyer, with fallback attempts.
     * @param array $orderedWinners  array of ping result entries in priority order
     * @return array{success:bool, buyer:?array, payout:?float, transaction_id:?string, post_id:?int, attempts:array}
     */
    public function post(array $lead, array $orderedWinners, bool $allowFallback = true): array
    {
        $client = new Client(['http_errors' => false, 'connect_timeout' => 3.0, 'verify' => false]);
        $attempts = [];
        foreach ($orderedWinners as $winner) {
            $buyer = $winner['buyer'];
            $lockKey = 'post:lead:' . $lead['id'];
            if (!$this->redis->lock($lockKey, 30)) {
                $attempts[] = ['buyer_id' => $buyer['id'], 'reason' => 'lock_busy'];
                return ['success' => false, 'buyer' => null, 'payout' => null, 'transaction_id' => null, 'post_id' => null, 'attempts' => $attempts];
            }
            try {
                $idempotencyKey = hash('sha256', $lead['lead_id'] . ':' . $buyer['id']);
                $payload = $this->mapper->apply($lead, $buyer['field_map'] ?? [], $buyer['transformations'] ?? [], [
                    'ping_mode' => 'post', 'transaction_id' => $winner['transaction_id'] ?? '',
                ]);
                $method = strtoupper($buyer['post_method'] ?? 'POST');
                $format = $buyer['request_format'] ?? 'json';
                $timeoutMs = (int)($buyer['timeout_ms'] ?? 8000);
                $options = [
                    'timeout' => max(1.0, $timeoutMs / 1000.0),
                    'headers' => array_merge(
                        $this->authHeaders($buyer),
                        $buyer['headers'] ?? [],
                        ['Idempotency-Key' => $idempotencyKey]
                    ),
                ];
                if ($method === 'GET') { $options['query'] = $payload; $reqBody = http_build_query($payload); }
                elseif ($format === 'form') { $options['form_params'] = $payload; $reqBody = http_build_query($payload); }
                elseif ($format === 'xml') { $reqBody = $this->toXml($payload); $options['body'] = $reqBody; $options['headers']['Content-Type'] = 'application/xml'; }
                else { $options['json'] = $payload; $reqBody = json_encode($payload); }

                $start = microtime(true);
                $success = false; $err = null; $respBody = null; $txn = null; $payout = null; $postId = null;
                try {
                    $resp = $client->request($method, $buyer['post_url'], $options);
                    $respBody = (string)$resp->getBody();
                    $ct = $resp->getHeaderLine('Content-Type');
                    if ($resp->getStatusCode() >= 400) {
                        $err = 'HTTP ' . $resp->getStatusCode();
                    } else {
                        $parsed = $this->parser->parse($respBody, $ct, $buyer['response_rules'] ?? []);
                        if ($parsed['accepted'] === true) {
                            $success = true;
                            $payout = $parsed['bid'] ?? ($winner['bid'] ?? null);
                            $txn = $parsed['transaction_id'] ?? ($winner['transaction_id'] ?? null);
                        } else {
                            $err = $parsed['error'] ?? 'not_accepted';
                        }
                    }
                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                }
                $rt = (int)((microtime(true) - $start) * 1000);
                $postId = $this->pingRepo->recordPost((int)$lead['id'], (int)$buyer['id'], $success, $txn, $payout, $rt, $reqBody, $respBody, $err, $idempotencyKey);
                $attempts[] = ['buyer_id' => (int)$buyer['id'], 'success' => $success, 'error' => $err, 'payout' => $payout, 'rt_ms' => $rt];

                if ($success) {
                    return ['success' => true, 'buyer' => $buyer, 'payout' => $payout, 'transaction_id' => $txn, 'post_id' => $postId, 'attempts' => $attempts];
                }
                if (!$allowFallback) break;
            } finally {
                $this->redis->unlock($lockKey);
            }
        }
        return ['success' => false, 'buyer' => null, 'payout' => null, 'transaction_id' => null, 'post_id' => null, 'attempts' => $attempts];
    }

    private function authHeaders(array $buyer): array
    {
        $creds = $buyer['credentials'] ?? [];
        $h = [];
        $type = $creds['type'] ?? null;
        if ($type === 'bearer' && !empty($creds['token'])) $h['Authorization'] = 'Bearer ' . $creds['token'];
        elseif ($type === 'basic' && !empty($creds['username'])) $h['Authorization'] = 'Basic ' . base64_encode($creds['username'] . ':' . ($creds['password'] ?? ''));
        elseif ($type === 'api_key' && !empty($creds['key'])) $h[$creds['header'] ?? 'X-API-Key'] = $creds['key'];
        return $h;
    }

    private function toXml(array $data, string $root = 'lead'): string
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><{$root}/>");
        $add = function ($arr, $node) use (&$add) {
            foreach ($arr as $k => $v) {
                $k = preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$k);
                if (is_array($v)) $add($v, $node->addChild($k));
                else $node->addChild($k, htmlspecialchars((string)$v, ENT_XML1));
            }
        };
        $add($data, $xml);
        return $xml->asXML() ?: '';
    }
}
