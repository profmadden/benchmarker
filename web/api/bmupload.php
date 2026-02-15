<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');

/* Bearer auth */
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) { http_response_code(401); echo json_encode(['error'=>'missing auth']); exit; }
$token = $m[1];
if (!hash_equals(getenv('API_BMUPLOAD_TOKEN') ?: 'CHANGE_ME', $token)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

/* Input */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['error'=>'bad json']); exit; }

foreach (['suite','benchmark','tool','primary_fom','artifact_url'] as $k) {
  if (!isset($data[$k]) || $data[$k]==='') { http_response_code(400); echo json_encode(['error'=>"missing $k"]); exit; }
}

/* payload */
$suite          = (string)$data['suite'];
$suite_variation= isset($data['suite_variation']) ? (string)$data['suite_variation'] : null; // NEW: optional
$benchmark      = (string)$data['benchmark'];
$tool           = (string)$data['tool'];
$tool_version   = isset($data['tool_version']) ? (string)$data['tool_version'] : null;       // optional
$primary_fom    = (float)$data['primary_fom'];
$artifact_url   = (string)$data['artifact_url'];
$sha256         = isset($data['sha256']) ? (string)$data['sha256'] : null;
$notes          = isset($data['notes'])  ? (string)$data['notes']  : null;

/* Resolve/insert dimensions */
$pdo->beginTransaction();

$tool_id = ensure_dim($pdo, 'tool', 'name', $tool);

/* suite by (name, variation) */
$suite_sel = $pdo->prepare("
  SELECT suite_id FROM benchmarksuite
  WHERE name=? AND COALESCE(variation,'')=COALESCE(?, '')
  LIMIT 1
");
$suite_sel->execute([$suite, $suite_variation]);
$suite_id = (int)($suite_sel->fetchColumn() ?: 0);
if (!$suite_id) {
  $ins = $pdo->prepare("INSERT INTO benchmarksuite (name, variation, date) VALUES (?,?,CURRENT_DATE())");
  $ins->execute([$suite, $suite_variation]);
  $suite_id = (int)$pdo->lastInsertId();
}

/* benchmark within suite */
$bench_sel = $pdo->prepare("SELECT benchmark_id FROM benchmark WHERE suite_id=? AND name=? LIMIT 1");
$bench_sel->execute([$suite_id, $benchmark]);
$bench_id = (int)($bench_sel->fetchColumn() ?: 0);
if (!$bench_id) {
  $bench_ins = $pdo->prepare("INSERT INTO benchmark (suite_id, name) VALUES (?,?)");
  $bench_ins->execute([$suite_id, $benchmark]);
  $bench_id = (int)$pdo->lastInsertId();
}

/* Decide formal release vs dev run_version */
$tool_release_id = null;
$run_version = null;
if ($tool_version !== null && $tool_version !== '') {
  // Try to match a formal release by name for this tool
  $rel_sel = $pdo->prepare("SELECT tool_release_id FROM toolrelease WHERE tool_id=? AND name=? LIMIT 1");
  $rel_sel->execute([$tool_id, $tool_version]);
  $tool_release_id = (int)($rel_sel->fetchColumn() ?: 0);
  if (!$tool_release_id) {
    // Treat as dev label
    $run_version = $tool_version;
  }
} else {
  // default dev label = UTC YYYYMMDD
  $run_version = gmdate('Ymd');
}

/* Insert result */
$ins = $pdo->prepare("
  INSERT INTO result
    (tool_id, tool_release_id, run_version,
     suite_id, benchmark_id,
     fom1, URL, file_hash, evaluator_output, text_description, date)
  VALUES
    (:tool_id, :tool_release_id, :run_version,
     :suite_id, :bench_id,
     :fom1, :url, :file_hash, NULL, :notes, CURRENT_DATE())
");
$ins->execute([
  ':tool_id'         => $tool_id,
  ':tool_release_id' => $tool_release_id ?: null,
  ':run_version'     => $run_version,
  ':suite_id'        => $suite_id,
  ':bench_id'        => $bench_id,
  ':fom1'            => $primary_fom,
  ':url'             => $artifact_url,
  ':file_hash'       => $sha256,
  ':notes'           => $notes,
]);

$run_id = (int)$pdo->lastInsertId();
$pdo->commit();

echo json_encode(['ok'=>true, 'run_id'=>$run_id]);

/* helpers */
function ensure_dim(PDO $pdo, string $table, string $col, string $val): int {
  $sel = $pdo->prepare("SELECT {$table}_id FROM {$table} WHERE {$col}=? LIMIT 1");
  $sel->execute([$val]);
  $id = (int)($sel->fetchColumn() ?: 0);
  if ($id) return $id;
  $ins = $pdo->prepare("INSERT INTO {$table} ({$col}) VALUES (?)");
  $ins->execute([$val]);
  return (int)$pdo->lastInsertId();
}
