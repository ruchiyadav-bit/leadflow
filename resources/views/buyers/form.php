<?php use LeadFlow\Core\View; /** @var ?array $buyer */
$b = $buyer ?? [];
$action = $buyer ? '/buyers/' . (int)$b['id'] : '/buyers';
$title = $buyer ? 'Edit Buyer' : 'New Buyer';
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
<form method="post" action="<?= View::e($action) ?>" class="card">
<div class="form-grid">
  <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($b['name'] ?? '') ?>" required></div>
  <div class="form-row"><label>Active</label>
    <select name="active"><option value="1" <?= ($b['active'] ?? 1)?'selected':'' ?>>Active</option><option value="0" <?= empty($b['active'])?'selected':'' ?>>Inactive</option></select>
  </div>
  <div class="form-row"><label>Ping URL</label><input name="ping_url" value="<?= View::e($b['ping_url'] ?? '') ?>" required></div>
  <div class="form-row"><label>Post URL</label><input name="post_url" value="<?= View::e($b['post_url'] ?? '') ?>" required></div>
  <div class="form-row"><label>Ping Method</label>
    <select name="ping_method"><?php foreach(['POST','GET'] as $m):?><option <?= (($b['ping_method']??'POST')===$m)?'selected':'' ?>><?= $m ?></option><?php endforeach;?></select>
  </div>
  <div class="form-row"><label>Post Method</label>
    <select name="post_method"><?php foreach(['POST','GET'] as $m):?><option <?= (($b['post_method']??'POST')===$m)?'selected':'' ?>><?= $m ?></option><?php endforeach;?></select>
  </div>
  <div class="form-row"><label>Request Format</label>
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
<div class="form-row"><label>Schedule JSON  { timezone, days:[mon..], start:"09:00", end:"17:00" }</label>
<textarea name="schedule_json"><?= $json('schedule','{}') ?></textarea></div>
<div class="form-row"><label>Eligibility Rules JSON  { op:"AND", rules:[...] }</label>
<textarea name="rules_json"><?= $json('rules','{"op":"AND","rules":[{"field":"state","operator":"in","value":["TX","FL","CA"]},{"field":"monthly_income","operator":">=","value":2000}]}') ?></textarea></div>
<button class="btn" type="submit"><?= $buyer ? 'Save Changes' : 'Create Buyer' ?></button>
</form>
