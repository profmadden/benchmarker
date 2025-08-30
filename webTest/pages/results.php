<?php
// pages/results.php

// ---- config for this page ----
$TOOL_WHITELIST = ['RePIAce','SA','CT','NTUplaceic04','NTUplace3','FS5.1']; // shown in Tools filter (if present in DB)
$FOM_KEYS       = ['fom1','fom2','fom3','fom4'];

// tiny helpers
$h = static fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$selected = static fn($cond)=>$cond ? ' selected' : '';
$checked  = static fn($cond)=>$cond ? ' checked'  : '';

// ---- table autodetect (handles schemas that don't use "suite"/"benchmark" names) ----
$detect_table = function(PDO $pdo, array $candidates) {
  $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
  $st  = $pdo->prepare($sql);
  foreach ($candidates as $name) {
    $st->execute([$name]);
    if ($st->fetchColumn()) return $name;
  }
  return $candidates[0];
};
$T = [
  'result'    => $detect_table($pdo, ['result','results']),
  'tool'      => $detect_table($pdo, ['tool','tools']),
  'suite'     => $detect_table($pdo, ['suite','benchmark_suite','BenchmarkSuite','suites']),
  'benchmark' => $detect_table($pdo, ['benchmark','benchmarks']),
];

// ----- read filters (GET) -----
$sel_suites      = array_map('intval', $_GET['suites'] ?? []);
$sel_benchmarks  = array_map('intval', $_GET['benchmark_ids'] ?? []);   // <-- MULTI
$sel_tools       = array_values(array_filter((array)($_GET['tools'] ?? $TOOL_WHITELIST)));
$sel_foms        = array_values(array_intersect($FOM_KEYS, (array)($_GET['foms'] ?? $FOM_KEYS)));
if (!$sel_foms) $sel_foms = $FOM_KEYS;

// ----- lookups -----
$benchmarks = [];
$stmt = $pdo->query("SELECT benchmark_id, name FROM `{$T['benchmark']}` ORDER BY name");
foreach ($stmt as $row) $benchmarks[(int)$row['benchmark_id']] = $row['name'];

