<?php
if (isset($_POST['action']) && in_array($_POST['action'], ['export_csv','export_latex'], true)) {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo "Invalid CSRF token";
    exit;
  }

  // ---- canonical tables ----
  $T = [
    'result'    => 'result',
    'tool'      => 'tool',
    'suite'     => 'benchmarksuite',
    'benchmark' => 'benchmark',
  ];

  // inputs
  $FOM_KEYS = ['fom1','fom2','fom3','fom4'];
  $suite_ids     = array_map('intval', $_POST['suites'] ?? []);
  $benchmark_ids = array_map('intval', $_POST['benchmark_ids'] ?? []);
  $tools         = array_values(array_filter((array)($_POST['tools'] ?? [])));
  $foms          = array_values(array_intersect($FOM_KEYS, (array)($_POST['foms'] ?? $FOM_KEYS)));
  if (!$foms) $foms = $FOM_KEYS;

  // lookups for defaults
  $suites_by_id = [];
  $stmt = $pdo->query("SELECT suite_id, name FROM `{$T['suite']}`");
  foreach ($stmt as $r) $suites_by_id[(int)$r['suite_id']] = $r['name'];
  if (!$suite_ids) $suite_ids = array_keys($suites_by_id);

  $bench_by_id = [];
  $stmt = $pdo->query("SELECT benchmark_id, name FROM `{$T['benchmark']}`");
  foreach ($stmt as $r) $bench_by_id[(int)$r['benchmark_id']] = $r['name'];
  if (!$benchmark_ids) $benchmark_ids = array_keys($bench_by_id);

  // filters
  $where = [];
  $params = [];

  if ($suite_ids) {
    $ph = implode(',', array_fill(0, count($suite_ids), '?'));
    $where[] = "s.suite_id IN ($ph)";
    $params = array_merge($params, $suite_ids);
  }
  if ($benchmark_ids) {
    $ph = implode(',', array_fill(0, count($benchmark_ids), '?'));
    $where[] = "b.benchmark_id IN ($ph)";
    $params = array_merge($params, $benchmark_ids);
  }
  if ($tools) {
    $ph = implode(',', array_fill(0, count($tools), '?'));
    $where[] = "t.name IN ($ph)";
    $params = array_merge($params, $tools);
  }
  $wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  $sql = "
    SELECT *
    FROM (
      SELECT
        CONCAT(s.name, COALESCE(CONCAT(' — ', s.variation), '')) AS suite_name,
        b.name AS bench_name,
        t.name AS tool_name,
        r.fom1, r.fom2, r.fom3, r.fom4,
        ROW_NUMBER() OVER (
        PARTITION BY r.suite_id, r.benchmark_id, r.tool_id
        ORDER BY
          CASE WHEN r.run_version REGEXP '^[0-9]{8}$' THEN 0 ELSE 1 END,
          CASE WHEN r.run_version REGEXP '^[0-9]{8}$' THEN r.run_version ELSE '' END DESC,
          r.date DESC,
          r.result_id DESC
      ) AS rn
      FROM `result` r
      JOIN `tool`      t ON t.tool_id = r.tool_id
      JOIN `benchmarksuite`     s ON s.suite_id = r.suite_id
      JOIN `benchmark` b ON b.benchmark_id = r.benchmark_id
      $wsql
    ) x
    WHERE rn = 1
    ORDER BY suite_name, bench_name, tool_name
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // pivot
  $pivot = []; $keys = [];
  foreach ($rows as $r) {
    $s = $r['suite_name']; $b = $r['bench_name']; $t = $r['tool_name'];
    if (!isset($pivot[$s])) $pivot[$s] = [];
    if (!isset($pivot[$s][$b])) { $pivot[$s][$b] = []; $keys[] = [$s,$b]; }
    $pivot[$s][$b][$t] = ['fom1'=>$r['fom1'],'fom2'=>$r['fom2'],'fom3'=>$r['fom3'],'fom4'=>$r['fom4']];
  }

  $tools_order = $tools;

  if ($_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="suites_results.csv"'); // FIXED

    $out = fopen('php://output', 'w');
    $header = ['Suite','Benchmark'];
    foreach ($tools_order as $t) foreach ($foms as $f) $header[] = "$t ".strtoupper($f);
    fputcsv($out, $header);

    foreach ($keys as [$s,$b]) {
      $row = [$s,$b];
      foreach ($tools_order as $t) {
        $vals = $pivot[$s][$b][$t] ?? null;
        foreach ($foms as $f) $row[] = $vals[$f] ?? '';
      }
      fputcsv($out, $row);
    }
    fclose($out);
    exit;
  }

  if ($_POST['action'] === 'export_latex') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="suites_results.tex"'); // FIXED

    $cols = 2 + count($tools_order) * count($foms);
    $colspec = str_repeat('c', $cols);
    $latex  = "% Auto-generated from Suites DB\n";
    $latex .= "\\begin{tabular}{$colspec}\n\\hline\n";
    $latex .= "\\multicolumn{1}{c}{Suite} & \\multicolumn{1}{c}{Benchmark}";
    foreach ($tools_order as $t) {
      $span = count($foms);
      $t = str_replace(['&','%','_','#','$','{','}','~','^','\\'], ['\\&','\\%','\\_','\\#','\\$','\\{','\\}','\\textasciitilde{}','\\^{}','\\textbackslash{}'], $t);
      $latex .= " & \\multicolumn{{$span}}{c}{{$t}}";
    }
    $latex .= " \\\\\n\\hline\n";
    $second = [];
    foreach ($tools_order as $_) foreach ($foms as $f) $second[] = strtoupper($f);
    $latex .= "Suite & Benchmark & ".implode(' & ', $second)." \\\\\n\\hline\n";

    foreach ($keys as [$s,$b]) {
      $row = [$s,$b];
      foreach ($tools_order as $t) {
        $vals = $pivot[$s][$b][$t] ?? null;
        foreach ($foms as $f) $row[] = $vals[$f] ?? '';
      }
      $row = array_map(fn($x)=>str_replace(['&','%','_','#','$','{','}','~','^','\\'], ['\\&','\\%','\\_','\\#','\\$','\\{','\\}','\\textasciitilde{}','\\^{}','\\textbackslash{}'], (string)$x), $row);
      $latex .= implode(' & ', $row)." \\\\\n";
    }
    $latex .= "\\hline\n\\end{tabular}\n";
    echo $latex;
    exit;
  }
}

