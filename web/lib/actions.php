<?php
// ========================
// EXPORT CSV / LaTeX (original section)
// ========================
if (isset($_POST['action']) && in_array($_POST['action'], ['export_csv','export_latex'], true)) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid CSRF token"; exit;
  }

  $T = [
    'result'    => 'result',
    'tool'      => 'tool',
    'suite'     => 'benchmarksuite',
    'benchmark' => 'benchmark',
  ];

  $FOM_KEYS = ['fom1','fom2','fom3','fom4'];
  $suite_ids     = array_map('intval', $_POST['suites'] ?? []);
  $benchmark_ids = array_map('intval', $_POST['benchmark_ids'] ?? []);
  $tools         = array_values(array_filter((array)($_POST['tools'] ?? [])));
  $foms          = array_values(array_intersect($FOM_KEYS, (array)($_POST['foms'] ?? $FOM_KEYS)));
  if (!$foms) $foms = $FOM_KEYS;

  $suites_by_id = [];
  $stmt = $pdo->query("SELECT suite_id, name FROM `{$T['suite']}`");
  foreach ($stmt as $r) $suites_by_id[(int)$r['suite_id']] = $r['name'];
  if (!$suite_ids) $suite_ids = array_keys($suites_by_id);

  $bench_by_id = [];
  $stmt = $pdo->query("SELECT benchmark_id, name FROM `{$T['benchmark']}`");
  foreach ($stmt as $r) $bench_by_id[(int)$r['benchmark_id']] = $r['name'];
  if (!$benchmark_ids) $benchmark_ids = array_keys($bench_by_id);

  $where = []; $params = [];
  if ($suite_ids)     { $ph = implode(',', array_fill(0, count($suite_ids), '?'));     $where[] = "s.suite_id IN ($ph)";     $params = array_merge($params, $suite_ids); }
  if ($benchmark_ids) { $ph = implode(',', array_fill(0, count($benchmark_ids), '?')); $where[] = "b.benchmark_id IN ($ph)"; $params = array_merge($params, $benchmark_ids); }
  if ($tools)         { $ph = implode(',', array_fill(0, count($tools), '?'));         $where[] = "t.name IN ($ph)";         $params = array_merge($params, $tools); }
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
      JOIN `tool`           t ON t.tool_id = r.tool_id
      JOIN `benchmarksuite` s ON s.suite_id = r.suite_id
      JOIN `benchmark`      b ON b.benchmark_id = r.benchmark_id
      $wsql
    ) x
    WHERE rn = 1
    ORDER BY suite_name, bench_name, tool_name
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    header('Content-Disposition: attachment; filename="suites_results.csv"');
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
    header('Content-Disposition: attachment; filename="suites_results.tex"');
    $cols = 2 + count($tools_order) * count($foms);
    $colspec = str_repeat('c', $cols);
    $latex  = "% Auto-generated from Suites DB\n";
    $latex .= "\\begin{tabular}{$colspec}\n\\hline\n";
    $latex .= "\\multicolumn{1}{c}{Suite} & \\multicolumn{1}{c}{Benchmark}";
    foreach ($tools_order as $t) {
      $span = count($foms);
      $t = str_replace(['&','%','_','#','$','{','}','~','^','\\'],
                       ['\\&','\\%','\\_','\\#','\\$','\\{','\\}','\\textasciitilde{}','\\^{}','\\textbackslash{}'], $t);
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
      $row = array_map(fn($x)=>str_replace(['&','%','_','#','$','{','}','~','^','\\'],
                                           ['\\&','\\%','\\_','\\#','\\$','\\{','\\}','\\textasciitilde{}','\\^{}','\\textbackslash{}'], (string)$x), $row);
      $latex .= implode(' & ', $row)." \\\\\n";
    }
    $latex .= "\\hline\n\\end{tabular}\n";
    echo $latex;
    exit;
  }
}

