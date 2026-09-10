<?php use LeadFlow\Core\View; /** @var array $buyers */ ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="margin:0">Buyers</h2>
  <a href="/buyers/new" class="btn">+ New Buyer</a>
</div>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Name</th><th>Status</th><th>Pinged</th><th>Accepted</th><th>Sold</th><th>Revenue</th><th>Avg RT</th><th>Timeouts</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($buyers as $b): $s = $b['stats']; ?>
    <tr>
      <td><a href="/buyers/<?= (int)$b['id'] ?>"><?= View::e($b['name']) ?></a></td>
      <td><?= (int)$b['active'] ? '<span class="badge badge-ok">ACTIVE</span>' : '<span class="badge badge-err">INACTIVE</span>' ?></td>
      <td><?= (int)($s['pinged'] ?? 0) ?></td>
      <td><?= (int)($s['accepted'] ?? 0) ?></td>
      <td><?= (int)($s['sold'] ?? 0) ?></td>
      <td>$<?= number_format((float)($s['revenue'] ?? 0), 2) ?></td>
      <td><?= (int)($s['avg_response'] ?? 0) ?>ms</td>
      <td><?= (int)($s['timeouts'] ?? 0) ?></td>
      <td>
        <form method="post" action="/buyers/<?= (int)$b['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px"><?= (int)$b['active'] ? 'Pause' : 'Enable' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$buyers): ?><tr><td colspan="9" class="muted" style="padding:20px">No buyers yet. <a href="/buyers/new">Create one</a>.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
