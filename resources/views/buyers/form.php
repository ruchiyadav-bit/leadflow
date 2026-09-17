<?php use LeadFlow\Core\View; /** @var ?array $buyer */
$b = $buyer ?? [];
$action = $buyer ? '/buyers/' . (int)$b['id'] : '/buyers';
$title = $buyer ? 'Edit Buyer' : 'New Buyer';
$type = $b['integration_type'] ?? 'ping_post';
$pc = \LeadFlow\DirectPost\PostOnlyEngine::config($b);
$hasCreds = !empty($b['post_credentials']['partner']);
$testResult = $testResult ?? null;
$json = fn($k, $default = '{}') => htmlspecialchars(json_encode($b[$k] ?? json_decode($default, true), JSON_PRETTY_PRINT), ENT_QUOTES);
?>
<h2><?= View::e($title) ?></h2>
<?php if ($buyer): $counts = $b['counts']; ?>
<div class="grid" style="margin-bottom:16px">
  <div class="stat"><div class="lbl">Today</div><div class="val"><?= $counts['today'] ?><?= !empty($b['daily_cap']) ? ' / ' . $b['daily_cap'] : '' ?></div></div>
  <div class="stat"><div class="lbl">Hour</div><div class="val"><?= $counts['hour'] ?><?= !empty($b['hourly_cap']) ? ' / ' . $b['hourly_cap'] : '' ?></div></div>
  <div class="stat"><div class="lbl">Month</div><div class="val"><?= $counts['month'] ?><?= !empty($b['monthly_cap']) ? ' / ' . $b['monthly_cap'] : '' ?></div></div>
  <div class="stat"><div class="lbl">Total</div><div class="val"><?= $counts['total'] ?><?= !empty($b['total_cap']) ? ' / ' . $b['total_cap'] : '' ?></div></div>
</div>
<?php endif; ?>
<?php if (!empty($error)): ?><div class="card" style="border-color:var(--err)"><span class="badge badge-err">ERROR</span> <?= View::e($error) ?></div><?php endif; ?>
<form method="post" action="<?= View::e($action) ?>" class="card">
<div class="form-grid">
  <div class="form-row"><label>Buyer Type</label>
    <select name="integration_type" id="integration_type" onchange="lfToggleType()">
      <option value="ping_post" <?= $type === 'ping_post' ? 'selected' : '' ?>>Ping + Post (API with bid)</option>
      <option value="post_only" <?= $type === 'post_only' ? 'selected' : '' ?>>Post Only (Round Sky / LeadHorizon format)</option>
    </select>
  </div>
  <div class="form-row"></div>
  <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($b['name'] ?? '') ?>" required></div>
  <div class="form-row"><label>Active</label>
    <select name="active"><option value="1" <?= ($b['active'] ?? 1)?'selected':'' ?>>Active</option><option value="0" <?= empty($b['active'])?'selected':'' ?>>Inactive</option></select>
  </div>
  <div class="form-row pp-only"><label>Ping URL</label><input name="ping_url" value="<?= View::e($b['ping_url'] ?? '') ?>"></div>
  <div class="form-row"><label><span class="pp-only">Post URL</span><span class="po-only">Live Post URL</span></label><input name="post_url" value="<?= View::e($b['post_url'] ?? ($type === 'post_only' ? 'https://www.leadhorizon.com/leads/payday/live.php' : '')) ?>"></div>
  <div class="form-row pp-only"><label>Ping Method</label>
    <select name="ping_method"><?php foreach(['POST','GET'] as $m):?><option <?= (($b['ping_method']??'POST')===$m)?'selected':'' ?>><?= $m ?></option><?php endforeach;?></select>
  </div>
  <div class="form-row pp-only"><label>Post Method</label>
    <select name="post_method"><?php foreach(['POST','GET'] as $m):?><option <?= (($b['post_method']??'POST')===$m)?'selected':'' ?>><?= $m ?></option><?php endforeach;?></select>
  </div>
  <div class="form-row pp-only"><label>Request Format</label>
    <select name="request_format"><?php foreach(['json','form','xml'] as $f):?><option <?= (($b['request_format']??'json')===$f)?'selected':'' ?>><?= $f ?></option><?php endforeach;?></select>
  </div>
  <div class="form-row"><label>Timeout (ms)</label><input type="number" name="timeout_ms" value="<?= (int)($b['timeout_ms'] ?? 3000) ?>"></div>
  <div class="form-row"><label>Priority (lower = higher)</label><input type="number" name="priority" value="<?= (int)($b['priority'] ?? 100) ?>"></div>
  <div class="form-row"><label>Weight</label><input type="number" name="weight" value="<?= (int)($b['weight'] ?? 1) ?>"></div>
  <div class="form-row"><label>Daily Cap</label><input type="number" name="daily_cap" value="<?= $b['daily_cap'] ?? '' ?>"></div>
  <div class="form-row"><label>Hourly Cap</label><input type="number" name="hourly_cap" value="<?= $b['hourly_cap'] ?? '' ?>"></div>
  <div class="form-row"><label>Monthly Cap</label><input type="number" name="monthly_cap" value="<?= $b['monthly_cap'] ?? '' ?>"></div>
  <div class="form-row"><label>Total Cap</label><input type="number" name="total_cap" value="<?= $b['total_cap'] ?? '' ?>"></div>
