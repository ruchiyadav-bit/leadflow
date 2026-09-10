<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Validators\LeadValidator;
use LeadFlow\Repositories\LeadRepository;
use LeadFlow\Repositories\PingRepository;
use LeadFlow\Services\LeadOrchestrator;
use LeadFlow\Services\PingTreeService;
use LeadFlow\Core\Database;

final class LeadApiController
{
    public function __construct(
        private LeadRepository $leads,
        private PingRepository $pings,
        private LeadOrchestrator $orchestrator,
        private Database $db,
    ) {}

    public function create(Request $req): Response
    {
        $data = $req->all();
        $validator = new LeadValidator();
        $result = $validator->validate($data);
        if (!empty($result['errors'])) {
            return Response::json(['error' => 'validation_failed', 'errors' => $result['errors']], 422);
        }

        $core = $result['normalized'];

        // Duplicate detection (default 30-day window)
        $dup = $this->leads->findDuplicate((string)$core['email'], (string)$core['phone'], 30);
        if ($dup) {
            return Response::json([
                'status' => 'duplicate',
                'lead_id' => $dup['lead_id'],
                'message' => 'Duplicate lead detected within 30 days',
            ], 200);
        }

        // Attribution
        $source = $req->user['source'] ?? null;
        $campaignId = null;
        if (!empty($data['campaign_key'])) {
            $c = $this->db->one('SELECT id FROM campaigns WHERE campaign_key = :k', ['k' => $data['campaign_key']]);
            $campaignId = $c ? (int)$c['id'] : null;
        }
        $attribution = [
            'source_id' => $source['id'] ?? null,
            'campaign_id' => $campaignId,
            'sub_id' => $data['sub_id'] ?? null,
            'fb_campaign_id' => $data['fb_campaign_id'] ?? ($data['fbc'] ?? null),
            'fb_adset_id' => $data['fb_adset_id'] ?? null,
            'fb_ad_id' => $data['fb_ad_id'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
        ];
        $consent = [
            'trustedform_cert' => $data['trustedform_cert'] ?? ($data['xxTrustedFormCertUrl'] ?? null),
            'jornaya_lead_id'  => $data['jornaya_lead_id']  ?? ($data['leadid_token'] ?? null),
            'consent_version'  => $data['consent_version'] ?? '1.0',
            'disclosure_version' => $data['disclosure_version'] ?? '1.0',
            'privacy_version'  => $data['privacy_version']  ?? '1.0',
            'terms_version'    => $data['terms_version']    ?? '1.0',
            'landing_url'      => $data['landing_url']      ?? ($req->header('referer')),
            'ip_address'       => $req->ip(),
            'user_agent'       => $req->header('user-agent'),
            'consent_timestamp'=> $data['consent_timestamp'] ?? null,
        ];

        // Custom fields = anything not in known keys
        $known = array_merge(LeadValidator::REQUIRED, [
            'address','city','date_of_birth','employment_status','monthly_income','pay_frequency','loan_amount',
            'sub_id','campaign_key','fb_campaign_id','fb_adset_id','fb_ad_id','fbc','fbp',
            'utm_source','utm_medium','utm_campaign','utm_content','api_key',
            'trustedform_cert','xxTrustedFormCertUrl','jornaya_lead_id','leadid_token',
            'consent_version','disclosure_version','privacy_version','terms_version','landing_url','consent_timestamp',
        ]);
        $custom = array_diff_key($data, array_flip($known));

        $lead = $this->leads->create($core, $custom, $consent, $attribution);

        // Choose ping tree and process synchronously (low latency)
        $treeService = new PingTreeService($this->db);
        $tree = $treeService->findForLead($lead);
        $outcome = $this->orchestrator->process($lead, $tree);

        return Response::json([
            'lead_id' => $lead['lead_id'],
            'outcome' => $outcome,
        ], 201);
    }

    public function index(Request $req): Response
    {
        return Response::json($this->leads->search($req->all(), (int)($req->query['page'] ?? 1), (int)($req->query['limit'] ?? 50)));
    }

    public function show(Request $req): Response
    {
        $lead = $this->leads->findByLeadId((string)$req->route('id'));
        if (!$lead) return Response::json(['error' => 'not_found'], 404);
        return Response::json($lead);
    }

    public function journey(Request $req): Response
    {
        $lead = $this->leads->findByLeadId((string)$req->route('id'));
        if (!$lead) return Response::json(['error' => 'not_found'], 404);
        return Response::json([
            'lead' => $lead,
            'status_history' => $this->leads->statusHistory((int)$lead['id']),
            'pings' => $this->pings->pingsForLead((int)$lead['id']),
            'posts' => $this->pings->postsForLead((int)$lead['id']),
        ]);
    }
}
