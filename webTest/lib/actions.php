<?php
if (isset($_POST['action']) && in_array($_POST['action'], ['export_csv','export_latex'], true)) {
  // CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo "Invalid CSRF token";
    exit;
  }

  // ---- table autodetect (same as results.php) ----
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

  // inputs
  $FOM_KEYS = ['fom1','fom2','fom3','fom4'];
  $suite_ids     = array_map('intval', $_POST['suites'] ?? []);
  $benchmark_ids = array_map('intval', $_POST['benchmark_ids'] ?? []); // <-- MULTI
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
