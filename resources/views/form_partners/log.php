<?php use LeadFlow\Core\View; /** @var array $partners */ /** @var array $rows */ /** @var int $page */
$badge = [
    'submitted' => 'badge-ok', 'failed' => 'badge-err', 'queued' => 'badge-info', 'running' => 'badge-warn',
];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="margin:0">Form Submission Log</h2>
  <a href="/form-partners" class="btn btn-secondary">Partners</a>
</div>
<div class="card" style="padding:0;overflow-x:auto">
<table>
  <thead><tr><th>Lead</th><th>Created</th>
    <?php foreach ($partners as $p): ?><th><?= View::e($p['name']) ?></th><?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/leads/<?= View::e($r['lead_id']) ?>"><?= View::e($r['lead_id']) ?></a></td>
      <td class="muted"><?= View::e($r['created_at']) ?></td>
      <?php foreach ($partners as $p): $s = $r['submissions'][(int)$p['id']] ?? null; ?>
        <td>
          <?php if (!$s): ?><span class="muted">-</span>
          <?php else: ?>
            <span class="badge <?= $badge[$s['status']] ?? '' ?>" title="<?= View::e(($s['error_message'] ?? '') . ($s['final_url'] ? ' | ' . $s['final_url'] : '')) ?>"><?= View::e(strtoupper($s['status'])) ?></span>
            <div class="muted" style="font-size:11px">tries: <?= (int)$s['attempts'] ?><?= $s['duration_ms'] !== null ? ' · ' . (int)$s['duration_ms'] . 'ms' : '' ?></div>
            <?php if ($s['status'] === 'failed'): ?>
              <div class="muted" style="font-size:11px;max-width:220px;white-space:normal"><?= View::e(mb_substr((string)$s['error_message'], 0, 120)) ?></div>
              <form method="post" action="/form-partners/submissions/<?= (int)$s['id'] ?>/retry" style="margin-top:4px">
                <button class="btn btn-secondary" style="padding:2px 8px;font-size:11px">Retry</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= 2 + count($partners) ?>" class="muted" style="padding:20px">No submissions yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<div>
  <?php if ($page > 1): ?><a class="btn btn-secondary" href="?page=<?= $page - 1 ?>">&larr; Newer</a><?php endif; ?>
  <?php if (count($rows) === 50): ?><a class="btn btn-secondary" href="?page=<?= $page + 1 ?>">Older &rarr;</a><?php endif; ?>
</div>
