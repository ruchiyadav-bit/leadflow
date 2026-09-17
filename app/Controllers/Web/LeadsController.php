<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Web;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Core\View;
use LeadFlow\Repositories\LeadRepository;
use LeadFlow\Repositories\PingRepository;
use LeadFlow\Repositories\OfferRepository;
use LeadFlow\DirectPost\DirectPostBuyerRepository;

final class LeadsController
{
    public function __construct(private LeadRepository $leads, private PingRepository $pings, private OfferRepository $offers, private DirectPostBuyerRepository $directBuyers) {}

    public function index(Request $req): Response
    {
        $page = max(1, (int)($req->query['page'] ?? 1));
        $result = $this->leads->search($req->query, $page, 50);
        return View::render('leads.index', ['result' => $result, 'filters' => $req->query, 'user' => $req->user]);
    }

    public function show(Request $req): Response
    {
        $lead = $this->leads->findByLeadId((string)$req->route('id'));
        if (!$lead) return Response::redirect('/leads');
        return View::render('leads.show', [
            'lead' => $lead,
            'history' => $this->leads->statusHistory((int)$lead['id']),
            'pings' => $this->pings->pingsForLead((int)$lead['id']),
            'posts' => $this->pings->postsForLead((int)$lead['id']),
            'offerClicks' => $this->offers->clicksForLead((int)$lead['id']),
            'directPosts' => $this->directBuyers->attemptsForLead((int)$lead['id']),
            'user' => $req->user,
        ]);
    }
}
