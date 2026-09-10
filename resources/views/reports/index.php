<?php use LeadFlow\Core\View; /** @var string $from */ /** @var string $to */ /** @var array $byBuyer */ /** @var array $bySource */ ?>
<h2>Revenue Reports</h2>
<form method="get" class="card" style="display:flex;gap:12px;align-items:end">
  <div><label>From</label><input type="datetime-local" name="from" value="<?= View::e(str_replace(' ', 'T', substr($from, 0, 16))) ?>"></div>
  <div><label>To</label><input type="datetime-local" name="to" value="<?= View::e(str_replace(' ', 'T', substr($to, 0, 16))) ?>"></div>
  <button class="btn" type="submit">Update</button>
  <a class="btn btn-secondary" href="/reports/revenue.csv?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>">Export CSV</a>
</form>
<div class="card">
<h3>By Buyer</h3>
<table>
  <thead><tr><th>Buyer</th><th>Sold</th><th>Revenue</th><th>Avg</th></tr></thead>
  <tbody>
    <?php foreach ($byBuyer as $r): ?>
      <tr><td><?= View::e($r['name']) ?></td><td><?= (int)$r['sold'] ?></td><td>$<?= number_format((float)$r['revenue'], 2) ?></td><td>$<?= number_format((float)$r['avg'], 4) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<div class="card">
<h3>By Source</h3>
<table>
  <thead><tr><th>Source</th><th>Sold</th><th>Revenue</th></tr></thead>
  <tbody>
    <?php foreach ($bySource as $r): ?>
      <tr><td><?= View::e($r['name'] ?? '(none)') ?></td><td><?= (int)$r['sold'] ?></td><td>$<?= number_format((float)$r['revenue'], 2) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
