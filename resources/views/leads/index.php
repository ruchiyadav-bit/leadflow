<?php use LeadFlow\Core\View; /** @var array $result */ /** @var array $filters */ ?>
<h2>Leads</h2>
<form method="get" class="card" style="display:flex;gap:12px;flex-wrap:wrap">
  <input name="lead_id" placeholder="Lead ID" value="<?= View::e($filters['lead_id'] ?? '') ?>" style="width:160px">
  <input name="email"   placeholder="Email"    value="<?= View::e($filters['email']   ?? '') ?>" style="width:200px">
  <input name="phone"   placeholder="Phone"    value="<?= View::e($filters['phone']   ?? '') ?>" style="width:140px">
  <input name="state"   placeholder="State"    value="<?= View::e($filters['state']   ?? '') ?>" style="width:60px">
  <select name="status" style="width:160px">
    <option value="">Status: any</option>
    <?php foreach (['NEW','VALID','SOLD','REJECTED','FAILED','DUPLICATE'] as $s): ?>
      <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected':'' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Lead ID</th><th>Name</th><th>State</th><th>Status</th><th>Buyer</th><th>Revenue</th><th>Created</th></tr></thead>
  <tbody>
    <?php foreach ($result['data'] as $l): ?>
    <tr>
      <td><a href="/leads/<?= View::e($l['lead_id']) ?>"><?= View::e($l['lead_id']) ?></a></td>
      <td><?= View::e(substr(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''), 0, 30)) ?></td>
      <td><?= View::e($l['state']) ?></td>
      <td><span class="badge badge-info"><?= View::e($l['status']) ?></span></td>
      <td><?= $l['winning_buyer_id'] ? '#'.(int)$l['winning_buyer_id'] : '-' ?></td>
      <td>$<?= number_format((float)$l['revenue'], 4) ?></td>
      <td><?= View::e($l['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$result['data']): ?><tr><td colspan="7" class="muted" style="padding:20px">No leads found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<div class="muted" style="margin-top:12px">Total: <?= (int)$result['total'] ?> · Page <?= (int)$result['page'] ?></div>