// ========================
// SAVE FLAGS (original)
// ========================
if (isset($_POST['action']) && $_POST['action'] === 'save_flags') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid CSRF token"; exit;
  }
  $flags       = $_POST['flags']       ?? [];
  $ridRowKey   = $_POST['rid_rowkey']  ?? [];
  $rowDescs    = $_POST['row_desc']    ?? [];
  $legacyDescs = $_POST['desc']        ?? [];
  $onlyRowKey  = isset($_POST['only_rowkey']) ? (string)$_POST['only_rowkey'] : '';
  $tabAfter    = $_POST['tab_after'] ?? '';

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
    header('Location: '.$redir); exit;
  }

  $verifyStmt = $pdo->prepare("SELECT 1 FROM result WHERE result_id = ?");
  $insStmt    = $pdo->prepare("INSERT INTO flag_records (result_id, description) VALUES (?, ?)");

  $saved = 0;
  foreach ($flags as $rid => $on) {
    $rid = (int)$rid; if ($rid <= 0) continue;
    $verifyStmt->execute([$rid]);
    if (!$verifyStmt->fetchColumn()) continue;

    $rowKey = $ridRowKey[$rid] ?? '';
    $desc = $rowKey !== '' && isset($rowDescs[$rowKey]) ? trim((string)$rowDescs[$rowKey]) : trim((string)($legacyDescs[$rid] ?? ''));
    if (function_exists('mb_substr')) $desc = mb_substr($desc, 0, 1000, 'UTF-8'); else $desc = substr($desc, 0, 1000);

    $insStmt->execute([$rid, $desc]); $saved++;
  }

  $_SESSION['flash'] = $saved ? "Saved $saved flag(s)." : "No valid flags saved.";
  $redir = 'index.php?page=results'.($tabAfter==='flags' ? '&tab=flags' : '');
  header('Location: '.$redir); exit;
}

// ========================
// RESOLVE FLAG (original)
// ========================
if (isset($_POST['action']) && $_POST['action'] === 'resolve_flag') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid CSRF token"; exit;
  }
  $flag_id = isset($_POST['flag_id']) ? (int)$_POST['flag_id'] : 0;
  if ($flag_id > 0) {
    $stmt = $pdo->prepare("DELETE FROM flag_records WHERE flag_id = ?");
    $stmt->execute([$flag_id]);
    $_SESSION['flash'] = "Flag #$flag_id cleared.";
  } else {
    $_SESSION['flash_err'] = "Invalid flag id.";
  }
  header('Location: index.php?page=flags'); exit;
}

// ========================
// ADD / UPDATE: SUITE  (store lowercase names)
// ========================
if (isset($_POST['action']) && in_array($_POST['action'], ['add_suite','update_suite'], true)) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF token"; exit; }

  $name            = isset($_POST['name']) ? norm_name($_POST['name']) : null; // LOWERCASE
  $variation       = str_or_null($_POST['variation'] ?? '');
  $date            = str_or_null($_POST['date'] ?? '');
  $url_benchmarks  = str_or_null($_POST['url_benchmarks'] ?? '');
  $url_evaluator   = str_or_null($_POST['url_evaluator'] ?? '');
  $fom1_label      = str_or_null($_POST['fom1_label'] ?? '');
  $fom2_label      = str_or_null($_POST['fom2_label'] ?? '');
  $fom3_label      = str_or_null($_POST['fom3_label'] ?? '');
  $fom4_label      = str_or_null($_POST['fom4_label'] ?? '');
  $text_description= str_or_null($_POST['text_description'] ?? '');

  if (!$name) { $_SESSION['flash_err']="Suite name is required."; header('Location: index.php?page=manage'); exit; }

  if ($_POST['action']==='add_suite') {
    // prevent duplicate by lowercase name
    $exists = fetchOne($pdo,"SELECT suite_id FROM benchmarksuite WHERE name=? LIMIT 1",[$name]);
    if ($exists) { $_SESSION['flash_err']="Suite “{$name}” already exists (id ".$exists['suite_id'].")."; header('Location:index.php?page=manage'); exit; }

    $sql = "INSERT INTO `benchmarksuite`
      (`name`,`variation`,`date`,`url_benchmarks`,`url_evaluator`,
       `fom1_label`,`fom2_label`,`fom3_label`,`fom4_label`,`text_description`)
      VALUES (?,?,?,?,?,?,?,?,?,?)";
    $st = $pdo->prepare($sql);
    $st->execute([$name,$variation,$date,$url_benchmarks,$url_evaluator,
                  $fom1_label,$fom2_label,$fom3_label,$fom4_label,$text_description]);
    $_SESSION['flash'] = "Suite “{$name}” added.";
  } else {
    $suite_id = (int)($_POST['suite_id'] ?? 0);
    if ($suite_id<=0) { $_SESSION['flash_err']="Invalid suite id."; header('Location:index.php?page=manage'); exit; }
    $sql = "UPDATE `benchmarksuite`
            SET `name`=?,`variation`=?,`date`=?,`url_benchmarks`=?,`url_evaluator`=?,
                `fom1_label`=?,`fom2_label`=?,`fom3_label`=?,`fom4_label`=?,`text_description`=?
            WHERE `suite_id`=?";
    $st = $pdo->prepare($sql);
    $st->execute([$name,$variation,$date,$url_benchmarks,$url_evaluator,
                  $fom1_label,$fom2_label,$fom3_label,$fom4_label,$text_description,$suite_id]);
    $_SESSION['flash'] = "Suite #$suite_id updated.";
  }
  header('Location: index.php?page=manage'); exit;
}

