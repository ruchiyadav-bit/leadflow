<?php use LeadFlow\Core\View; /** @var array $lead */ /** @var array $history */ /** @var array $pings */ /** @var array $posts */ ?>
<h2>Lead <?= View::e($lead['lead_id']) ?></h2>
<div class="card">
  <div class="form-grid">
    <div><strong>Status:</strong> <span class="badge badge-info"><?= View::e($lead['status']) ?></span></div>
    <div><strong>Created:</strong> <?= View::e($lead['created_at']) ?></div>
    <div><strong>Name:</strong> <?= View::e($lead['first_name'] . ' ' . $lead['last_name']) ?></div>
    <div><strong>State/Zip:</strong> <?= View::e($lead['state']) ?> <?= View::e($lead['zip']) ?></div>
    <div><strong>Employment:</strong> <?= View::e($lead['employment_status'] ?? '-') ?></div>
    <div><strong>Income:</strong> $<?= number_format((float)($lead['monthly_income'] ?? 0), 2) ?>/mo</div>
    <div><strong>Loan Requested:</strong> $<?= number_format((float)($lead['loan_amount'] ?? 0), 2) ?></div>
    <div><strong>Revenue:</strong> $<?= number_format((float)$lead['revenue'], 4) ?></div>
    <div><strong>Winning Buyer:</strong> <?= $lead['winning_buyer_id'] ? '#' . (int)$lead['winning_buyer_id'] : '-' ?></div>
    <div><strong>Buyer Txn:</strong> <?= View::e($lead['buyer_transaction_id'] ?? '-') ?></div>
  </div>
</div>

<div class="card">
<h3>Journey</h3>
<table>
  <thead><tr><th>Time</th><th>From</th><th>To</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($history as $h): ?>
    <tr><td><?= View::e($h['created_at']) ?></td><td><?= View::e($h['from_status'] ?? '-') ?></td><td><span class="badge badge-info"><?= View::e($h['to_status']) ?></span></td><td><?= View::e($h['note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="card">
<h3>Ping Results</h3>
<table>
  <thead><tr><th>Buyer</th><th>Status</th><th>Accepted</th><th>Bid</th><th>Txn ID</th><th>RT</th><th>Error</th></tr></thead>
  <tbody>
  <?php foreach ($pings as $p): ?>
    <tr>
      <td><?= View::e($p['buyer_name']) ?></td>
      <td><?= View::e($p['status']) ?></td>
      <td><?= $p['accepted'] === null ? '?' : ((int)$p['accepted'] ? '✓' : '✗') ?></td>
      <td><?= $p['bid'] !== null ? '$' . number_format((float)$p['bid'], 4) : '-' ?></td>
      <td><?= View::e($p['transaction_id'] ?? '-') ?></td>
      <td><?= (int)$p['response_time_ms'] ?>ms</td>
      <td class="muted"><?= View::e(substr((string)($p['error_message'] ?? ''), 0, 80)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="card">
<h3>Post Attempts</h3>
<table>
  <thead><tr><th>Buyer</th><th>Success</th><th>Payout</th><th>Txn ID</th><th>RT</th><th>Error</th></tr></thead>
  <tbody>
  <?php foreach ($posts as $p): ?>
    <tr>
      <td><?= View::e($p['buyer_name']) ?></td>
      <td><?= (int)$p['success'] ? '<span class="badge badge-ok">YES</span>' : '<span class="badge badge-err">NO</span>' ?></td>
      <td><?= $p['payout'] !== null ? '$' . number_format((float)$p['payout'], 4) : '-' ?></td>
      <td><?= View::e($p['transaction_id'] ?? '-') ?></td>
      <td><?= (int)$p['response_time_ms'] ?>ms</td>
      <td class="muted"><?= View::e(substr((string)($p['error_message'] ?? ''), 0, 80)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
