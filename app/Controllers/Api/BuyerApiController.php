<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Repositories\BuyerRepository;
use LeadFlow\PingEngine\PingEngine;

final class BuyerApiController
{
    public function __construct(private BuyerRepository $buyers, private PingEngine $ping) {}

    public function index(Request $req): Response
    {
        $rows = $this->buyers->all();
        foreach ($rows as &$r) unset($r['credentials_json'], $r['headers_json']);
        return Response::json(['data' => $rows]);
    }
    public function show(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        return $b ? Response::json($b) : Response::json(['error' => 'not_found'], 404);
    }
    public function store(Request $req): Response
    {
        $id = $this->buyers->create($req->all());
        return Response::json(['id' => $id], 201);
    }
    public function update(Request $req): Response
    {
        $this->buyers->update((int)$req->route('id'), $req->all());
        return Response::json(['ok' => true]);
    }
    public function addRule(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::json(['error' => 'not_found'], 404);
        $rules = json_decode($b['rules_json'] ?? '{"op":"AND","rules":[]}', true);
        $rules['rules'][] = $req->all();
        $this->buyers->update((int)$b['id'], ['rules' => $rules]);
        return Response::json(['ok' => true, 'rules' => $rules]);
    }
    public function testPing(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::json(['error' => 'not_found'], 404);
        $lead = array_merge([
            'id' => 0, 'lead_id' => 'TEST-' . bin2hex(random_bytes(4)),
            'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com',
            'phone' => '5555550100', 'state' => 'TX', 'zip' => '75001',
            'monthly_income' => 3000, 'loan_amount' => 500,
        ], $req->body['lead'] ?? []);
        $results = $this->ping->pingAll($lead, [$b], (int)($b['timeout_ms'] ?? 3000));
        return Response::json(['test_mode' => true, 'result' => $results[0] ?? null]);
    }
}
