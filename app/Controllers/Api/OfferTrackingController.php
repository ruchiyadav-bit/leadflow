<?php
declare(strict_types=1);

namespace LeadFlow\Controllers\Api;

use LeadFlow\Core\Request;
use LeadFlow\Core\Response;
use LeadFlow\Repositories\LeadRepository;
use LeadFlow\Repositories\OfferRepository;

/**
 * Public tracking endpoints for affiliate offers.
 *
 *  GET /go/{offer_id}?lead_id=LD-XXXX&sub_id=...   -> records click, 302 to network tracking URL
 *  GET /postback/{offer_id}?key=...&click_id=...&payout=...&txid=...   -> S2S conversion (s2s offers only)
 */
final class OfferTrackingController
{
    public function __construct(private OfferRepository $offers, private LeadRepository $leads) {}

    public function click(Request $req): Response
    {
        $offer = $this->offers->findById((int)$req->route('id'));
        if (!$offer || !(int)$offer['active']) {
            return Response::text('Offer not available', 404);
        }

        $leadRowId = null;
        $leadRef = (string)($req->query['lead_id'] ?? '');
        if ($leadRef !== '') {
            $lead = $this->leads->findByLeadId($leadRef);
            $leadRowId = $lead ? (int)$lead['id'] : null;
        }
        $subId = isset($req->query['sub_id']) ? substr((string)$req->query['sub_id'], 0, 120) : null;

        $clickId = $this->offers->createClick((int)$offer['id'], $leadRowId, $subId, $req->ip(), $req->header('user-agent'));

        $url = strtr((string)$offer['tracking_url'], [
            '{click_id}' => rawurlencode($clickId),
            '{lead_id}'  => rawurlencode($leadRef),
            '{sub_id}'   => rawurlencode((string)$subId),
        ]);
        return Response::redirect($url);
    }

    public function postback(Request $req): Response
    {
        $offerId = (int)$req->route('id');
        $q = $req->query;
        $clickId = isset($q['click_id']) ? (string)$q['click_id'] : null;
        $qs = (string)($req->server['QUERY_STRING'] ?? http_build_query($q));
        $ip = $req->ip();

        $offer = $this->offers->findById($offerId);
        if (!$offer) {
            $this->offers->logPostback(null, $clickId, 'unknown_offer', $qs, $ip);
            return Response::json(['status' => 'error', 'error' => 'unknown_offer'], 404);
        }
        if ($offer['delivery_mode'] !== 's2s') {
            $this->offers->logPostback($offerId, $clickId, 'not_s2s', $qs, $ip);
            return Response::json(['status' => 'error', 'error' => 'offer_is_affiliate_direct'], 400);
        }
        if (!hash_equals((string)$offer['postback_key'], (string)($q['key'] ?? ''))) {
            $this->offers->logPostback($offerId, $clickId, 'bad_key', $qs, $ip);
            return Response::json(['status' => 'error', 'error' => 'invalid_key'], 403);
        }
        if ($clickId === null || $clickId === '') {
            $this->offers->logPostback($offerId, null, 'missing_click', $qs, $ip);
            return Response::json(['status' => 'error', 'error' => 'missing_click_id'], 422);
        }

        $click = $this->offers->findClick($clickId);
        if (!$click || (int)$click['offer_id'] !== $offerId) {
            $this->offers->logPostback($offerId, $clickId, 'unknown_click', $qs, $ip);
            return Response::json(['status' => 'error', 'error' => 'unknown_click_id'], 404);
        }
        if ((int)$click['converted']) {
            $this->offers->logPostback($offerId, $clickId, 'duplicate', $qs, $ip);
            return Response::json(['status' => 'ok', 'duplicate' => true]);
        }

        $payout = (isset($q['payout']) && is_numeric($q['payout'])) ? (float)$q['payout'] : (float)$offer['default_payout'];
        $txid = isset($q['txid']) ? substr((string)$q['txid'], 0, 128) : null;

        $this->offers->markConverted((int)$click['id'], $payout, $txid);
        $this->offers->logPostback($offerId, $clickId, 'converted', $qs, $ip);

        return Response::json(['status' => 'ok', 'click_id' => $clickId, 'payout' => $payout]);
    }
}