$all_tools = [];
$ph = str_repeat('?,', count($TOOL_WHITELIST)); $ph = $ph ? substr($ph,0,-1) : "''";
$stmt = $pdo->prepare("SELECT DISTINCT name FROM `{$T['tool']}` WHERE name IN ($ph) ORDER BY name");
$stmt->execute($TOOL_WHITELIST);
$all_tools = array_map(fn($r)=>$r['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
// keep selection order stable by whitelist
$display_tools = array_values(array_filter($TOOL_WHITELIST, fn($t)=>in_array($t, $all_tools, true) && in_array($t, $sel_tools, true)));
// if user cleared everything, default to all available in whitelist
if (!$display_tools) $display_tools = $all_tools;

// suites lookup came from index.php as $all_suites = suites($pdo);
// if none selected, treat as all
$sel_suites = $sel_suites ?: array_map(fn($r)=>(int)$r['suite_id'], $all_suites);
// if no benchmarks selected, treat as all
$sel_benchmarks = $sel_benchmarks ?: array_keys($benchmarks);

// ----- fetch latest result per (suite, benchmark, tool) with filters -----
$where = [];
$params = [];

// suites
if ($sel_suites) {
  $ph = implode(',', array_fill(0, count($sel_suites), '?'));
  $where[] = "s.suite_id IN ($ph)";
  $params = array_merge($params, $sel_suites);
}
// benchmarks (MULTI)
if ($sel_benchmarks) {
  $ph = implode(',', array_fill(0, count($sel_benchmarks), '?'));
  $where[] = "b.benchmark_id IN ($ph)";
  $params = array_merge($params, $sel_benchmarks);
}
// tools
if ($display_tools) {
  $ph = implode(',', array_fill(0, count($display_tools), '?'));
  $where[] = "t.name IN ($ph)";
  $params = array_merge($params, $display_tools);
}

$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$sql = "
  SELECT *
  FROM (
    SELECT
      s.name AS suite_name,
      b.name AS bench_name,
      t.name AS tool_name,
      r.fom1, r.fom2, r.fom3, r.fom4,
      ROW_NUMBER() OVER (
        PARTITION BY r.suite_id, r.benchmark_id, r.tool_id
        ORDER BY r.date DESC, r.result_id DESC
      ) AS rn
    FROM `{$T['result']}` r
    JOIN `{$T['tool']}`      t ON t.tool_id = r.tool_id
    JOIN `{$T['suite']}`     s ON s.suite_id = r.suite_id
    JOIN `{$T['benchmark']}` b ON b.benchmark_id = r.benchmark_id
    $wsql
  ) x
  WHERE rn = 1
  ORDER BY suite_name, bench_name, tool_name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ----- pivot to Suite, Benchmark rows; Tool -> (FOMs) columns -----
$pivot = [];                // [suite][bench][tool] = ['fom1'=>..,'fom2'=>..,'fom3'=>..,'fom4'=>..]
$combo_keys = [];           // list of [suite, bench] to keep order
foreach ($rows as $r) {
  $s = $r['suite_name']; $b = $r['bench_name']; $t = $r['tool_name'];
  if (!isset($pivot[$s])) $pivot[$s] = [];
  if (!isset($pivot[$s][$b])) { $pivot[$s][$b] = []; $combo_keys[] = [$s,$b]; }
  $pivot[$s][$b][$t] = ['fom1'=>$r['fom1'],'fom2'=>$r['fom2'],'fom3'=>$r['fom3'],'fom4'=>$r['fom4']];
}
?>

<div class="card" style="margin-bottom:16px;">
  <!-- FILTER FORM (GET) -->
  <form id="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="results">

    <div class="row3">
      <div>
        <label><strong>Suites (multi)</strong></label>
        <select name="suites[]" multiple size="6">
          <?php foreach ($all_suites as $s): ?>
            <option value="<?=$h($s['suite_id'])?>"<?=$selected(in_array((int)$s['suite_id'],$sel_suites,true))?>><?=$h($s['name'])?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Hold Ctrl/Cmd to select multiple</div>
      </div>

      <div>
        <label><strong>Benchmarks (multi)</strong></label>
        <select name="benchmark_ids[]" multiple size="6">
          <?php foreach ($benchmarks as $bid=>$bn): ?>
            <option value="<?=$h($bid)?>"<?=$selected(in_array($bid,$sel_benchmarks,true))?>><?=$h($bn)?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Leave empty for all benchmarks</div>
      </div>

      <div>
        <label><strong>Tools (multi)</strong></label>
        <select name="tools[]" multiple size="6">
          <?php foreach ($TOOL_WHITELIST as $t): if (!in_array($t,$all_tools,true)) continue; ?>
            <option value="<?=$h($t)?>"<?=$selected(in_array($t,$sel_tools,true))?>><?=$h($t)?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Only whitelisted tools appear if present in DB</div>
      </div>
    </div>

    <div style="margin-top:12px;">
      <strong>FOMs</strong><br>
      <?php foreach ($FOM_KEYS as $f): ?>
        <label style="margin-right:10px;">
          <input type="checkbox" name="foms[]" value="<?=$h($f)?>"<?=$checked(in_array($f,$sel_foms,true))?>> <?=strtoupper($h($f))?>
        </label>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      <button class="btn" type="submit">Apply</button>
      <a class="btn alt" href="index.php?page=results">Reset</a>
    </div>
  </form>

  <!-- EXPORT FORMS (POST) — separate from GET form (no nesting) -->
  <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
    <form class="inline" method="post" action="index.php">
      <input type="hidden" name="action" value="export_csv">
      <input type="hidden" name="csrf" value="<?=$h($CSRF)?>">
      <?php foreach ($sel_suites as $v): ?><input type="hidden" name="suites[]" value="<?=$h($v)?>"><?php endforeach; ?>
      <?php foreach ($sel_benchmarks as $v): ?><input type="hidden" name="benchmark_ids[]" value="<?=$h($v)?>"><?php endforeach; ?>
      <?php foreach ($display_tools as $t): ?><input type="hidden" name="tools[]" value="<?=$h($t)?>"><?php endforeach; ?>
      <?php foreach ($sel_foms as $f): ?><input type="hidden" name="foms[]" value="<?=$h($f)?>"><?php endforeach; ?>
      <button class="btn alt" type="submit">Download CSV</button>
    </form>

    <form class="inline" method="post" action="index.php">
      <input type="hidden" name="action" value="export_latex">
      <input type="hidden" name="csrf" value="<?=$h($CSRF)?>">
      <?php foreach ($sel_suites as $v): ?><input type="hidden" name="suites[]" value="<?=$h($v)?>"><?php endforeach; ?>
      <?php foreach ($sel_benchmarks as $v): ?><input type="hidden" name="benchmark_ids[]" value="<?=$h($v)?>"><?php endforeach; ?>
      <?php foreach ($display_tools as $t): ?><input type="hidden" name="tools[]" value="<?=$h($t)?>"><?php endforeach; ?>
      <?php foreach ($sel_foms as $f): ?><input type="hidden" name="foms[]" value="<?=$h($f)?>"><?php endforeach; ?>
      <button class="btn alt" type="submit">Download LaTeX</button>
    </form>
  </div>
</div>

<?php
// ----- render pivot table -----
if (!$combo_keys): ?>
  <div class="muted">No rows matched the current filters.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th rowspan="2">Suite</th>
        <th rowspan="2">Benchmark</th>
        <?php foreach ($display_tools as $t): ?>
          <th colspan="<?=count($sel_foms)?>"><?=$h($t)?></th>
        <?php endforeach; ?>
      </tr>
      <tr>
        <?php foreach ($display_tools as $t): foreach ($sel_foms as $f): ?>
          <th><?=strtoupper($h($f))?></th>
        <?php endforeach; endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($combo_keys as [$s,$b]): ?>
        <tr>
          <td><?=$h($s)?></td>
          <td><?=$h($b)?></td>
          <?php foreach ($display_tools as $t):
            $vals = $pivot[$s][$b][$t] ?? null;
            foreach ($sel_foms as $f):
              $v = $vals[$f] ?? '';
          ?>
            <td class="mono"><?=$h($v)?></td>
          <?php endforeach; endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
