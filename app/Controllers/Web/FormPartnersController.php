<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Repositories\FormPartnerRepository;

/** Form Partners (hidden admin area): super_admin / admin only. */
final class FormPartnersController
{
    private const ROLES = ['super_admin', 'admin'];

    public function __construct(private FormPartnerRepository $partners) {}

    private function denied(Request $req): ?Response
    {
        return in_array($req->user['role'] ?? '', self::ROLES, true) ? null : Response::redirect('/dashboard');
    }

    public function index(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        return View::render('form_partners.index', ['partners' => $this->partners->all(), 'user' => $req->user]);
    }

    public function create(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        return View::render('form_partners.form', ['partner' => null, 'error' => null, 'user' => $req->user]);
    }

    public function store(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        [$data, $error] = $this->parseForm($req);
        if ($error) return View::render('form_partners.form', ['partner' => $data, 'error' => $error, 'user' => $req->user]);
        $id = $this->partners->create($data);
        return Response::redirect('/form-partners/' . $id);
    }

    public function show(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        $p = $this->partners->findById((int)$req->route('id'));
        if (!$p) return Response::redirect('/form-partners');
        return View::render('form_partners.form', ['partner' => $p, 'error' => null, 'user' => $req->user]);
    }

    public function update(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        $id = (int)$req->route('id');
        [$data, $error] = $this->parseForm($req);
        if ($error) return View::render('form_partners.form', ['partner' => $data + ['id' => $id], 'error' => $error, 'user' => $req->user]);
        $this->partners->update($id, $data);
        return Response::redirect('/form-partners/' . $id);
    }

    public function toggle(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        $this->partners->toggleActive((int)$req->route('id'));
        return Response::redirect('/form-partners');
    }

    public function log(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        $page = max(1, (int)($req->query['page'] ?? 1));
        return View::render('form_partners.log', [
            'partners' => $this->partners->all(),
            'rows' => $this->partners->log($page),
            'page' => $page,
            'user' => $req->user,
        ]);
    }

    public function retry(Request $req): Response
    {
        if ($r = $this->denied($req)) return $r;
        $this->partners->retry((int)$req->route('id'));
        return Response::redirect('/form-partners/log');
    }

    /** @return array{0: array, 1: ?string} */
    private function parseForm(Request $req): array
    {
        $b = $req->body;
        $stepsRaw = trim((string)($b['steps_json'] ?? ''));
        $successRaw = trim((string)($b['success_json'] ?? ''));
        $data = [
            'name' => trim((string)($b['name'] ?? '')),
            'form_url' => trim((string)($b['form_url'] ?? '')),
            'active' => (int)($b['active'] ?? 0),
            'sort_order' => (int)($b['sort_order'] ?? 100),
            'steps_json' => $stepsRaw,
            'success_json' => $successRaw !== '' ? $successRaw : null,
            'timeout_ms' => max(5000, (int)($b['timeout_ms'] ?? 45000)),
            'max_retries' => max(0, min(10, (int)($b['max_retries'] ?? 2))),
            'notes' => trim((string)($b['notes'] ?? '')) ?: null,
        ];
        if ($data['name'] === '') return [$data, 'Name is required'];
        if (!filter_var($data['form_url'], FILTER_VALIDATE_URL)) return [$data, 'Form URL is not a valid URL'];
        $steps = json_decode($stepsRaw, true);
        if (!is_array($steps) || !array_is_list($steps) || !$steps) return [$data, 'Steps must be a non-empty JSON array'];
        foreach ($steps as $i => $s) {
            if (!is_array($s) || !in_array($s['action'] ?? '', ['fill', 'select', 'check', 'click', 'wait', 'wait_for', 'goto'], true)) {
                return [$data, 'Step ' . ($i + 1) . ': unknown action'];
            }
        }
        if ($successRaw !== '' && !is_array(json_decode($successRaw, true))) return [$data, 'Success rule must be a JSON object'];
        return [$data, null];
    }
}
