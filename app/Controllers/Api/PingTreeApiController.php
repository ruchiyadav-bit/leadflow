<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\Database;
use LeadFlow\Services\PingTreeService;

final class PingTreeApiController
{
    private PingTreeService $svc;
    public function __construct(Database $db) { $this->svc = new PingTreeService($db); }

    public function index(Request $req): Response { return Response::json(['data' => $this->svc->all()]); }
    public function show(Request $req): Response { $t = $this->svc->find((int)$req->route('id')); return $t ? Response::json($t) : Response::json(['error'=>'not_found'], 404); }
    public function store(Request $req): Response { $id = $this->svc->create($req->all()); return Response::json(['id' => $id], 201); }
    public function update(Request $req): Response { $this->svc->update((int)$req->route('id'), $req->all()); return Response::json(['ok' => true]); }
}
