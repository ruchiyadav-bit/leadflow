<?php use LeadFlow\Core\View; /** @var array $trees */ ?>
<div style="display:flex;justify-content:space-between;margin-bottom:16px">
  <h2 style="margin:0">Ping Trees</h2>
  <a href="/ping-trees/new" class="btn">+ New Ping Tree</a>
</div>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Name</th><th>Status</th><th>Routing</th><th>Min Bid</th><th>Fallback</th><th>Max Wait</th></tr></thead>
  <tbody>
  <?php foreach ($trees as $t): ?>
    <tr>
      <td><a href="/ping-trees/<?= (int)$t['id'] ?>"><?= View::e($t['name']) ?></a></td>
      <td><?= (int)$t['active'] ? '<span class="badge badge-ok">ACTIVE</span>' : '<span class="badge badge-err">OFF</span>' ?></td>
      <td><?= View::e($t['routing_mode']) ?></td>
      <td>$<?= number_format((float)$t['min_bid'], 2) ?></td>
      <td><?= (int)$t['allow_fallback'] ? 'Yes' : 'No' ?></td>
      <td><?= (int)$t['max_wait_ms'] ?>ms</td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$trees): ?><tr><td colspan="6" class="muted" style="padding:20px">No ping trees yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