// ========================
// ADD / UPDATE: BENCHMARK  (store lowercase names)
// ========================
if (isset($_POST['action']) && in_array($_POST['action'], ['add_benchmark','update_benchmark'], true)) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF token"; exit; }

  $suite_id        = (int)($_POST['suite_id'] ?? 0);
  $name            = isset($_POST['name']) ? norm_name($_POST['name']) : null; // LOWERCASE
  $URL             = str_or_null($_POST['URL'] ?? '');
  $file_hash       = str_or_null($_POST['file_hash'] ?? '');
  $text_description= str_or_null($_POST['text_description'] ?? '');

  if ($suite_id<=0) { $_SESSION['flash_err']="Suite is required for benchmark."; header('Location:index.php?page=manage'); exit; }
  if (!$name)       { $_SESSION['flash_err']="Benchmark name is required."; header('Location:index.php?page=manage'); exit; }

  if ($_POST['action']==='add_benchmark') {
    $exists = fetchOne($pdo,"SELECT benchmark_id FROM benchmark WHERE suite_id=? AND name=? LIMIT 1",[$suite_id,$name]);
    if ($exists) { $_SESSION['flash_err']="Benchmark “{$name}” already exists in that suite (id ".$exists['benchmark_id'].")."; header('Location:index.php?page=manage'); exit; }

    $sql = "INSERT INTO `benchmark` (`suite_id`,`name`,`URL`,`file_hash`,`text_description`)
            VALUES (?,?,?,?,?)";
    $st  = $pdo->prepare($sql);
    $st->execute([$suite_id,$name,$URL,$file_hash,$text_description]);
    $_SESSION['flash'] = "Benchmark “{$name}” added.";
  } else {
    $benchmark_id = (int)($_POST['benchmark_id'] ?? 0);
    if ($benchmark_id<=0) { $_SESSION['flash_err']="Invalid benchmark id."; header('Location:index.php?page=manage'); exit; }
    $sql = "UPDATE `benchmark`
            SET `suite_id`=?, `name`=?, `URL`=?, `file_hash`=?, `text_description`=?
            WHERE `benchmark_id`=?";
    $st  = $pdo->prepare($sql);
    $st->execute([$suite_id,$name,$URL,$file_hash,$text_description,$benchmark_id]);
    $_SESSION['flash'] = "Benchmark #$benchmark_id updated.";
  }
  header('Location: index.php?page=manage'); exit;
}

// ========================
// ADD / UPDATE: TOOL  (store lowercase names)
// ========================
if (isset($_POST['action']) && in_array($_POST['action'], ['add_tool','update_tool'], true)) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF token"; exit; }

  $name            = isset($_POST['name']) ? norm_name($_POST['name']) : null; // LOWERCASE
  $URL             = str_or_null($_POST['URL'] ?? '');
  $text_description= str_or_null($_POST['text_description'] ?? '');

  if (!$name) { $_SESSION['flash_err']="Tool name is required."; header('Location:index.php?page=manage'); exit; }

  if ($_POST['action']==='add_tool') {
    $exists = fetchOne($pdo,"SELECT tool_id FROM tool WHERE name=? LIMIT 1",[$name]);
    if ($exists) { $_SESSION['flash_err']="Tool “{$name}” already exists (id ".$exists['tool_id'].")."; header('Location:index.php?page=manage'); exit; }

    $st  = $pdo->prepare("INSERT INTO `tool` (`name`,`URL`,`text_description`) VALUES (?,?,?)");
    $st->execute([$name,$URL,$text_description]);
    $_SESSION['flash'] = "Tool “{$name}” added.";
  } else {
    $tool_id = (int)($_POST['tool_id'] ?? 0);
    if ($tool_id<=0) { $_SESSION['flash_err']="Invalid tool id."; header('Location:index.php?page=manage'); exit; }
    $st  = $pdo->prepare("UPDATE `tool` SET `name`=?,`URL`=?,`text_description`=? WHERE `tool_id`=?");
    $st->execute([$name,$URL,$text_description,$tool_id]);
    $_SESSION['flash'] = "Tool #$tool_id updated.";
  }
  header('Location: index.php?page=manage'); exit;
}

