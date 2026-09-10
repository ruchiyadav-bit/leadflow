<?php use LeadFlow\Core\View; /** @var array $stats */ /** @var array $topBuyers */ ?>
<h2 style="margin-top:0">Dashboard</h2>
<div class="grid">
  <div class="stat"><div class="lbl">Today Leads</div><div class="val"><?= (int)($stats['today_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Yesterday</div><div class="val"><?= (int)($stats['yesterday_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Monthly</div><div class="val"><?= (int)($stats['monthly_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Sold</div><div class="val"><?= (int)($stats['sold_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Rejected</div><div class="val"><?= (int)($stats['rejected_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Pending</div><div class="val"><?= (int)($stats['pending_leads'] ?? 0) ?></div></div>
  <div class="stat"><div class="lbl">Revenue Today</div><div class="val">$<?= number_format((float)($stats['revenue_today'] ?? 0), 2) ?></div></div>
  <div class="stat"><div class="lbl">Revenue Yesterday</div><div class="val">$<?= number_format((float)($stats['revenue_yesterday'] ?? 0), 2) ?></div></div>
  <div class="stat"><div class="lbl">Revenue Month</div><div class="val">$<?= number_format((float)($stats['revenue_month'] ?? 0), 2) ?></div></div>
  <div class="stat"><div class="lbl">Avg Revenue / Lead</div><div class="val">$<?= number_format((float)($stats['avg_revenue_per_lead'] ?? 0), 4) ?></div></div>
  <div class="stat"><div class="lbl">Sell Rate</div><div class="val"><?= number_format(((float)($stats['sell_rate'] ?? 0)) * 100, 1) ?>%</div></div>
</div>

<div class="card" style="margin-top:24px">
  <h3 style="margin-top:0">Top Buyers (This Month)</h3>
  <table>
    <thead><tr><th>Buyer</th><th>Sold</th><th>Revenue</th><th>Avg Payout</th></tr></thead>
    <tbody>
      <?php foreach ($topBuyers as $b): ?>
      <tr>
        <td><a href="/buyers/<?= (int)$b['id'] ?>"><?= View::e($b['name']) ?></a></td>
        <td><?= (int)$b['sold'] ?></td>
        <td>$<?= number_format((float)$b['revenue'], 2) ?></td>
        <td>$<?= number_format((float)$b['avg'], 4) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$topBuyers): ?><tr><td colspan="4" class="muted">No sales yet this month.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
