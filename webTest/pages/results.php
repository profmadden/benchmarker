<?php
// pages/results.php

// ---- config for this page ----
$FOM_KEYS = ['fom1','fom2','fom3','fom4'];

// tiny helpers
$h = static fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$selected = static fn($cond)=>$cond ? ' selected' : '';
$checked  = static fn($cond)=>$cond ? ' checked'  : '';

// ---- canonical tables ----
$T = [
  'result'    => 'result',
  'tool'      => 'tool',
  'suite'     => 'benchmarksuite',
  'benchmark' => 'benchmark',
  'toolrelease' => 'toolrelease',
];

// active tab
$active_tab = $_GET['tab'] ?? 'results';

// ----- read filters (GET) -----
$sel_suites      = array_map('intval', $_GET['suites'] ?? []);
$sel_benchmarks  = array_map('intval', $_GET['benchmark_ids'] ?? []);
$sel_tools       = (array)($_GET['tools'] ?? []);
$sel_foms        = array_values(array_intersect($FOM_KEYS, (array)($_GET['foms'] ?? $FOM_KEYS)));
if (!$sel_foms) $sel_foms = $FOM_KEYS;

// ----- lookups -----
$benchmarks = [];
$stmt = $pdo->query("SELECT benchmark_id, suite_id, name FROM `{$T['benchmark']}` ORDER BY name");
foreach ($stmt as $row) {
  $benchmarks[(int)$row['benchmark_id']] = [
    'name' => $row['name'],
    'suite_id' => (int)$row['suite_id']
  ];
}

// ----- tools: dynamic from DB (for the cascade) -----
$tool_sql = "
  SELECT DISTINCT t.name, r.suite_id, r.benchmark_id
  FROM `{$T['result']}` r
  JOIN `{$T['tool']}` t ON t.tool_id = r.tool_id
";
$stmt = $pdo->query($tool_sql);
$tool_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// map: suite_id → benchmark_id → [tools]
$tools_map = [];
foreach ($tool_rows as $tr) {
  $sid = (int)$tr['suite_id'];
  $bid = (int)$tr['benchmark_id'];
  $tools_map[$sid][$bid][] = $tr['name'];
}

// build tool list for current selection (filtering the Tools multiselect)
$toolset = [];
if ($sel_suites && $sel_benchmarks) {
  foreach ($sel_suites as $sid) {
    foreach ($sel_benchmarks as $bid) {
      foreach ($tools_map[$sid][$bid] ?? [] as $t) {
        $toolset[$t] = true;
      }
    }
  }
}
$all_tools = $toolset ? array_keys($toolset) : array_unique(array_map(fn($r) => $r['name'], $tool_rows));
sort($all_tools);

$display_tools = $sel_tools ? array_values(array_intersect($sel_tools, $all_tools)) : $all_tools;

// suites lookup came from index.php as $all_suites = suites($pdo)
$sel_suites = $sel_suites ?: array_map(fn($r)=>(int)$r['suite_id'], $all_suites);
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
// benchmarks
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

// include result_id so we can flag
$sql = "
  SELECT 
      x.result_id,
    x.suite_id,
    x.benchmark_id,
    x.tool_id,
    x.suite_name,
    x.bench_name,
    x.tool_name,
    x.tool_url,
    x.tool_version,
    x.release_url,
    x.fom1,
    x.fom2,
    x.fom3,
    x.fom4, 
    x.result_url,
    x.text_description
  FROM (
    SELECT
      r.result_id,
      r.suite_id,
      r.benchmark_id,
      r.tool_id,
      CONCAT(s.name, COALESCE(CONCAT(' — ', s.variation), '')) AS suite_name,
      b.name AS bench_name,
      t.name AS tool_name,
      COALESCE(t.URL, '#') AS tool_url,
      COALESCE(tr.tool_release_version, 'v1.0 (default)') AS tool_version,
      COALESCE(tr.URL, '#') AS release_url,
      r.fom1, r.fom2, r.fom3, r.fom4,
      COALESCE(r.URL, '#') AS result_url,
      COALESCE(NULLIF(r.text_description, ''), 'Example: command to reproduce this result.') AS text_description,
      ROW_NUMBER() OVER (
        PARTITION BY r.suite_id, r.benchmark_id, r.tool_id
        ORDER BY r.date DESC, r.result_id DESC
      ) AS rn
    FROM `{$T['result']}` r
    JOIN `{$T['tool']}`      t ON t.tool_id = r.tool_id
    JOIN `{$T['suite']}`     s ON s.suite_id = r.suite_id
    JOIN `{$T['benchmark']}` b ON b.benchmark_id = r.benchmark_id
    LEFT JOIN `{$T['toolrelease']}` tr ON tr.tool_release_id = r.tool_release_id
    $wsql
  ) x
  WHERE rn = 1
  ORDER BY suite_name, bench_name, tool_name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// pivot keyed by Suite, Benchmark, Tool → values + result_id
