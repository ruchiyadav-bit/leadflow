<?php use LeadFlow\Core\View; /** @var array $offers */ ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="margin:0">Offers</h2>
  <a href="/offers/new" class="btn">+ New Offer</a>
</div>
<p class="muted">Affiliate-link buyers. <strong>S2S Dashboard</strong> = network sends conversion postback to LeadFlow. <strong>Affiliate Direct</strong> = only clicks tracked here, conversions are in the network's own panel.</p>
<div class="card" style="padding:0">
<table>
  <thead><tr><th>Name</th><th>Network</th><th>Mode</th><th>Status</th><th>Clicks</th><th>Conversions</th><th>Revenue</th><th>EPC</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($offers as $o): $clicks = (int)$o['clicks']; $isS2s = $o['delivery_mode'] === 's2s'; ?>
    <tr>
      <td><a href="/offers/<?= (int)$o['id'] ?>"><?= View::e($o['name']) ?></a></td>
      <td><?= View::e($o['network'] ?? '-') ?></td>
      <td><?= $isS2s ? '<span class="badge badge-info">S2S DASHBOARD</span>' : '<span class="badge badge-warn">AFFILIATE DIRECT</span>' ?></td>
      <td><?= (int)$o['active'] ? '<span class="badge badge-ok">ACTIVE</span>' : '<span class="badge badge-err">INACTIVE</span>' ?></td>
      <td><?= $clicks ?></td>
      <td><?= $isS2s ? (int)$o['conversions'] : '<span class="muted">network panel</span>' ?></td>
      <td><?= $isS2s ? '$' . number_format((float)$o['revenue'], 2) : '<span class="muted">network panel</span>' ?></td>
      <td><?= $isS2s && $clicks ? '$' . number_format((float)$o['revenue'] / $clicks, 2) : '-' ?></td>
      <td>
        <form method="post" action="/offers/<?= (int)$o['id'] ?>/toggle" style="display:inline">
          <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px"><?= (int)$o['active'] ? 'Pause' : 'Enable' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$offers): ?><tr><td colspan="9" class="muted" style="padding:20px">No offers yet. <a href="/offers/new">Create one</a>.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
