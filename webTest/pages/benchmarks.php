<?php
$filter_suite = (int)(getv('suite_id') ?? 0);
if($filter_suite){
  $sql = "SELECT b.*, s.name AS suite_name, GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS tools
          FROM benchmark b
          JOIN benchmarksuite s ON b.suite_id=s.suite_id
          LEFT JOIN result r ON r.benchmark_id=b.benchmark_id
          LEFT JOIN tool t ON r.tool_id=t.tool_id
          WHERE b.suite_id=?
          GROUP BY b.benchmark_id
          ORDER BY b.name";
  $benchmarks = fetchAll($pdo,$sql,[$filter_suite]);
} else {
  $sql = "SELECT b.*, s.name AS suite_name, GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS tools
          FROM benchmark b
          JOIN benchmarksuite s ON b.suite_id=s.suite_id
          LEFT JOIN result r ON r.benchmark_id=b.benchmark_id
          LEFT JOIN tool t ON r.tool_id=t.tool_id
          GROUP BY b.benchmark_id
          ORDER BY s.name, b.name";
  $benchmarks = fetchAll($pdo,$sql);
}
?>
<form method="get" style="margin-bottom:12px">
  <input type="hidden" name="page" value="benchmarks">
  <label>Filter by suite:</label>
  <select name="suite_id" onchange="this.form.submit()">
    <option value="0">All suites</option>
    <?php foreach($all_suites as $s): ?>
      <option value="<?=$s['suite_id']?>" <?= $filter_suite===$s['suite_id']?'selected':'' ?>><?=h($s['name'].($s['variation']?(' — '.$s['variation']):''))?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn">Apply</button></noscript>
</form>

<table>
  <thead>
    <tr>
      <th>Benchmark</th><th>Suite</th><th>URL</th><th>Tools (from results)</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if(!$benchmarks): ?><tr><td colspan="5">No benchmarks</td></tr><?php endif; ?>
    <?php foreach($benchmarks as $b): ?>
      <tr id="b<?=$b['benchmark_id']?>">
        <td><strong><?=h($b['name'])?></strong></td>
        <td><?=h($b['suite_name'])?></td>
        <td><?php if($b['URL']): ?><a href="<?=h($b['URL'])?>" target="_blank">link</a><?php endif; ?></td>
        <td><?=h($b['tools'] ?: '—')?></td>
        <td><a class="btn alt" href="index.php?page=manage&edit_benchmark=<?=$b['benchmark_id']?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