$pivot = [];
$combo_keys = [];
foreach ($rows as $r) {
  $s = $r['suite_name']; $b = $r['bench_name']; $t = $r['tool_name'];
  if (!isset($pivot[$s])) $pivot[$s] = [];
  if (!isset($pivot[$s][$b])) { $pivot[$s][$b] = []; $combo_keys[] = [$s,$b]; }
  $pivot[$s][$b][$t] = [
    'fom1'=>$r['fom1'],'fom2'=>$r['fom2'],'fom3'=>$r['fom3'],'fom4'=>$r['fom4'],
    'result_id'=>$r['result_id'],
      'text_description' => $r['text_description'],
    'result_url'=>$r['result_url'], 
  ];
}

// ---- flagged counts for current results (for per-tool Flagged + row highlighting) ----
$result_ids = array_values(array_unique(array_map(fn($r)=>(int)$r['result_id'], $rows)));
$flag_counts = [];
if ($result_ids) {
  $ph = implode(',', array_fill(0, count($result_ids), '?'));
  $stmt = $pdo->prepare("SELECT result_id, COUNT(*) AS c FROM flag_records WHERE result_id IN ($ph) GROUP BY result_id");
  $stmt->execute($result_ids);
  foreach ($stmt as $fr) $flag_counts[(int)$fr['result_id']] = (int)$fr['c'];
}

// ---- flags list for Flags tab (filtered same as results) ----
$flags_rows = [];
$flags_total = 0;
{
  $flags_sql = "
    SELECT
      fr.flag_id, fr.result_id, fr.description, fr.created_at,
      t.name AS tool_name,
      CONCAT(s.name, COALESCE(CONCAT(' — ', s.variation), '')) AS suite_name,
      b.name AS bench_name,
      r.fom1, r.fom2, r.fom3, r.fom4
    FROM flag_records fr
    JOIN `{$T['result']}` r ON r.result_id = fr.result_id
    JOIN `{$T['tool']}` t ON t.tool_id = r.tool_id
    JOIN `{$T['suite']}` s ON s.suite_id = r.suite_id
    JOIN `{$T['benchmark']}` b ON b.benchmark_id = r.benchmark_id
    $wsql
    ORDER BY fr.created_at DESC, fr.flag_id DESC
    LIMIT 500
  ";
  $stmt = $pdo->prepare($flags_sql);
  $stmt->execute($params);
  $flags_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $cnt_sql = "
    SELECT COUNT(*) FROM flag_records fr
    JOIN `{$T['result']}` r ON r.result_id = fr.result_id
    JOIN `{$T['tool']}` t ON t.tool_id = r.tool_id
    JOIN `{$T['suite']}` s ON s.suite_id = r.suite_id
    JOIN `{$T['benchmark']}` b ON b.benchmark_id = r.benchmark_id
    $wsql
  ";
  $stmt = $pdo->prepare($cnt_sql);
  $stmt->execute($params);
  $flags_total = (int)$stmt->fetchColumn();
}

// optional highlight when redirected after save
$last_flag_id = isset($_GET['last_flag_id']) ? (int)$_GET['last_flag_id'] : 0;
?>

