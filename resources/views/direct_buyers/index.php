<?php use LeadFlow\Core\View; /** @var array $buyers */ ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="margin:0">Direct Post Buyers</h2>
  <a href="/direct-buyers/new" class="btn">+ New Direct Buyer</a>
</div>
<p class="muted">Post-only buyer APIs (e.g. Round Sky / LeadHorizon). Lead is posted with a minimum-price waterfall; approved leads must be redirected to the buyer's URL.</p>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Name</th><th>Status</th><th>Mode</th><th>Priority</th><th>Leads Posted</th><th>Approved</th><th>Revenue</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($buyers as $b): ?>
    <tr>
      <td><a href="/direct-buyers/<?= (int)$b['id'] ?>"><?= View::e($b['name']) ?></a></td>
      <td><?= (int)$b['active'] ? '<span class="badge badge-ok">ACTIVE</span>' : '<span class="badge badge-err">INACTIVE</span>' ?></td>
      <td><?= (int)$b['test_mode'] ? '<span class="badge badge-warn">TEST URL</span>' : '<span class="badge badge-ok">LIVE URL</span>' ?></td>
      <td><?= (int)$b['priority'] ?></td>
      <td><?= (int)$b['leads_posted'] ?></td>
      <td><?= (int)$b['approved'] ?></td>
      <td>$<?= number_format((float)$b['revenue'], 2) ?></td>
      <td>
        <form method="post" action="/direct-buyers/<?= (int)$b['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px"><?= (int)$b['active'] ? 'Pause' : 'Enable' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$buyers): ?><tr><td colspan="8" class="muted" style="padding:20px">No direct buyers yet. <a href="/direct-buyers/new">Create one</a>.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
