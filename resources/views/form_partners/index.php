<?php use LeadFlow\Core\View; /** @var array $partners */ ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="margin:0">Form Partners</h2>
  <div><a href="/form-partners/log" class="btn btn-secondary">Submission Log</a> <a href="/form-partners/new" class="btn">+ New Partner</a></div>
</div>
<p class="muted">Permission-based partner forms. Every new lead is queued for all <strong>active</strong> partners and submitted by the worker from your own server IP, in the order below.</p>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Order</th><th>Name</th><th>Form URL</th><th>Status</th><th>Submitted</th><th>Failed</th><th>Pending</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($partners as $p): ?>
    <tr>
      <td><?= (int)$p['sort_order'] ?></td>
      <td><a href="/form-partners/<?= (int)$p['id'] ?>"><?= View::e($p['name']) ?></a></td>
      <td class="muted" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= View::e($p['form_url']) ?></td>
      <td><?= (int)$p['active'] ? '<span class="badge badge-ok">ACTIVE</span>' : '<span class="badge badge-err">INACTIVE</span>' ?></td>
      <td><?= (int)$p['submitted'] ?></td>
      <td><?= (int)$p['failed'] ?></td>
      <td><?= (int)$p['pending'] ?></td>
      <td>
        <form method="post" action="/form-partners/<?= (int)$p['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px"><?= (int)$p['active'] ? 'Pause' : 'Enable' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$partners): ?><tr><td colspan="8" class="muted" style="padding:20px">No partners yet. <a href="/form-partners/new">Add one</a>.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