<!-- Toggle + flag color styles -->
<style>
  .switch { position: relative; display: inline-block; width: 42px; height: 24px; }
  .switch input { opacity: 0; width: 0; height: 0; }
  .switch .slider {
    position: absolute; inset: 0; cursor: pointer; background: #cfd4da;
    transition: .2s; border-radius: 999px;
  }
  .switch .slider:before {
    content: ""; position: absolute; height: 18px; width: 18px; left: 3px; top: 3px;
    background: #fff; transition: .2s; border-radius: 999px; box-shadow: 0 1px 2px rgba(0,0,0,.2);
  }
  .switch input:checked + .slider { background: #0d6efd; }
  .switch input:checked + .slider:before { transform: translateX(18px); }
  .switch input:disabled + .slider { background:#e5e7eb; cursor:not-allowed; }

  table.flag-matrix tr.flag-yellow { background: #fff8e1; }
  table.flag-matrix tr.flag-orange { background: #ffeeda; }
  table.flag-matrix tr.flag-red    { background: #ffe5e5; }

    /* --- Added: Benchmarker Enhanced Result UI (Rahul & Tejas) --- */

  .tool-link {
    font-weight: 600;
    color: #0d6efd;
    text-decoration: none;
  }
  .tool-link:hover {
    text-decoration: underline;
  }

  .version-text a {
    color: #6c757d;
    text-decoration: none;
    font-style: italic;
    font-size: 13px;
  }
  .version-text.placeholder a {
    color: #9a9a9a;
  }

  .fom-cell .fom-link {
    color: #007bff;
    font-weight: 500;
    text-decoration: none;
  }
  .fom-cell .fom-link:hover {
    text-decoration: underline;
  }

  .info-icon {
    font-size: 12px;
    margin-left: 4px;
    cursor: help;
    color: #007bff;
  }

  .placeholder {
    color: #999;
    font-style: italic;
  }

    .info-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }
  .info-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 16px 20px;
    max-width: 480px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.25);
  }
  .info-modal-content h3 {
    margin-top: 0;
    color: #333;
  }
  .info-modal-content pre {
    background: #f6f8fa;
    padding: 8px;
    border-radius: 6px;
    white-space: pre-wrap;
    font-size: 14px;
  }
  .info-close {
    margin-top: 10px;
    padding: 6px 12px;
    background: #0d6efd;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
</style>
<div id="infoModal" class="info-modal">
  <div class="info-modal-content">
    <h3>Result Description</h3>
    <pre id="infoText">...</pre>
    <button class="info-close">Close</button>
  </div>
</div>


<div class="card" style="margin-bottom:16px;">
  <form id="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="results">

    <div class="row4" style="display: grid; grid-template-columns: repeat(4, 1fr);gap: 12px;">
      <div>
        <label><strong>Suite</strong></label>
        <select id="suiteSelect" name="suite_id">
          <option value="">-- Select Suite --</option>
          <?php
            $suite_names = [];
            foreach ($all_suites as $s) {
              if (!in_array($s['name'], $suite_names, true)) {
                $suite_names[] = $s['name'];
                echo '<option value="'.$h($s['name']).'"'.($s['name'] === ($_GET['suite_id'] ?? '') ? ' selected' : '').'>'.$h($s['name']).'</option>';
              }
            }
          ?>
        </select>
      </div>

      <div>
        <label><strong>Variation(s)</strong></label>
        <select id="variationSelect" name="suites[]" multiple size="6"></select>
        <div class="muted">Hold Ctrl/Cmd to select multiple</div>
      </div>

      <div>
        <label><strong>Benchmarks (multi)</strong></label>
        <select id="benchmarkSelect" name="benchmark_ids[]" multiple size="6"></select>
      </div>

      <div>
        <label><strong>Tools (multi)</strong></label>
        <select id="toolSelect" name="tools[]" multiple size="6">
          <?php foreach ($all_tools as $t): ?>
            <option value="<?=$h($t)?>"<?=$selected(in_array($t,$sel_tools,true))?>><?=$h($t)?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Filtered dynamically from DB</div>
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
</div>

<script>
  // ===== data from PHP =====
  const allSuites = <?= json_encode(array_map(
    fn($s)=>['suite_id'=>(int)$s['suite_id'],'name'=>$s['name'],'variation'=>$s['variation']],
    $all_suites
  )) ?>;
  const allBenchmarks = <?= json_encode(array_map(
    fn($id,$b)=>['benchmark_id'=>(int)$id,'name'=>$b['name'],'suite_id'=>(int)$b['suite_id']],
    array_keys($benchmarks), $benchmarks
  )) ?>;
  const toolsMap = <?= json_encode($tools_map) ?>; // {suite_id:{benchmark_id:[tool,...]}}
  const phpSelSuites = <?= json_encode($sel_suites) ?>;
  const phpSelBench  = <?= json_encode($sel_benchmarks) ?>;
  const phpSelTools  = <?= json_encode(array_values($sel_tools)) ?>;
  const phpSuiteName = <?= json_encode($_GET['suite_id'] ?? '') ?>;

  const suiteSelect = document.getElementById('suiteSelect');
  const variationSelect = document.getElementById('variationSelect');
  const benchmarkSelect = document.getElementById('benchmarkSelect');
  const toolSelect = document.getElementById('toolSelect');

  function setMultiSelected(selectEl, values) {
    const wanted = new Set(values.map(v => String(v)));
    Array.from(selectEl.options).forEach(opt => { opt.selected = wanted.has(String(opt.value)); });
  }
  function populateVariations(suiteName) {
    variationSelect.innerHTML = '';
    const variations = allSuites.filter(s => s.name === suiteName);
    variations.forEach(s => {
      const opt = document.createElement('option');
      opt.value = String(s.suite_id);
      opt.textContent = s.variation || '(default)';
      variationSelect.appendChild(opt);
    });
    if (phpSelSuites.length) setMultiSelected(variationSelect, phpSelSuites);
    if (!variationSelect.selectedOptions.length && variations.length === 1) {
      variationSelect.options[0].selected = true;
    }
    populateBenchmarksFromVariations();
  }
  function populateBenchmarksFromVariations() {
    benchmarkSelect.innerHTML = '';
    const selectedSuiteIds = Array.from(variationSelect.selectedOptions).map(o => parseInt(o.value,10));
    if (!selectedSuiteIds.length) { toolSelect.innerHTML = ''; return; }
    const matching = allBenchmarks.filter(b => selectedSuiteIds.includes(parseInt(b.suite_id,10)));
    matching.forEach(b => {
      const opt = document.createElement('option');
      opt.value = String(b.benchmark_id);
      opt.textContent = b.name;
      benchmarkSelect.appendChild(opt);
    });
    if (phpSelBench.length) setMultiSelected(benchmarkSelect, phpSelBench);
    populateToolsFromBenchmarks();
  }
  function populateToolsFromBenchmarks() {
    toolSelect.innerHTML = '';
    const selectedSuiteIds = Array.from(variationSelect.selectedOptions).map(o => parseInt(o.value,10));
    const selectedBenchIds = Array.from(benchmarkSelect.selectedOptions).map(o => parseInt(o.value,10));
    if (!selectedSuiteIds.length || !selectedBenchIds.length) return;
    const toolSet = new Set();
    selectedSuiteIds.forEach(sid => {
      selectedBenchIds.forEach(bid => {
        const arr = (toolsMap[String(sid)] && toolsMap[String(sid)][String(bid)]) || [];
        arr.forEach(t => toolSet.add(t));
      });
    });
    [...toolSet].sort().forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t;
      toolSelect.appendChild(opt);
    });
    if (phpSelTools.length) setMultiSelected(toolSelect, phpSelTools);
  }
  suiteSelect.addEventListener('change', e => populateVariations(e.target.value));
  variationSelect.addEventListener('change', populateBenchmarksFromVariations);
  benchmarkSelect.addEventListener('change', populateToolsFromBenchmarks);
  if (!phpSuiteName && phpSelSuites.length) {
    const firstSid = phpSelSuites[0];
    const found = allSuites.find(s => s.suite_id === firstSid);
    if (found) suiteSelect.value = found.name;
  } else {
    suiteSelect.value = phpSuiteName;
  }
  if (suiteSelect.value) populateVariations(suiteSelect.value);

  //Added <icon> button info JS
  document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('infoModal');
  const infoText = document.getElementById('infoText');
  const closeBtn = modal.querySelector('.info-close');

  // Hover: small tooltip (uses title)
  document.querySelectorAll('.info-icon').forEach(icon => {
    icon.addEventListener('mouseenter', e => {
      const desc = e.target.getAttribute('data-desc');
      e.target.setAttribute('title', desc);
    });

    // Click: open modal for long description
    icon.addEventListener('click', e => {
      const desc = e.target.getAttribute('data-desc');
      infoText.textContent = desc || 'No description available.';
      modal.style.display = 'flex';
    });
  });

  // Close modal
  closeBtn.addEventListener('click', () => modal.style.display = 'none');
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
  });
});