// -------- Save flags (supports multi-tool per row with one shared description) --------
if (isset($_POST['action']) && $_POST['action'] === 'save_flags') {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo "Invalid CSRF token";
    exit;
  }

  // Inputs
  $flags       = $_POST['flags']       ?? []; // flags[result_id] = "1"
  $ridRowKey   = $_POST['rid_rowkey']  ?? []; // rid_rowkey[result_id] = rowKey
  $rowDescs    = $_POST['row_desc']    ?? []; // row_desc[rowKey] = "desc"
  $legacyDescs = $_POST['desc']        ?? []; // (back-compat)
  $onlyRowKey  = isset($_POST['only_rowkey']) ? (string)$_POST['only_rowkey'] : '';
  $tabAfter    = $_POST['tab_after'] ?? '';

  // If 'Done' was pressed on one row, keep only those flags for that row
  if ($onlyRowKey !== '') {
    $flags = array_filter(
      $flags,
      function($on, $rid) use ($ridRowKey, $onlyRowKey) {
        return isset($ridRowKey[$rid]) && $ridRowKey[$rid] === $onlyRowKey;
      },
      ARRAY_FILTER_USE_BOTH
    );
  }

  if (!$flags) {
    $_SESSION['flash'] = 'No flags to save.';
    $redir = 'index.php?page=results'.($tabAfter==='flags' ? '&tab=flags' : '');
    header('Location: '.$redir);
    exit;
  }

  $verifyStmt = $pdo->prepare("SELECT 1 FROM result WHERE result_id = ?");
  $insStmt    = $pdo->prepare("INSERT INTO flag_records (result_id, description) VALUES (?, ?)");

  $saved = 0;
  foreach ($flags as $rid => $on) {
    $rid = (int)$rid;
    if ($rid <= 0) continue;

    $verifyStmt->execute([$rid]);
    if (!$verifyStmt->fetchColumn()) continue;

    // prefer row-level shared description; fall back to per-rid (legacy)
    $rowKey = $ridRowKey[$rid] ?? '';
    $desc   = '';
    if ($rowKey !== '' && isset($rowDescs[$rowKey])) {
      $desc = trim((string)$rowDescs[$rowKey]);
    } else {
      $desc = trim((string)($legacyDescs[$rid] ?? ''));
    }
    if (function_exists('mb_substr')) {
      $desc = mb_substr($desc, 0, 1000, 'UTF-8');
    } else {
      $desc = substr($desc, 0, 1000);
    }

    $insStmt->execute([$rid, $desc]);
    $saved++;
  }

  $_SESSION['flash'] = $saved ? "Saved $saved flag(s)." : "No valid flags saved.";
  $redir = 'index.php?page=results'.($tabAfter==='flags' ? '&tab=flags' : '');
  header('Location: '.$redir);
  exit;
}


// -------- Resolve (delete) a single flag from flag_records --------
if (isset($_POST['action']) && $_POST['action'] === 'resolve_flag') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo "Invalid CSRF token";
    exit;
  }

  $flag_id = isset($_POST['flag_id']) ? (int)$_POST['flag_id'] : 0;
  if ($flag_id > 0) {
    $stmt = $pdo->prepare("DELETE FROM flag_records WHERE flag_id = ?");
    $stmt->execute([$flag_id]);
    $_SESSION['flash'] = "Flag #$flag_id cleared.";
  } else {
    $_SESSION['flash_err'] = "Invalid flag id.";
  }
  header('Location: index.php?page=flags');
  exit;
}
