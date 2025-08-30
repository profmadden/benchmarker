<?php
$filter_suite_tools = (int)(getv('ts_suite_id') ?? 0);
$filter_bench_tools = (int)(getv('ts_benchmark_id') ?? 0);
$bench_opts_for_suite = $filter_suite_tools ? benchmarksBySuite($pdo,$filter_suite_tools) : [];
$params = []; $where = [];
if($filter_suite_tools){ $where[]='r.suite_id=?'; $params[]=$filter_suite_tools; }
if($filter_bench_tools){ $where[]='r.benchmark_id=?'; $params[]=$filter_bench_tools; }
$w = $where? ('WHERE '.implode(' AND ',$where)) : '';
$sql = "SELECT DISTINCT t.tool_id, t.name, t.URL, COUNT(r.result_id) AS run_count
        FROM tool t JOIN result r ON r.tool_id=t.tool_id
        $w
        GROUP BY t.tool_id, t.name, t.URL
        ORDER BY t.name";
$tools_list = fetchAll($pdo,$sql,$params);
?>
<form method="get" class="row" style="margin-bottom:12px">
  <input type="hidden" name="page" value="tools">
  <div>
    <label>Suite:</label>
    <select name="ts_suite_id" onchange="this.form.submit()">
      <option value="0">All</option>
      <?php foreach($all_suites as $s): ?>
        <option value="<?=$s['suite_id']?>" <?= $filter_suite_tools===$s['suite_id']?'selected':'' ?>><?=h($s['name'].($s['variation']?(' — '.$s['variation']):''))?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Benchmark:</label>
    <select name="ts_benchmark_id" onchange="this.form.submit()">
      <option value="0">All</option>
      <?php foreach($bench_opts_for_suite as $b): ?>
        <option value="<?=$b['benchmark_id']?>" <?= $filter_bench_tools===$b['benchmark_id']?'selected':'' ?>><?=h($b['name'])?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <noscript><div><button class="btn">Apply</button></div></noscript>
</form>

<table>
  <thead><tr><th>Tool</th><th>URL</th><th>Runs</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if(!$tools_list): ?><tr><td colspan="4">No tools found for this filter.</td></tr><?php endif; ?>
    <?php foreach($tools_list as $t): ?>
      <tr>
        <td><?=h($t['name'])?></td>
        <td><?php if($t['URL']): ?><a href="<?=h($t['URL'])?>" target="_blank">link</a><?php endif; ?></td>
        <td class="mono"><?= (int)$t['run_count'] ?></td>
        <td><a class="btn alt" href="index.php?page=manage&edit_tool=<?=$t['tool_id']?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