</script>

<!-- Sub-tabs -->
<div style="margin: -8px 0 12px 0; display:flex; gap:8px;">
  <?php
    $q = $_GET; unset($q['tab']);
    $base = 'index.php?'.http_build_query(array_merge($q,['page'=>'results']));
    $uResults = $base.'&tab=results';
    $uFlags   = $base.'&tab=flags';
  ?>
  <a class="btn <?= $active_tab==='results'?'':'alt' ?>" href="<?=$h($uResults)?>">Results</a>
<!--  <a class="btn <?= $active_tab==='flags'  ?'':'alt' ?>" href="<?=$h($uFlags)?>">Flags (<?=$h($flags_total)?>)</a> -->
</div>

<?php if ($active_tab==='flags'): ?>

  <?php if (!$flags_rows): ?>
    <div class="muted">No flags saved for the current filters.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Suite</th>
          <th>Benchmark</th>
          <th>Tool</th>
          <th>Result&nbsp;ID</th>
          <th>FOM1</th><th>FOM2</th><th>FOM3</th><th>FOM4</th>
          <th>Description</th>
          <th>Resolve</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($flags_rows as $fr): ?>
          <tr <?= $last_flag_id && $last_flag_id==(int)$fr['flag_id'] ? 'style="outline:2px solid #448aff;"' : '' ?>>
            <td class="mono"><?=$h($fr['created_at'])?></td>
            <td><?=$h($fr['suite_name'])?></td>
            <td><?=$h($fr['bench_name'])?></td>
            <td><?=$h($fr['tool_name'])?></td>
            <td class="mono"><?=$h($fr['result_id'])?></td>
            <td class="mono"><?=$h($fr['fom1'])?></td>
            <td class="mono"><?=$h($fr['fom2'])?></td>
            <td class="mono"><?=$h($fr['fom3'])?></td>
            <td class="mono"><?=$h($fr['fom4'])?></td>
            <td><?=$h($fr['description'])?></td>
            <td>
              <form method="post" action="index.php" class="inline">
                <input type="hidden" name="action" value="resolve_flag">
                <input type="hidden" name="csrf" value="<?=$h($CSRF)?>">
                <input type="hidden" name="flag_id" value="<?=$h($fr['flag_id'])?>">
                <input type="hidden" name="redir" value="results">
                <button type="submit" class="btn alt">Resolve</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else: ?>

