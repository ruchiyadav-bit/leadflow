<?php use LeadFlow\Core\View; /** @var ?array $tree */ /** @var array $buyers */
$t = $tree ?? [];
$action = $tree ? '/ping-trees/' . (int)$t['id'] : '/ping-trees';
$selected = $tree ? (json_decode($t['buyer_ids_json'] ?? '[]', true) ?: []) : [];
$fallback = $tree ? (json_decode($t['fallback_ids_json'] ?? '[]', true) ?: []) : [];
?>
<h2><?= $tree ? 'Edit Ping Tree' : 'New Ping Tree' ?></h2>
<form method="post" action="<?= View::e($action) ?>" class="card">
  <div class="form-grid">
    <div class="form-row"><label>Name</label><input name="name" value="<?= View::e($t['name'] ?? '') ?>" required></div>
    <div class="form-row"><label>Active</label>
      <select name="active"><option value="1" <?= ($t['active'] ?? 1)?'selected':'' ?>>Active</option><option value="0" <?= empty($t['active'])?'selected':'' ?>>Off</option></select>
    </div>
    <div class="form-row"><label>Routing Mode</label>
      <select name="routing_mode">
        <?php foreach (['highest_bid','priority','weighted','round_robin','waterfall'] as $m): ?>
          <option value="<?= $m ?>" <?= (($t['routing_mode']??'highest_bid')===$m)?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Min Bid ($)</label><input type="number" step="0.01" name="min_bid" value="<?= View::e($t['min_bid'] ?? '0.00') ?>"></div>
    <div class="form-row"><label>Allow Fallback</label>
      <select name="allow_fallback"><option value="1" <?= ($t['allow_fallback'] ?? 1)?'selected':'' ?>>Yes</option><option value="0" <?= empty($t['allow_fallback'])?'selected':'' ?>>No</option></select>
    </div>
    <div class="form-row"><label>Max Wait (ms)</label><input type="number" name="max_wait_ms" value="<?= (int)($t['max_wait_ms'] ?? 5000) ?>"></div>
    <div class="form-row"><label>Max Buyers</label><input type="number" name="max_buyers" value="<?= (int)($t['max_buyers'] ?? 25) ?>"></div>
    <div class="form-row"><label>Source ID (optional)</label><input name="source_id" value="<?= View::e($t['source_id'] ?? '') ?>"></div>
    <div class="form-row"><label>Campaign ID (optional)</label><input name="campaign_id" value="<?= View::e($t['campaign_id'] ?? '') ?>"></div>
  </div>
  <div class="form-row"><label>Buyers (multi-select)</label>
    <select name="buyer_ids[]" multiple size="8">
      <?php foreach ($buyers as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= in_array((int)$b['id'], $selected, true) ? 'selected':'' ?>><?= View::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row"><label>Fallback Buyers (multi-select)</label>
    <select name="fallback_ids[]" multiple size="5">
      <?php foreach ($buyers as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= in_array((int)$b['id'], $fallback, true) ? 'selected':'' ?>><?= View::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn" type="submit"><?= $tree ? 'Save' : 'Create' ?></button>
</form>
