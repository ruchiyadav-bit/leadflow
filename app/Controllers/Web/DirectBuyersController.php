<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\DirectPost\DirectPostBuyerRepository;
use LeadFlow\DirectPost\DirectPostEngine;

final class DirectBuyersController
{
    public function __construct(private DirectPostBuyerRepository $buyers, private DirectPostEngine $engine) {}

    public function index(Request $req): Response
    {
        return View::render('direct_buyers.index', ['buyers' => $this->buyers->all(), 'user' => $req->user]);
    }

    public function create(Request $req): Response
    {
        return View::render('direct_buyers.form', ['buyer' => null, 'user' => $req->user]);
    }

    public function store(Request $req): Response
    {
        try {
            $id = $this->buyers->save(null, $this->parseForm($req), $this->parseCredentials($req, true));
        } catch (\Throwable $e) {
            return View::render('direct_buyers.form', ['buyer' => null, 'error' => $e->getMessage(), 'user' => $req->user]);
        }
        return Response::redirect('/direct-buyers/' . $id);
    }

    public function show(Request $req, ?array $testResult = null, ?string $error = null): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::redirect('/direct-buyers');
        return View::render('direct_buyers.form', [
            'buyer' => $b,
            'attempts' => $this->buyers->attemptsForBuyer((int)$b['id']),
            'testResult' => $testResult,
            'error' => $error,
            'user' => $req->user,
        ]);
    }

    public function update(Request $req): Response
    {
        $id = (int)$req->route('id');
        try {
            $this->buyers->save($id, $this->parseForm($req), $this->parseCredentials($req, false));
        } catch (\Throwable $e) {
            return $this->show($req, null, $e->getMessage());
        }
        return Response::redirect('/direct-buyers/' . $id);
    }

    public function toggle(Request $req): Response
    {
        $this->buyers->toggleActive((int)$req->route('id'));
        return Response::redirect('/direct-buyers');
    }

    public function test(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::redirect('/direct-buyers');
        if (empty($b['credentials']['partner'])) {
            return $this->show($req, null, 'Save partner ID and password first.');
        }
        $kind = ($req->body['kind'] ?? '') === 'approved' ? 'approved' : 'declined';
        $result = $this->engine->runTest($b, $kind);
        $result['kind'] = $kind;
        return $this->show($req, $result);
    }

    private function parseForm(Request $req): array
    {
        $b = $req->body;
        $tiers = array_values(array_filter(array_map('trim', explode(',', (string)($b['price_tiers'] ?? ''))), 'is_numeric'));
        $list = fn($k) => array_values(array_filter(array_map('trim', explode(',', (string)($b[$k] ?? '')))));
        return [
            'name' => trim((string)($b['name'] ?? 'Round Sky')),
            'active' => (int)($b['active'] ?? 0),
            'test_mode' => (int)($b['test_mode'] ?? 1),
            'test_url' => trim((string)($b['test_url'] ?? '')),
            'live_url' => trim((string)($b['live_url'] ?? '')),
            'sub_id' => trim((string)($b['sub_id'] ?? '')),
            'domain' => trim((string)($b['domain'] ?? '')),
            'time_allowed' => (int)($b['time_allowed'] ?? 20),
            'total_budget_s' => (int)($b['total_budget_s'] ?? 45),
            'price_tiers' => array_map('floatval', $tiers ?: DirectPostBuyerRepository::DEFAULT_TIERS),
            'priority' => (int)($b['priority'] ?? 100),
            'daily_cap' => ($b['daily_cap'] ?? '') !== '' ? (int)$b['daily_cap'] : null,
            'filters' => [
                'account_types' => array_map('strtolower', $list('f_account_types')),
                'exclude_military' => !empty($b['f_exclude_military']),
                'excluded_states' => array_map('strtoupper', $list('f_excluded_states')),
                'min_age' => (int)($b['f_min_age'] ?? 20),
                'max_age' => (int)($b['f_max_age'] ?? 80),
                'min_income' => (float)($b['f_min_income'] ?? 1200),
                'max_income' => (float)($b['f_max_income'] ?? 10000),
                'work_phone_not_home_phone' => !empty($b['f_work_phone_not_home_phone']),
            ],
        ];
    }

    /** Returns null to keep existing credentials when both fields are left blank on edit. */
    private function parseCredentials(Request $req, bool $isNew): ?array
    {
        $partner = trim((string)($req->body['partner'] ?? ''));
        $password = trim((string)($req->body['partner_password'] ?? ''));
        if ($partner === '' && $password === '') return $isNew ? null : null;
        if ($partner === '' || $password === '') {
            throw new \InvalidArgumentException('Enter both Partner ID and Partner Password (or leave both blank to keep saved ones).');
        }
        return ['partner' => $partner, 'partner_password' => $password];
    }
}