<?php if (!$combo_keys): ?>
  <div class="muted">No rows matched the current filters.</div>
<?php else: ?>

<!-- EXPORT BUTTONS (clean matrix; no flags/toggles in files) -->
<div style="margin:12px 0; display:flex; gap:8px; flex-wrap:wrap;">
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

<!-- One table; per-tool Flagged + Toggle; one shared Description per row -->
<form method="post" action="index.php" style="margin-top:12px;" id="flagForm">
  <input type="hidden" name="action" value="save_flags">
  <input type="hidden" name="csrf" value="<?=$h($CSRF)?>">
  <input type="hidden" name="only_rowkey" id="only_rowkey" value="">
  <input type="hidden" name="tab_after" value="flags">

  <table class="flag-matrix">
    <thead>
      <tr>
        <th rowspan="2">Suite</th>
        <th rowspan="2">Benchmark</th>
        <?php foreach ($display_tools as $t): ?>
          <?php
              $sample = null;
              foreach ($rows as $r) {
                if ($r['tool_name'] === $t) { $sample = $r; break; }
              }
              $tool_url = $sample['tool_url'] ?? '#';
              $rel_url  = $sample['release_url'] ?? '#';
              $tool_ver = $sample['tool_version'] ?? 'v1.0 (default)';
              $is_default = str_contains($tool_ver, 'default');
            ?>
            <!-- Hyperlink to Tool Header -->
          <th colspan="<?=count($sel_foms)?>">
                <a href="<?=$h($tool_url)?>" target="_blank" class="tool-link"><?=$h($t)?></a><br>
                <small class="version-text <?=$is_default ? 'placeholder' : ''?>">
                  <a href="<?=$h($rel_url)?>" target="_blank"><?=$h($tool_ver)?></a>
                </small>
          </th>
        <?php endforeach; ?>
        <!-- <th rowspan="2">Description (applies to all toggled tools)</th> -->
      </tr>
      <tr>
        <?php foreach ($display_tools as $t): ?>
          <?php foreach ($sel_foms as $f): ?>
            <th><?=strtoupper($h($f))?></th>
          <?php endforeach; ?>
          <!-- Added to remove flag -->
          <?php if(false): ?>
            <th>Flagged</th>
            <th>Flag</th>
          <?php endif; ?>
          <!-- Ended added flag here -->
        <?php endforeach; ?>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($combo_keys as [$s,$b]): ?>
        <?php
          // gather per-tool rids + counts for this row
          $rowRids = [];
          $rowCounts = [];
          foreach ($display_tools as $tn) {
            $rid = (int)($pivot[$s][$b][$tn]['result_id'] ?? 0);
            if ($rid) {
              $rowRids[$tn] = $rid;
              $rowCounts[$tn] = $flag_counts[$rid] ?? 0;
            } else {
              $rowRids[$tn] = 0;
              $rowCounts[$tn] = 0;
            }
          }
          $rowMaxFlag = $rowCounts ? max($rowCounts) : 0;
          $flagClass = '';
          if     ($rowMaxFlag >= 5) $flagClass = 'flag-red';
          elseif ($rowMaxFlag >= 3) $flagClass = 'flag-orange';
          elseif ($rowMaxFlag >= 1) $flagClass = 'flag-yellow';

          // unique key for this row (so one shared description can be posted)
          $rowKey = md5($s.'|'.$b);
        ?>
        <tr class="<?=$flagClass?>">
          <td><?=$h($s)?></td>
          <td><?=$h($b)?></td>

          <?php foreach ($display_tools as $t): ?>
            <?php $vals = $pivot[$s][$b][$t] ?? null; $rid = (int)($vals['result_id'] ?? 0); ?>
            <?php foreach ($sel_foms as $f): ?>
              <?php
                $val  = $vals[$f] ?? '';
                $rurl = $vals['result_url'] ?? '#';
                $desc = trim($vals['text_description'] ?? '');
              ?>
              <td class="mono fom-cell">
                <?php if ($val !== ''): ?>
		  <?php if (strlen($rurl) < 4): ?>
		   <?=$h($val) ?>
		  <?php else: ?>
                    <a href="<?=$h($rurl)?>" target="_blank" class="fom-link"><?=$h($val)?></a>
		  <?php endif; ?>
                  <span class="info-icon" data-desc="<?=$h($desc)?>" title="<?=$h($desc)?>">&#9432;</span>
                <?php else: ?>
                  <span class="placeholder">—</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <!-- Added flag td data removed -->
            <?php if(false): ?>
              <td class="mono"><?= (int)($flag_counts[$rid] ?? 0) ?></td>
              <td>
                <?php if ($rid): ?>
                  <input type="hidden" name="rid_rowkey[<?=$rid?>]" value="<?=$rowKey?>">
                  <label class="switch" title="Flag latest <?=$h($t)?> result">
                    <input type="checkbox"
                      class="tool-flag-toggle"
                      data-rowkey="<?=$rowKey?>"
                      name="flags[<?=$rid?>]"
                      value="1">
                    <span class="slider"></span>
                  </label>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <!-- Flag removed td data -->

          <?php endforeach; ?>
          <!-- Description column earlier present now making it false -->
          <?php if(false): ?>
          <td class="desc-cell">
            <div class="desc-wrap" style="display:none;">
              <input type="text"
                     class="desc-input"
                     name="row_desc[<?=$rowKey?>]"
                     maxlength="1000"
                     placeholder="Enter description…">
              <div class="row-actions" style="margin-top:6px; display:flex; gap:6px;">
                <button type="submit" class="btn small row-done" data-rowkey="<?=$rowKey?>">Done</button>
                <button type="button" class="btn alt small cancel-desc" data-rowkey="<?=$rowKey?>">Cancel</button>
              </div>
            </div>
          </td>
          <?php endif; ?>

        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</form>
