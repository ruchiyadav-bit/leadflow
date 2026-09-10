<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Repositories\BuyerRepository;

final class BuyersController
{
    public function __construct(private BuyerRepository $buyers) {}

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
        $data = $this->parseForm($req);
        $id = $this->buyers->create($data);
        return Response::redirect('/buyers/' . $id);
    }

    public function show(Request $req): Response
    {
        $b = $this->buyers->findById((int)$req->route('id'));
        if (!$b) return Response::redirect('/buyers');
        $b['stats'] = $this->buyers->stats((int)$b['id']);
        $b['counts'] = $this->buyers->counts((int)$b['id']);
        return View::render('buyers.form', ['buyer' => $b, 'user' => $req->user]);
    }

    public function update(Request $req): Response
    {
        $data = $this->parseForm($req);
        $this->buyers->update((int)$req->route('id'), $data);
        return Response::redirect('/buyers/' . $req->route('id'));
    }

    public function toggle(Request $req): Response
    {
        $this->buyers->toggleActive((int)$req->route('id'));
        return Response::redirect('/buyers');
    }

    private function parseForm(Request $req): array
    {
        $b = $req->body;
        $out = [
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
        return $out;
    }
}
