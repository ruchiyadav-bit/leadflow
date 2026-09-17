<?php use LeadFlow\Core\View; /** @var ?array $offer */ /** @var string $baseUrl */
$o = $offer ?? [];
$action = $offer ? '/offers/' . (int)$o['id'] : '/offers';
$title = $offer ? 'Edit Offer' : 'New Offer';
$mode = $o['delivery_mode'] ?? 's2s';
$clicks = $clicks ?? [];
$postbacks = $postbacks ?? [];
?>
<h2><?= View::e($title) ?></h2>
<form method="post" action="<?= View::e($action) ?>" class="card">
<div class="form-grid">
  <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($o['name'] ?? '') ?>" required></div>
  <div class="form-row"><label>Network</label><input name="network" value="<?= View::e($o['network'] ?? '') ?>" placeholder="e.g. exltrk"></div>
  <div class="form-row"><label>Delivery Mode</label>
    <select name="delivery_mode">
      <option value="s2s" <?= $mode === 's2s' ? 'selected' : '' ?>>S2S Dashboard (network sends postback)</option>
      <option value="direct" <?= $mode === 'direct' ? 'selected' : '' ?>>Affiliate Direct (link only, stats in network panel)</option>
    </select>
  </div>
  <div class="form-row"><label>Active</label>
    <select name="active"><option value="1" <?= ($o['active'] ?? 1) ? 'selected' : '' ?>>Active</option><option value="0" <?= ($offer && empty($o['active'])) ? 'selected' : '' ?>>Inactive</option></select>
  </div>
  <div class="form-row" style="grid-column:1/-1"><label>Affiliate Tracking URL</label>
    <input name="tracking_url" value="<?= View::e($o['tracking_url'] ?? '') ?>" required placeholder="https://network.com/click?offer=123&amp;sub1={click_id}&amp;sub2={lead_id}">
    <div class="muted" style="margin-top:4px">Placeholders: <code>{click_id}</code> (required for S2S — put it in the network's sub/aff_sub param), <code>{lead_id}</code>, <code>{sub_id}</code></div>
  </div>
  <div class="form-row"><label>Default Payout ($)</label><input name="default_payout" type="number" step="0.0001" value="<?= View::e($o['default_payout'] ?? '0') ?>"></div>
  <div class="form-row"><label>Notes</label><input name="notes" value="<?= View::e($o['notes'] ?? '') ?>"></div>
</div>
<button class="btn">Save</button> <a href="/offers" class="btn btn-secondary">Back</a>
</form>

<?php if ($offer): $id = (int)$o['id']; ?>
<div class="card">
  <h3>Links</h3>
  <div class="form-row"><label>Click link (use on lander / thank-you page)</label>
    <pre><?= View::e($baseUrl . '/go/' . $id . '?lead_id=LEAD_ID&sub_id=OPTIONAL') ?></pre>
    <div class="muted">Or send <code>"offer_id": <?= $id ?></code> with the lead POST — the response returns a ready <code>redirect_url</code>.</div>
  </div>
  <?php if ($mode === 's2s'): ?>
  <div class="form-row"><label>Postback URL (paste in network panel)</label>
    <pre><?= View::e($baseUrl . '/postback/' . $id . '?key=' . $o['postback_key'] . '&click_id={NETWORK_SUB1_MACRO}&payout={NETWORK_PAYOUT_MACRO}&txid={NETWORK_TXN_MACRO}') ?></pre>
    <div class="muted">Replace <code>{NETWORK_..._MACRO}</code> with the network's own macros. If payout is not sent, Default Payout is used.</div>
  </div>
  <?php else: ?>
  <p class="muted">Affiliate Direct: conversions and payouts are tracked in the network's own panel. LeadFlow records clicks only.</p>
  <?php endif; ?>
</div>

<div class="card">
<h3>Recent Clicks</h3>
<table>
  <thead><tr><th>Time</th><th>Click ID</th><th>Lead</th><th>Sub ID</th><?php if ($mode === 's2s'): ?><th>Converted</th><th>Payout</th><th>Txn</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($clicks as $c): ?>
    <tr>
      <td><?= View::e($c['created_at']) ?></td>
      <td><code><?= View::e($c['click_id']) ?></code></td>
      <td><?= $c['lead_ref'] ? '<a href="/leads/' . View::e($c['lead_ref']) . '">' . View::e($c['lead_ref']) . '</a>' : '-' ?></td>
      <td><?= View::e($c['sub_id'] ?? '-') ?></td>
      <?php if ($mode === 's2s'): ?>
      <td><?= (int)$c['converted'] ? '<span class="badge badge-ok">YES</span>' : '<span class="badge badge-warn">NO</span>' ?></td>
      <td><?= $c['payout'] !== null ? '$' . number_format((float)$c['payout'], 2) : '-' ?></td>
      <td><?= View::e($c['conversion_txid'] ?? '-') ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$clicks): ?><tr><td colspan="7" class="muted">No clicks yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?php if ($mode === 's2s'): ?>
<div class="card">
<h3>Postback Log</h3>
<table>
  <thead><tr><th>Time</th><th>Result</th><th>Click ID</th><th>IP</th><th>Query</th></tr></thead>
  <tbody>
  <?php foreach ($postbacks as $p): ?>
    <tr>
      <td><?= View::e($p['created_at']) ?></td>
      <td><span class="badge <?= $p['result'] === 'converted' ? 'badge-ok' : 'badge-err' ?>"><?= View::e(strtoupper($p['result'])) ?></span></td>
      <td><code><?= View::e($p['click_id'] ?? '-') ?></code></td>
      <td><?= View::e($p['ip_address'] ?? '') ?></td>
      <td class="muted"><?= View::e(preg_replace('/key=[^&]*/', 'key=***', (string)$p['query_string'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$postbacks): ?><tr><td colspan="5" class="muted">No postbacks received yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
