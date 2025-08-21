<?php
require_once 'config.php';

// Query latest 200 results
$stmt = $pdo->query("
  SELECT r.result_id, r.date,
         r.fom1, r.fom2, r.fom3, r.fom4, r.URL,
         t.name  AS tool_name,
         tr.name AS release_name,
         s.name  AS suite_name,
         b.name  AS benchmark_name
  FROM result r
  LEFT JOIN tool          t  ON r.tool_id = t.tool_id
  LEFT JOIN toolrelease   tr ON r.tool_release_id = tr.tool_release_id
  LEFT JOIN benchmarksuite s ON r.suite_id = s.suite_id
  LEFT JOIN benchmark     b  ON r.benchmark_id = b.benchmark_id
  ORDER BY r.date DESC
  LIMIT 200
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Placement Results</title>
  <style>
    body { font-family: system-ui, -apple-system, Arial, sans-serif; margin: 24px; }
    h1 { margin-bottom: 16px; }
    table { border-collapse: collapse; width: 100%; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 6px 10px; }
    th { background: #f4f4f4; text-align: left; }
    tr:hover { background: #fafafa; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
    a { color: #0645AD; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<h1>Latest Results</h1>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Date</th>
      <th>Tool</th>
      <th>Release</th>
      <th>Suite</th>
      <th>Benchmark</th>
      <th>FOM1</th>
      <th>FOM2</th>
      <th>FOM3</th>
      <th>FOM4</th>
      <th>URL</th>
    </tr>
  </thead>
  <tbody>
    <?php if (count($rows) === 0): ?>
      <tr><td colspan="11">No results found</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?=htmlspecialchars($r['result_id'])?></td>
          <td><?=htmlspecialchars($r['date'])?></td>
          <td><?=htmlspecialchars($r['tool_name'] ?? '')?></td>
          <td><?=htmlspecialchars($r['release_name'] ?? '')?></td>
          <td><?=htmlspecialchars($r['suite_name'] ?? '')?></td>
          <td><?=htmlspecialchars($r['benchmark_name'] ?? '')?></td>
          <td class="mono"><?=htmlspecialchars($r['fom1'])?></td>
          <td class="mono"><?=htmlspecialchars($r['fom2'])?></td>
          <td class="mono"><?=htmlspecialchars($r['fom3'])?></td>
          <td class="mono"><?=htmlspecialchars($r['fom4'])?></td>
          <td>
            <?php if (!empty($r['URL'])): ?>
              <a href="<?=htmlspecialchars($r['URL'])?>" target="_blank">link</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>