// ========================
// ADD: TOOL RELEASE  (lowercase name if provided)
// ========================
if (isset($_POST['action']) && $_POST['action']==='add_release') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF token"; exit; }

  $tool_id         = (int)($_POST['tool_id'] ?? 0);
  $name_in         = str_or_null($_POST['name'] ?? '');
  $name            = $name_in !== null ? norm_name($name_in) : null; // LOWERCASE if set
  $date            = str_or_null($_POST['date'] ?? '');
  $URL             = str_or_null($_POST['URL'] ?? '');
  $text_description= str_or_null($_POST['text_description'] ?? '');

  if ($tool_id<=0) { $_SESSION['flash_err']="Tool is required for release."; header('Location:index.php?page=manage'); exit; }

  // If you added UNIQUE(tool_id, name), you can skip duplicates:
  if ($name) {
    $exists = fetchOne($pdo,"SELECT tool_release_id FROM toolrelease WHERE tool_id=? AND name=? LIMIT 1",[$tool_id,$name]);
    if ($exists) { $_SESSION['flash_err']="Release “{$name}” already exists for that tool (id ".$exists['tool_release_id'].")."; header('Location:index.php?page=manage'); exit; }
  }

  $sql = "INSERT INTO `toolrelease` (`tool_id`,`name`,`date`,`URL`,`text_description`)
          VALUES (?,?,?,?,?)";
  $st  = $pdo->prepare($sql);
  $st->execute([$tool_id,$name,$date,$URL,$text_description]);

  $_SESSION['flash'] = "Release added.";
  header('Location: index.php?page=manage'); exit;
}

// ========================
// ADD: RESULT  (text-first; UPSERT; lowercase names)
// ========================
if (isset($_POST['action']) && $_POST['action']==='add_result') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); echo "Invalid CSRF token"; exit; }

  // Your manage.php now sends text fields for these:
  $suite_name_in     = isset($_POST['suite']) ? norm_name((string)$_POST['suite']) : '';
  $benchmark_name_in = isset($_POST['benchmark']) ? norm_name((string)$_POST['benchmark']) : '';
  $tool_name_in      = isset($_POST['tool']) ? norm_name((string)$_POST['tool']) : '';

  // Release: allow either id or name
  $toolrel_id_in     = (int)($_POST['tool_release_id'] ?? 0);
  $toolrel_name_in   = isset($_POST['tool_release']) ? norm_name((string)$_POST['tool_release']) : '';

  $fom1 = $_POST['fom1'] ?? null;   // required
  $fom2 = $_POST['fom2'] ?? null;
  $fom3 = $_POST['fom3'] ?? null;
  $fom4 = $_POST['fom4'] ?? null;

  $date       = str_or_null($_POST['date'] ?? '') ?: today_ymd();
  $file_hash  = str_or_null($_POST['file_hash'] ?? '');
  $URL        = str_or_null($_POST['URL'] ?? '');
  $evaluator_output = str_or_null($_POST['evaluator_output'] ?? '');
  $text_description = str_or_null($_POST['text_description'] ?? '');
  $run_version      = null; // keep NULL

  if ($suite_name_in === '' || $benchmark_name_in === '' || $tool_name_in === '') {
    $_SESSION['flash_err'] = "Suite (text), Benchmark (name), and Tool (name) are required.";
    header('Location: index.php?page=manage'); exit;
  }
  if ($fom1 === null || $fom1 === '') {
    $_SESSION['flash_err'] = "FOM1 is required.";
    header('Location: index.php?page=manage'); exit;
  }

  try {
    $pdo->beginTransaction();

    // Safe UPSERTs (lowercase)
    $suite_id     = ensure_suite_id($pdo, $suite_name_in);
    $benchmark_id = ensure_benchmark_id($pdo, $suite_id, $benchmark_name_in);
    $tool_id      = ensure_tool_id($pdo, $tool_name_in);

    // Tool release (optional)
    $tool_release_id = null;
    if ($toolrel_id_in > 0) {
      $tool_release_id = $toolrel_id_in;
    } elseif ($toolrel_name_in !== '') {
      $tool_release_id = ensure_toolrelease_id($pdo, $tool_id, $toolrel_name_in);
    }

    // Insert the result row
    $sql = "INSERT INTO `result`
      (`suite_id`,`benchmark_id`,`tool_id`,`tool_release_id`,
       `fom1`,`fom2`,`fom3`,`fom4`,
       `date`,`file_hash`,`URL`,`evaluator_output`,`text_description`,`run_version`)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $st = $pdo->prepare($sql);
    $st->execute([
      $suite_id, $benchmark_id, $tool_id, $tool_release_id,
      ($fom1 === '' ? null : $fom1),
      ($fom2 === '' ? null : $fom2),
      ($fom3 === '' ? null : $fom3),
      ($fom4 === '' ? null : $fom4),
      $date, $file_hash, $URL, $evaluator_output, $text_description, $run_version
    ]);

    $pdo->commit();
    $_SESSION['flash'] = "Result added.";
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash_err'] = "Failed to add result: ".$e->getMessage();
  }
  header('Location: index.php?page=manage'); exit;
}
