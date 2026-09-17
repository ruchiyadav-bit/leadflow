<?php use LeadFlow\Core\View; /** @var ?array $partner */ /** @var ?string $error */
$p = $partner ?? [];
$isEdit = !empty($p['id']);
$action = $isEdit ? '/form-partners/' . (int)$p['id'] : '/form-partners';
$exampleSteps = json_encode([
    ['action' => 'fill', 'selector' => '#first_name', 'value' => '{first_name}'],
    ['action' => 'fill', 'selector' => '#last_name', 'value' => '{last_name}'],
    ['action' => 'fill', 'selector' => 'input[name=email]', 'value' => '{email}'],
    ['action' => 'fill', 'selector' => 'input[name=phone]', 'value' => '{phone}'],
    ['action' => 'fill', 'selector' => 'input[name=zip]', 'value' => '{zip}'],
    ['action' => 'select', 'selector' => 'select[name=state]', 'value' => '{state}'],
    ['action' => 'check', 'selector' => '#consent'],
    ['action' => 'click', 'selector' => 'button[type=submit]'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$exampleSuccess = json_encode(['url_contains' => 'thank-you'], JSON_UNESCAPED_SLASHES);
$pretty = function (?string $json) {
    $d = json_decode((string)$json, true);
    return is_array($d) ? json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string)$json;
};
?>
<h2><?= $isEdit ? 'Edit Form Partner' : 'New Form Partner' ?></h2>
<?php if ($error): ?><div class="card" style="border-color:var(--err);color:var(--err)"><?= View::e($error) ?></div><?php endif; ?>
<form method="post" action="<?= View::e($action) ?>" class="card">
<div class="form-grid">
  <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($p['name'] ?? '') ?>" required></div>
  <div class="form-row"><label>Active</label>
    <select name="active"><option value="1" <?= ($p['active'] ?? 1) ? 'selected' : '' ?>>Active</option><option value="0" <?= (isset($p['active']) && !(int)$p['active']) ? 'selected' : '' ?>>Inactive</option></select>
  </div>
  <div class="form-row" style="grid-column:1/-1"><label>Form URL</label>
    <input name="form_url" value="<?= View::e($p['form_url'] ?? '') ?>" required placeholder="https://partner.com/apply">
  </div>
  <div class="form-row"><label>Order (lower runs first)</label><input name="sort_order" type="number" value="<?= View::e($p['sort_order'] ?? 100) ?>"></div>
  <div class="form-row"><label>Timeout per attempt (ms)</label><input name="timeout_ms" type="number" value="<?= View::e($p['timeout_ms'] ?? 45000) ?>"></div>
  <div class="form-row"><label>Max retries</label><input name="max_retries" type="number" min="0" max="10" value="<?= View::e($p['max_retries'] ?? 2) ?>"></div>
  <div class="form-row"><label>Notes (permission reference etc.)</label><input name="notes" value="<?= View::e($p['notes'] ?? '') ?>"></div>
  <div class="form-row" style="grid-column:1/-1"><label>Steps (JSON)</label>
    <textarea name="steps_json" style="min-height:260px" required><?= View::e(isset($p['steps_json']) ? $pretty($p['steps_json']) : $exampleSteps) ?></textarea>
    <div class="muted" style="margin-top:4px">
      Actions: <code>fill</code>, <code>select</code> (selector + value), <code>check</code>, <code>click</code> (selector), <code>wait</code> (ms), <code>wait_for</code> (selector), <code>goto</code> (url).
      Optional per step: <code>"optional": true</code> (skip if element not found).<br>
      Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{email}</code> <code>{phone}</code> <code>{phone_dashed}</code> <code>{address}</code> <code>{city}</code> <code>{state}</code> <code>{zip}</code> <code>{date_of_birth}</code> <code>{employment_status}</code> <code>{monthly_income}</code> <code>{pay_frequency}</code> <code>{loan_amount}</code> <code>{lead_id}</code> and any custom field key, e.g. <code>{credit_score}</code>.
    </div>
  </div>
  <div class="form-row" style="grid-column:1/-1"><label>Success rule (JSON, optional)</label>
    <input name="success_json" value="<?= View::e(isset($p['id']) || isset($p['name']) ? ($p['success_json'] ?? '') : $exampleSuccess) ?>">
    <div class="muted" style="margin-top:4px">One of: <code>{"url_contains":"thank"}</code>, <code>{"text_contains":"Thank you"}</code>, <code>{"selector":".success"}</code>. Empty = submitted if the last step runs without error.</div>
  </div>
</div>
<button class="btn">Save</button> <a href="/form-partners" class="btn btn-secondary">Back</a>
</form>
