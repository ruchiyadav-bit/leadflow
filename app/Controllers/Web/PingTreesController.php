<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Services\PingTreeService;
use LeadFlow\Repositories\BuyerRepository;

final class PingTreesController
{
    public function __construct(private PingTreeService $svc, private BuyerRepository $buyers) {}

    public function index(Request $req): Response
    {
        return View::render('reports.pingtrees_index', ['trees' => $this->svc->all(), 'user' => $req->user]);
    }
    public function create(Request $req): Response
    {
        return View::render('reports.pingtree_form', ['tree' => null, 'buyers' => $this->buyers->all(), 'user' => $req->user]);
    }
    public function store(Request $req): Response
    {
        $id = $this->svc->create($this->parse($req));
        return Response::redirect('/ping-trees/' . $id);
    }
    public function show(Request $req): Response
    {
        $t = $this->svc->find((int)$req->route('id'));
        if (!$t) return Response::redirect('/ping-trees');
        return View::render('reports.pingtree_form', ['tree' => $t, 'buyers' => $this->buyers->all(), 'user' => $req->user]);
    }
    public function update(Request $req): Response
    {
        $this->svc->update((int)$req->route('id'), $this->parse($req));
        return Response::redirect('/ping-trees/' . $req->route('id'));
    }
    private function parse(Request $req): array
    {
        $b = $req->body;
        return [
            'name' => $b['name'] ?? '',
            'active' => (int)($b['active'] ?? 0),
            'routing_mode' => $b['routing_mode'] ?? 'highest_bid',
            'min_bid' => (float)($b['min_bid'] ?? 0),
            'allow_fallback' => (int)($b['allow_fallback'] ?? 0),
            'max_wait_ms' => (int)($b['max_wait_ms'] ?? 5000),
            'max_buyers' => (int)($b['max_buyers'] ?? 25),
            'source_id' => $b['source_id'] !== '' ? (int)$b['source_id'] : null,
            'campaign_id' => $b['campaign_id'] !== '' ? (int)$b['campaign_id'] : null,
            'buyer_ids' => array_map('intval', $b['buyer_ids'] ?? []),
            'fallback_ids' => array_map('intval', $b['fallback_ids'] ?? []),
        ];
    }
}
