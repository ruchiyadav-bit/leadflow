<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Repositories\BuyerRepository;
use LeadFlow\DirectPost\PostOnlyEngine;

final class BuyersController
{
    public function __construct(private BuyerRepository $buyers, private PostOnlyEngine $postOnly) {}

    public function index(Request $req): Response
    {
        $rows = $this->buyers->all();
        foreach ($rows as &$r) {
            $r['stats'] = $this->buyers->stats((int)$r['id']);
        }
        return View::render('buyers.index', ['buyers' => $rows, 'user' => $req->user]);
    }

    public function create(Request $req): Response
    {
        return View::render('buyers.form', ['buyer' => null, 'user' => $req->user]);
    }

    public function store(Request $req): Response
    {
        try {
            $id = $this->buyers->create($this->parseForm($req));
        } catch (\Throwable $e) {
            return View::render('buyers.form', ['buyer' => null, 'error' => $e->getMessage(), 'user' => $req->user]);
        }
        return Response::redirect('/buyers/' . $id);
    }

    public function show(Request $req, ?array $testResult = null, ?string $error = null): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::redirect('/buyers');
        $b['stats'] = $this->buyers->stats((int)$b['id']);
        $b['counts'] = $this->buyers->counts((int)$b['id']);
        return View::render('buyers.form', ['buyer' => $b, 'testResult' => $testResult, 'error' => $error, 'user' => $req->user]);
    }

    public function update(Request $req): Response
    {
        try {
            $this->buyers->update((int)$req->route('id'), $this->parseForm($req));
        } catch (\Throwable $e) {
            return $this->show($req, null, $e->getMessage());
        }
        return Response::redirect('/buyers/' . $req->route('id'));
    }

    /** Post-only buyer integration test (Round Sky test URL): declined / approved sample lead. */
    public function testPostOnly(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::redirect('/buyers');
        if (($b['integration_type'] ?? '') !== 'post_only') return $this->show($req, null, 'Test is only for Post Only buyers.');
        if (empty($b['post_credentials']['partner'])) return $this->show($req, null, 'Save Partner ID and Partner Password first.');
        $kind = ($req->body['kind'] ?? '') === 'approved' ? 'approved' : 'declined';
        $result = $this->postOnly->runTest($b, $kind);
        $result['kind'] = $kind;
        return $this->show($req, $result);
    }

    public function toggle(Request $req): Response
    {
        $this->buyers->toggleActive((int)$req->route('id'));
        return Response::redirect('/buyers');
    }

    private function parseForm(Request $req): array
    {
        $b = $req->body;
        $type = ($b['integration_type'] ?? 'ping_post') === 'post_only' ? 'post_only' : 'ping_post';
        $out = [
            'integration_type' => $type,
            'name' => $b['name'] ?? '',
            'active' => (int)($b['active'] ?? 0),
            'ping_url' => $b['ping_url'] ?? '',
            'post_url' => $b['post_url'] ?? '',
            'ping_method' => $b['ping_method'] ?? 'POST',
            'post_method' => $b['post_method'] ?? 'POST',
            'request_format' => $b['request_format'] ?? 'json',
            'timeout_ms' => (int)($b['timeout_ms'] ?? 3000),
            'priority' => (int)($b['priority'] ?? 100),
            'weight' => (int)($b['weight'] ?? 1),
            'daily_cap'  => $b['daily_cap']  !== '' ? (int)$b['daily_cap']  : null,
            'hourly_cap' => $b['hourly_cap'] !== '' ? (int)$b['hourly_cap'] : null,
            'monthly_cap'=> $b['monthly_cap']!== '' ? (int)$b['monthly_cap']: null,
            'total_cap'  => $b['total_cap']  !== '' ? (int)$b['total_cap']  : null,
        ];
        foreach (['credentials','headers','field_map','transformations','response_rules','schedule','rules'] as $k) {
            if (!empty($b[$k . '_json'])) {
                $decoded = json_decode($b[$k . '_json'], true);
                $out[$k] = is_array($decoded) ? $decoded : [];
            }
        }
        if ($type === 'post_only') {
            $out['ping_url'] = '';
            if ($out['post_url'] === '') throw new \InvalidArgumentException('Live Post URL is required.');
            $list = fn($k) => array_values(array_filter(array_map('trim', explode(',', (string)($b[$k] ?? '')))));
            $tiers = array_values(array_filter($list('po_price_tiers'), 'is_numeric'));
            $jsonObj = function (string $key, string $label) use ($b): array {
                $rawJson = trim((string)($b[$key] ?? ''));
                if ($rawJson === '') return [];
                $d = json_decode($rawJson, true);
                if (!is_array($d)) throw new \InvalidArgumentException($label . ' is not valid JSON.');
                return $d;
            };
            $out['field_map'] = $jsonObj('po_field_map_json', 'Field Mapping');
            $valueMap = $jsonObj('po_value_map_json', 'Value Mapping');
            $out['post_config'] = [
                'format' => 'roundsky',
                'value_map' => $valueMap,
                'test_mode' => (int)($b['po_test_mode'] ?? 1),
                'test_url' => trim((string)($b['po_test_url'] ?? '')),
                'sub_id' => trim((string)($b['po_sub_id'] ?? '')),
                'domain' => trim((string)($b['po_domain'] ?? '')),
                'time_allowed' => max(20, (int)($b['po_time_allowed'] ?? 20)),
                'total_budget_s' => max(20, (int)($b['po_total_budget_s'] ?? 45)),
                'price_tiers' => $tiers ? array_map('floatval', $tiers) : PostOnlyEngine::DEFAULT_TIERS,
                'filters' => [
                    'account_types' => array_map('strtolower', $list('po_f_account_types')),
                    'exclude_military' => !empty($b['po_f_exclude_military']),
                    'excluded_states' => array_map('strtoupper', $list('po_f_excluded_states')),
                    'min_age' => (int)($b['po_f_min_age'] ?? 20),
                    'max_age' => (int)($b['po_f_max_age'] ?? 80),
                    'min_income' => (float)($b['po_f_min_income'] ?? 1200),
                    'max_income' => (float)($b['po_f_max_income'] ?? 10000),
                    'work_phone_not_home_phone' => !empty($b['po_f_work_phone_not_home_phone']),
                ],
            ];
            $partner = trim((string)($b['po_partner'] ?? ''));
            $password = trim((string)($b['po_partner_password'] ?? ''));
            if ($partner !== '' || $password !== '') {
                if ($partner === '' || $password === '') {
                    throw new \InvalidArgumentException('Enter both Partner ID and Partner Password (or leave both blank to keep saved ones).');
                }
                $out['post_credentials'] = ['partner' => $partner, 'partner_password' => $password];
            }
        } elseif ($out['ping_url'] === '' || $out['post_url'] === '') {
            throw new \InvalidArgumentException('Ping URL and Post URL are required for Ping + Post buyers.');
        }
        return $out;
    }
}
