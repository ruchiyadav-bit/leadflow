<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Repositories\OfferRepository;

final class OffersController
{
    public function __construct(private OfferRepository $offers) {}

    public function index(Request $req): Response
    {
        return View::render('offers.index', ['offers' => $this->offers->all(), 'user' => $req->user]);
    }

    public function create(Request $req): Response
    {
        return View::render('offers.form', ['offer' => null, 'user' => $req->user, 'baseUrl' => $this->baseUrl($req)]);
    }

    public function store(Request $req): Response
    {
        $id = $this->offers->create($this->parseForm($req));
        return Response::redirect('/offers/' . $id);
    }

    public function show(Request $req): Response
    {
        $o = $this->offers->findById((int)$req->route('id'));
        if (!$o) return Response::redirect('/offers');
        return View::render('offers.form', [
            'offer' => $o,
            'clicks' => $this->offers->recentClicks((int)$o['id']),
            'postbacks' => $this->offers->recentPostbacks((int)$o['id']),
            'user' => $req->user,
            'baseUrl' => $this->baseUrl($req),
        ]);
    }

    public function update(Request $req): Response
    {
        $this->offers->update((int)$req->route('id'), $this->parseForm($req));
        return Response::redirect('/offers/' . $req->route('id'));
    }

    public function toggle(Request $req): Response
    {
        $this->offers->toggleActive((int)$req->route('id'));
        return Response::redirect('/offers');
    }

    private function parseForm(Request $req): array
    {
        $b = $req->body;
        return [
            'name' => trim((string)($b['name'] ?? '')),
            'network' => trim((string)($b['network'] ?? '')) ?: null,
            'delivery_mode' => ($b['delivery_mode'] ?? 's2s') === 'direct' ? 'direct' : 's2s',
            'tracking_url' => trim((string)($b['tracking_url'] ?? '')),
            'default_payout' => (float)($b['default_payout'] ?? 0),
            'active' => (int)($b['active'] ?? 0),
            'notes' => trim((string)($b['notes'] ?? '')) ?: null,
        ];
    }

    private function baseUrl(Request $req): string
    {
        $proto = $req->header('x-forwarded-proto') ?? (!empty($req->server['HTTPS']) ? 'https' : 'http');
        $host = $req->header('host') ?? 'localhost';
        return $proto . '://' . $host;
    }
}