<?php endif; ?>
<?php endif; ?>

<script>
// show description only if *any* tool toggle in the row is on
function anyCheckedInRow(rowKey) {
  return !!document.querySelector('.tool-flag-toggle[data-rowkey="'+rowKey+'"]:checked');
}
function updateDescVisibility(rowKey) {
  const wrap = document.querySelector('.desc-cell .desc-wrap input[name="row_desc['+rowKey+']"]')?.closest('.desc-wrap');
  if (!wrap) return;
  wrap.style.display = anyCheckedInRow(rowKey) ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tool-flag-toggle').forEach(cb => {
    cb.addEventListener('change', e => {
      const rk = e.target.getAttribute('data-rowkey');
      updateDescVisibility(rk);
    });
  });

  // Row 'Done': submit only this row's toggles (server filters by rowkey)
  const onlyRowKey = document.getElementById('only_rowkey');
  document.querySelectorAll('.row-done').forEach(btn => {
    btn.addEventListener('click', () => {
      const rk = btn.getAttribute('data-rowkey');
      onlyRowKey.value = rk || '';
    });
  });

  // Cancel: turn off all toggles in that row & hide description
  document.querySelectorAll('.cancel-desc').forEach(btn => {
    btn.addEventListener('click', e => {
      const rk = btn.getAttribute('data-rowkey');
      document.querySelectorAll('.tool-flag-toggle[data-rowkey="'+rk+'"]').forEach(cb => { cb.checked = false; });
      updateDescVisibility(rk);
      const inp = document.querySelector('input[name="row_desc['+rk+']"]');
      if (inp) inp.value = '';
    });
  });
});
</script>
