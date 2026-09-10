<?php
declare(strict_types=1);

namespace LeadFlow\PingEngine;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use LeadFlow\Services\FieldMappingService;
use LeadFlow\Services\ResponseParserService;
use LeadFlow\Repositories\PingRepository;
use LeadFlow\Core\Logger;

final class PingEngine
{
    public function __construct(
        private FieldMappingService $mapper,
        private ResponseParserService $parser,
        private PingRepository $pingRepo,
        private Logger $log,
    ) {}

    /**
     * Concurrently ping all eligible buyers.
     * @return array<int, array{buyer:array, accepted:?bool, bid:?float, transaction_id:?string, error:?string, status:string, rt_ms:int, ping_id:int}>
     */
    public function pingAll(array $lead, array $buyers, int $globalTimeoutMs = 5000): array
    {
        $client = new Client([
            'http_errors' => false,
            'connect_timeout' => 2.0,
            'verify' => false,
        ]);

        $promises = [];
        $meta = [];
        foreach ($buyers as $buyer) {
            $timeoutS = max(0.5, min($globalTimeoutMs, (int)$buyer['timeout_ms']) / 1000.0);
            $payload = $this->mapper->apply($lead, $buyer['field_map'] ?? [], $buyer['transformations'] ?? [], [
                'ping_mode' => 'ping',
            ]);
            $format = $buyer['request_format'] ?? 'json';
            $method = strtoupper($buyer['ping_method'] ?? 'POST');
            $headers = array_merge($this->authHeaders($buyer), $buyer['headers'] ?? []);
            $options = [
                'timeout' => $timeoutS,
                'headers' => $headers,
            ];
            if ($method === 'GET') {
                $options['query'] = $payload;
                $bodyForLog = http_build_query($payload);
            } else {
                if ($format === 'form') {
                    $options['form_params'] = $payload;
                    $bodyForLog = http_build_query($payload);
                } elseif ($format === 'xml') {
                    $xml = $this->toXml($payload);
                    $options['body'] = $xml;
                    $options['headers']['Content-Type'] = 'application/xml';
                    $bodyForLog = $xml;
                } else {
                    $options['json'] = $payload;
                    $bodyForLog = json_encode($payload);
                }
            }
            $meta[(int)$buyer['id']] = ['buyer' => $buyer, 'started' => microtime(true), 'req' => $bodyForLog];
            $promises[(int)$buyer['id']] = $client->requestAsync($method, $buyer['ping_url'], $options);
        }

        $results = Utils::settle($promises)->wait();

        $out = [];
        foreach ($results as $bid => $result) {
            $m = $meta[$bid];
            $buyer = $m['buyer'];
            $rt = (int)((microtime(true) - $m['started']) * 1000);
            $status = 'ok'; $accepted = null; $bidVal = null; $txn = null; $err = null; $respBody = null;

            if ($result['state'] === 'fulfilled') {
                /** @var \Psr\Http\Message\ResponseInterface $resp */
                $resp = $result['value'];
                $respBody = (string)$resp->getBody();
                $ct = $resp->getHeaderLine('Content-Type');
                if ($resp->getStatusCode() >= 400) {
                    $status = 'error';
                    $accepted = false;
                    $err = 'HTTP ' . $resp->getStatusCode();
                } else {
                    $parsed = $this->parser->parse($respBody, $ct, $buyer['response_rules'] ?? []);
                    $accepted = $parsed['accepted'];
                    $bidVal = $parsed['bid'];
                    $txn = $parsed['transaction_id'];
                    $err = $parsed['error'];
                    $status = $accepted === true ? 'accepted' : ($accepted === false ? 'rejected' : 'unclear');
                }
            } else {
                $reason = $result['reason'];
                if ($reason instanceof \GuzzleHttp\Exception\ConnectException
                    || (method_exists($reason, 'getHandlerContext') && ($reason->getHandlerContext()['errno'] ?? 0) === 28)) {
                    $status = 'timeout'; $err = 'timeout';
                } else {
                    $status = 'error'; $err = method_exists($reason,'getMessage') ? $reason->getMessage() : 'unknown';
                }
            }

            $pingId = $this->pingRepo->recordPing(
                (int)$lead['id'], (int)$buyer['id'], $status, $accepted, $bidVal, $txn, $rt, $m['req'], $respBody, $err
            );

            $out[] = [
                'buyer' => $buyer,
                'accepted' => $accepted,
                'bid' => $bidVal,
                'transaction_id' => $txn,
                'error' => $err,
                'status' => $status,
                'rt_ms' => $rt,
                'ping_id' => $pingId,
            ];
        }
        return $out;
    }

    private function authHeaders(array $buyer): array
    {
        $creds = $buyer['credentials'] ?? [];
        $h = [];
        $type = $creds['type'] ?? null;
        if ($type === 'bearer' && !empty($creds['token'])) {
            $h['Authorization'] = 'Bearer ' . $creds['token'];
        } elseif ($type === 'basic' && !empty($creds['username'])) {
            $h['Authorization'] = 'Basic ' . base64_encode($creds['username'] . ':' . ($creds['password'] ?? ''));
        } elseif ($type === 'api_key' && !empty($creds['key'])) {
            $h[$creds['header'] ?? 'X-API-Key'] = $creds['key'];
        }
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
