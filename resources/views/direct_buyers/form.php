<?php use LeadFlow\Core\View; use LeadFlow\DirectPost\DirectPostBuyerRepository as R;
/** @var ?array $buyer */
$b = $buyer ?? [];
$f = $b['filters'] ?? R::DEFAULT_FILTERS;
$action = $buyer ? '/direct-buyers/' . (int)$b['id'] : '/direct-buyers';
$attempts = $attempts ?? [];
$testResult = $testResult ?? null;
$hasCreds = !empty($b['credentials']['partner']);
?>
<h2><?= $buyer ? 'Edit Direct Buyer' : 'New Direct Buyer (Round Sky)' ?></h2>
<?php if (!empty($error)): ?><div class="card" style="border-color:var(--err)"><span class="badge badge-err">ERROR</span> <?= View::e($error) ?></div><?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="card">
<div class="form-grid">
  <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($b['name'] ?? 'Round Sky (LeadHorizon)') ?>" required></div>
  <div class="form-row"><label>Active (receives real leads)</label>
    <select name="active"><option value="0" <?= empty($b['active']) ? 'selected' : '' ?>>Inactive</option><option value="1" <?= !empty($b['active']) ? 'selected' : '' ?>>Active</option></select>
  </div>
  <div class="form-row"><label>Post URL in use</label>
    <select name="test_mode"><option value="1" <?= ($b['test_mode'] ?? 1) ? 'selected' : '' ?>>TEST URL</option><option value="0" <?= ($buyer && !(int)$b['test_mode']) ? 'selected' : '' ?>>LIVE URL</option></select>
  </div>
  <div class="form-row"><label>Priority (lower = first)</label><input name="priority" type="number" value="<?= View::e($b['priority'] ?? 100) ?>"></div>
  <div class="form-row"><label>Test Post URL</label><input name="test_url" value="<?= View::e($b['test_url'] ?? 'https://www.leadhorizon.com/leads/payday/test.php') ?>" required></div>
  <div class="form-row"><label>Live Post URL</label><input name="live_url" value="<?= View::e($b['live_url'] ?? 'https://www.leadhorizon.com/leads/payday/live.php') ?>" required></div>
  <div class="form-row"><label>Partner ID <?= $hasCreds ? '<span class="badge badge-ok">SAVED</span>' : '' ?></label><input name="partner" autocomplete="off" placeholder="<?= $hasCreds ? 'leave blank to keep saved' : '' ?>"></div>
  <div class="form-row"><label>Partner Password <?= $hasCreds ? '<span class="badge badge-ok">SAVED</span>' : '' ?></label><input name="partner_password" type="password" autocomplete="new-password" placeholder="<?= $hasCreds ? 'leave blank to keep saved' : '' ?>"></div>
  <div class="form-row"><label>Sub ID (per traffic source, not per lead)</label><input name="sub_id" value="<?= View::e($b['sub_id'] ?? '') ?>" required placeholder="brightmoneysteps"></div>
  <div class="form-row"><label>Domain (no http://)</label><input name="domain" value="<?= View::e($b['domain'] ?? 'brightmoneysteps.com') ?>" required></div>
  <div class="form-row"><label>Minimum price waterfall ($, comma separated)</label><input name="price_tiers" value="<?= View::e(implode(', ', $b['price_tiers'] ?? R::DEFAULT_TIERS)) ?>"></div>
  <div class="form-row"><label>Daily cap (approved)</label><input name="daily_cap" type="number" value="<?= View::e($b['daily_cap'] ?? '') ?>"></div>
  <div class="form-row"><label>time_allowed per post (sec, min 20)</label><input name="time_allowed" type="number" min="20" value="<?= View::e($b['time_allowed'] ?? 20) ?>"></div>
  <div class="form-row"><label>Total time budget per lead (sec)</label><input name="total_budget_s" type="number" min="20" value="<?= View::e($b['total_budget_s'] ?? 45) ?>">
    <div class="muted" style="margin-top:4px">Server request timeout is 60s — keep this under ~50.</div></div>
</div>
<h3>Filters</h3>
<div class="form-grid">
  <div class="form-row"><label>Allowed account types</label><input name="f_account_types" value="<?= View::e(implode(', ', $f['account_types'])) ?>"></div>
  <div class="form-row"><label>Excluded states</label><input name="f_excluded_states" value="<?= View::e(implode(', ', $f['excluded_states'])) ?>"></div>
  <div class="form-row"><label>Min age</label><input name="f_min_age" type="number" value="<?= View::e($f['min_age']) ?>"></div>
  <div class="form-row"><label>Max age</label><input name="f_max_age" type="number" value="<?= View::e($f['max_age']) ?>"></div>
  <div class="form-row"><label>Min monthly income</label><input name="f_min_income" type="number" value="<?= View::e($f['min_income']) ?>"></div>
  <div class="form-row"><label>Max monthly income</label><input name="f_max_income" type="number" value="<?= View::e($f['max_income']) ?>"></div>
  <div class="form-row"><label><input type="checkbox" name="f_exclude_military" value="1" style="width:auto" <?= !empty($f['exclude_military']) ? 'checked' : '' ?>> Exclude military</label></div>
  <div class="form-row"><label><input type="checkbox" name="f_work_phone_not_home_phone" value="1" style="width:auto" <?= !empty($f['work_phone_not_home_phone']) ? 'checked' : '' ?>> Reject if work phone = home phone</label></div>
</div>
<button class="btn">Save</button> <a href="/direct-buyers" class="btn btn-secondary">Back</a>
</form>

<?php if ($buyer): $id = (int)$b['id']; ?>
<div class="card">
  <h3>Integration Test (always uses TEST URL)</h3>
  <p class="muted">Step 1: send DECLINED lead. Step 2 &amp; 3: send APPROVED lead, then open its redirect URL in your browser to get the completion code.</p>
  <form method="post" action="/direct-buyers/<?= $id ?>/test" style="display:inline"><input type="hidden" name="kind" value="declined"><button class="btn btn-secondary">1. Send DECLINED test lead</button></form>
  <form method="post" action="/direct-buyers/<?= $id ?>/test" style="display:inline"><input type="hidden" name="kind" value="approved"><button class="btn">2. Send APPROVED test lead</button></form>

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
      <?php if ($testResult['decision'] === 'APPROVED'): ?>
      <a class="btn" href="<?= View::e($testResult['redirect_url']) ?>" target="_blank" rel="noopener">3. Open redirect URL → get completion code</a>
      <?php endif; ?>
    <?php endif; ?>
    <label style="margin-top:12px">Raw response</label>
    <pre><?= View::e($testResult['response_body'] ?? '') ?></pre>
  </div>
  <?php endif; ?>
</div>

<div class="card">
<h3>Recent Posts</h3>
<table>
  <thead><tr><th>Time</th><th>Lead</th><th>Test</th><th>Min Price</th><th>Decision</th><th>Price</th><th>Message</th><th>RT</th></tr></thead>
  <tbody>
  <?php foreach ($attempts as $a): ?>
    <tr>
      <td><?= View::e($a['created_at']) ?></td>
      <td><?= $a['lead_ref'] ? '<a href="/leads/' . View::e($a['lead_ref']) . '">' . View::e($a['lead_ref']) . '</a>' : '-' ?></td>
      <td><?= (int)$a['is_test'] ? 'yes' : 'no' ?></td>
      <td>$<?= number_format((float)$a['minimum_price'], 2) ?></td>
      <td><?= $a['decision'] ? '<span class="badge ' . ($a['decision'] === 'APPROVED' ? 'badge-ok' : 'badge-err') . '">' . View::e($a['decision']) . '</span>' : '<span class="badge badge-warn">' . View::e($a['error_message'] ?? 'ERR') . '</span>' ?></td>
      <td><?= $a['price'] !== null ? '$' . number_format((float)$a['price'], 2) : '-' ?></td>
      <td class="muted"><?= View::e($a['message'] ?? '') ?></td>
      <td><?= (int)$a['response_time_ms'] ?>ms</td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$attempts): ?><tr><td colspan="8" class="muted">No posts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