</div>
<div class="po-only">
<h3>Post Only Settings</h3>
<div class="form-grid">
  <div class="form-row"><label>Post URL in use</label>
    <select name="po_test_mode"><option value="1" <?= $pc['test_mode'] ? 'selected' : '' ?>>TEST URL</option><option value="0" <?= !$pc['test_mode'] ? 'selected' : '' ?>>LIVE URL</option></select></div>
  <div class="form-row"><label>Test Post URL</label><input name="po_test_url" value="<?= View::e($pc['test_url']) ?>"></div>
  <div class="form-row"><label>Partner ID <?= $hasCreds ? '<span class="badge badge-ok">SAVED</span>' : '' ?></label><input name="po_partner" autocomplete="off" placeholder="<?= $hasCreds ? 'leave blank to keep saved' : '' ?>"></div>
  <div class="form-row"><label>Partner Password <?= $hasCreds ? '<span class="badge badge-ok">SAVED</span>' : '' ?></label><input name="po_partner_password" type="password" autocomplete="new-password" placeholder="<?= $hasCreds ? 'leave blank to keep saved' : '' ?>"></div>
  <div class="form-row"><label>Sub ID (per traffic source, not per lead)</label><input name="po_sub_id" value="<?= View::e($pc['sub_id']) ?>" placeholder="brightmoneysteps"></div>
  <div class="form-row"><label>Domain (no http://)</label><input name="po_domain" value="<?= View::e($pc['domain'] ?: 'brightmoneysteps.com') ?>"></div>
  <div class="form-row"><label>Minimum price waterfall ($, comma separated)</label><input name="po_price_tiers" value="<?= View::e(implode(', ', $pc['price_tiers'])) ?>"></div>
  <div class="form-row"><label>time_allowed per post (sec, min 20)</label><input name="po_time_allowed" type="number" min="20" value="<?= (int)$pc['time_allowed'] ?>"></div>
  <div class="form-row"><label>Total time budget per lead (sec, keep under ~50)</label><input name="po_total_budget_s" type="number" min="20" value="<?= (int)$pc['total_budget_s'] ?>"></div>
  <div class="form-row"></div>
  <div class="form-row"><label>Allowed account types</label><input name="po_f_account_types" value="<?= View::e(implode(', ', $pc['filters']['account_types'])) ?>"></div>
  <div class="form-row"><label>Excluded states</label><input name="po_f_excluded_states" value="<?= View::e(implode(', ', $pc['filters']['excluded_states'])) ?>"></div>
  <div class="form-row"><label>Min age</label><input name="po_f_min_age" type="number" value="<?= View::e($pc['filters']['min_age']) ?>"></div>
  <div class="form-row"><label>Max age</label><input name="po_f_max_age" type="number" value="<?= View::e($pc['filters']['max_age']) ?>"></div>
  <div class="form-row"><label>Min monthly income</label><input name="po_f_min_income" type="number" value="<?= View::e($pc['filters']['min_income']) ?>"></div>
  <div class="form-row"><label>Max monthly income</label><input name="po_f_max_income" type="number" value="<?= View::e($pc['filters']['max_income']) ?>"></div>
  <div class="form-row"><label><input type="checkbox" name="po_f_exclude_military" value="1" style="width:auto" <?= !empty($pc['filters']['exclude_military']) ? 'checked' : '' ?>> Exclude military</label></div>
  <div class="form-row"><label><input type="checkbox" name="po_f_work_phone_not_home_phone" value="1" style="width:auto" <?= !empty($pc['filters']['work_phone_not_home_phone']) ? 'checked' : '' ?>> Reject if work phone = home phone</label></div>
</div>
<div class="form-row"><label>Field Mapping JSON  { "round_sky_field": "your_form_field" }</label>
<textarea name="po_field_map_json" placeholder='{"social_security_number":"ssn","account_number":"bank_account"}'><?= ($type === 'post_only' && !empty($b['field_map'])) ? htmlspecialchars(json_encode($b['field_map'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES) : '' ?></textarea>
<div class="muted" style="margin-top:4px">Only fields whose form name differs from Round Sky's need to be listed. Round Sky fields: <?= View::e(implode(', ', \LeadFlow\DirectPost\RoundSkyClient::FIELDS)) ?></div></div>
<div class="form-row"><label>Value Mapping JSON  { "round_sky_field": { "Form Value": "round_sky_value" } }</label>
<textarea name="po_value_map_json" placeholder='{"housing":{"Own Home":"own","Renting":"rent"},"account_type":{"Checking Account":"checking"}}'><?= !empty($pc['value_map']) ? htmlspecialchars(json_encode($pc['value_map'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES) : '' ?></textarea>
<div class="muted" style="margin-top:4px">Match is case-insensitive. Values not listed are sent as-is.</div></div>
</div>
<div class="pp-only">
<div class="form-row"><label>Credentials JSON (type: bearer/basic/api_key)</label>
<textarea name="credentials_json"><?= $json('credentials','{"type":"bearer","token":""}') ?></textarea></div>
<div class="form-row"><label>Custom Headers JSON</label>
<textarea name="headers_json"><?= $json('headers','{}') ?></textarea></div>
<div class="form-row"><label>Field Mapping JSON  { "buyer_field": "internal_field" }</label>
<textarea name="field_map_json"><?= $json('field_map','{"fname":"first_name","lname":"last_name","email":"email","phone":"phone","state":"state","zip":"zip","income":"monthly_income","loan":"loan_amount"}') ?></textarea></div>
<div class="form-row"><label>Transformations JSON  [ { field, op, ... } ]</label>
<textarea name="transformations_json"><?= $json('transformations','[{"field":"phone","op":"phone_format","format":"digits"}]') ?></textarea></div>
<div class="form-row"><label>Response Rules JSON</label>
<textarea name="response_rules_json"><?= $json('response_rules','{"accepted_path":"status","accepted_values":["accepted","ok","1","true","matched"],"rejected_values":["rejected","0","false","no"],"bid_path":"bid","transaction_id_path":"transaction_id","error_path":"message"}') ?></textarea></div>
</div>
<div class="form-row"><label>Eligibility Rules JSON  { op:"AND", rules:[...] }</label>
<textarea name="rules_json"><?= $json('rules','{"op":"AND","rules":[{"field":"state","operator":"in","value":["TX","FL","CA"]},{"field":"monthly_income","operator":">=","value":2000}]}') ?></textarea></div>
<div class="form-row"><label>Schedule JSON  { timezone, days:[mon..], start:"09:00", end:"17:00" }</label>
<textarea name="schedule_json"><?= $json('schedule','{}') ?></textarea></div>
<button class="btn" type="submit"><?= $buyer ? 'Save Changes' : 'Create Buyer' ?></button>
</form>

<?php if ($buyer && $type === 'post_only'): $id = (int)$b['id']; ?>
<div class="card">
  <h3>Integration Test (always uses TEST URL)</h3>
  <p class="muted">Step 1: send DECLINED lead. Step 2: send APPROVED lead. Step 3: open its redirect URL in your browser to get the completion code.</p>
  <form method="post" action="/buyers/<?= $id ?>/test-post" style="display:inline"><input type="hidden" name="kind" value="declined"><button class="btn btn-secondary">1. Send DECLINED test lead</button></form>
  <form method="post" action="/buyers/<?= $id ?>/test-post" style="display:inline"><input type="hidden" name="kind" value="approved"><button class="btn">2. Send APPROVED test lead</button></form>
  <?php if ($testResult): ?>
  <div style="margin-top:16px">
    <div class="form-grid">
      <div><strong>Test sent:</strong> <?= View::e(strtoupper($testResult['kind'])) ?></div>
      <div><strong>Decision parsed:</strong> <?= $testResult['decision'] ? '<span class="badge ' . ($testResult['decision'] === 'APPROVED' ? 'badge-ok' : 'badge-err') . '">' . View::e($testResult['decision']) . '</span>' : '<span class="badge badge-err">NONE</span>' ?></div>
      <div><strong>Lead ID:</strong> <?= View::e($testResult['buyer_lead_id'] ?? '-') ?></div>
      <div><strong>Price:</strong> <?= $testResult['price'] !== null ? '$' . number_format((float)$testResult['price'], 2) : '-' ?></div>
      <div><strong>Message:</strong> <?= View::e($testResult['message'] ?? '-') ?></div>
      <div><strong>Response time:</strong> <?= (int)($testResult['rt_ms'] ?? 0) ?>ms</div>
      <?php if (!empty($testResult['error'])): ?><div><strong>Error:</strong> <span class="badge badge-err"><?= View::e($testResult['error']) ?></span></div><?php endif; ?>
    </div>
    <?php if (!empty($testResult['redirect_url'])): ?>
      <p><strong>Redirect URL:</strong> <code><?= View::e($testResult['redirect_url']) ?></code></p>
      <?php if ($testResult['decision'] === 'APPROVED'): ?><a class="btn" href="<?= View::e($testResult['redirect_url']) ?>" target="_blank" rel="noopener">3. Open redirect URL → get completion code</a><?php endif; ?>
    <?php endif; ?>
    <label style="margin-top:12px">Raw response</label>
    <pre><?= View::e($testResult['response_body'] ?? '') ?></pre>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function lfToggleType() {
  var po = document.getElementById('integration_type').value === 'post_only';
  document.querySelectorAll('.po-only').forEach(function (el) { el.style.display = po ? '' : 'none'; });
  document.querySelectorAll('.pp-only').forEach(function (el) { el.style.display = po ? 'none' : ''; });
}
lfToggleType();
</script>
