<?php
// pages/flags.php

// canonical tables
$T = [
  'result'    => 'result',
  'tool'      => 'tool',
  'suite'     => 'benchmarksuite',
  'benchmark' => 'benchmark',
];

$h = static fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Pull latest flags with joined context
$sql = "
  SELECT
    fr.flag_id, fr.result_id, fr.description, fr.created_at,
    t.name AS tool_name,
    CONCAT(s.name, COALESCE(CONCAT(' — ', s.variation), '')) AS suite_name,
    b.name AS bench_name,
    r.fom1, r.fom2, r.fom3, r.fom4
  FROM flag_records fr
  JOIN `{$T['result']}` r ON r.result_id = fr.result_id
  JOIN `{$T['tool']}`   t ON t.tool_id   = r.tool_id
  JOIN `{$T['suite']}`  s ON s.suite_id  = r.suite_id
  JOIN `{$T['benchmark']}` b ON b.benchmark_id = r.benchmark_id
  ORDER BY fr.created_at DESC, fr.flag_id DESC
  LIMIT 1000
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  /* same pretty switch used elsewhere */
  .switch { position: relative; display: inline-block; width: 42px; height: 24px; }
  .switch input { opacity: 0; width: 0; height: 0; }
  .switch .slider {
    position: absolute; inset: 0; cursor: pointer; background:#cfd4da;
    transition: .2s; border-radius: 999px;
  }
  .switch .slider:before {
    content: ""; position: absolute; height: 18px; width: 18px; left: 3px; top: 3px;
    background: #fff; transition: .2s; border-radius: 999px; box-shadow: 0 1px 2px rgba(0,0,0,.2);
  }
  .switch input:checked + .slider { background:#0d6efd; }
  .switch input:checked + .slider:before { transform: translateX(18px); }
  .switch input:disabled + .slider { background:#e5e7eb; cursor:not-allowed; }
</style>

<div class="card">
  <h3 style="margin:0 0 10px;">Flag Review</h3>
  <?php if (!$rows): ?>
    <div class="muted">There are no flags to review.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="width:80px;">Keep</th>
          <th>Date</th>
          <th>Suite</th>
          <th>Benchmark</th>
          <th>Tool</th>
          <th>Result&nbsp;ID</th>
          <th>FOM1</th>
          <th>FOM2</th>
          <th>FOM3</th>
          <th>FOM4</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <!-- Initially ON (checked) means 'keep'; turning OFF submits and deletes -->
              <form class="inline" method="post" action="index.php">
                <input type="hidden" name="action" value="resolve_flag">
                <input type="hidden" name="csrf" value="<?=$h($CSRF)?>">
                <input type="hidden" name="flag_id" value="<?=$h($r['flag_id'])?>">
                <label class="switch" title="Uncheck to mark reviewed and remove this flag">
                  <input type="checkbox" class="clear-flag-toggle" checked>
                  <span class="slider"></span>
                </label>
              </form>
            </td>
            <td class="mono"><?=$h($r['created_at'])?></td>
            <td><?=$h($r['suite_name'])?></td>
            <td><?=$h($r['bench_name'])?></td>
            <td><?=$h($r['tool_name'])?></td>
            <td class="mono"><?=$h($r['result_id'])?></td>
            <td class="mono"><?=$h($r['fom1'])?></td>
            <td class="mono"><?=$h($r['fom2'])?></td>
            <td class="mono"><?=$h($r['fom3'])?></td>
            <td class="mono"><?=$h($r['fom4'])?></td>
            <td><?=$h($r['description'])?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
document.addEventListener('change', (e) => {
  if (e.target.classList.contains('clear-flag-toggle')) {
    // When toggled OFF, submit to delete the flag
    if (!e.target.checked) {
      e.target.closest('form').submit();
    } else {
      // toggled back ON — do nothing
    }
  }
});
</script>
