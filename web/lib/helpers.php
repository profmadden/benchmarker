<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=null){ return isset($_POST[$k])?trim($_POST[$k]):$d; }
function getv($k,$d=null){ return isset($_GET[$k])?trim($_GET[$k]):$d; }

function check_csrf(){
  if(($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')){
    http_response_code(400); exit('Bad CSRF token');
  }
}

function fetchAll($pdo, $sql, $params=[]){
  $st=$pdo->prepare($sql); $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
function fetchOne($pdo, $sql, $params=[]){
  $r=fetchAll($pdo,$sql,$params); return $r[0]??null;
}

// ---------------- Lookups used in manage.php lists ----------------
function suites($pdo){
  return fetchAll($pdo, "SELECT * FROM benchmarksuite ORDER BY name, variation");
}
function benchmarksBySuite($pdo,$suite_id){
  return fetchAll($pdo, "SELECT * FROM benchmark WHERE suite_id=? ORDER BY name", [$suite_id]);
}
function benchmarksAll($pdo){
  return fetchAll($pdo, "SELECT b.*, s.name AS suite_name
                         FROM benchmark b
                         JOIN benchmarksuite s ON b.suite_id=s.suite_id
                         ORDER BY s.name, b.name");
}
function tools($pdo){
  return fetchAll($pdo, "SELECT * FROM tool ORDER BY name");
}
function releasesByTool($pdo,$tool_id){
  return fetchAll($pdo, "SELECT * FROM toolrelease
                         WHERE tool_id=?
                         ORDER BY COALESCE(date,'0000-00-00') DESC, name", [$tool_id]);
}

// ---------------- Small utilities ----------------
function str_or_null($s){
  $s = trim((string)$s);
  return $s === '' ? null : $s;
}
function today_ymd(){
  return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
}

// ---------- Normalization + case-insensitive helpers ----------
// trim + collapse spaces + lowercase (store lowercase)
function norm_name(string $s): string {
  $s = preg_replace('/\s+/u', ' ', trim($s));
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

// Case-insensitive lookup; insert lowercase if missing
function ensure_suite_id(PDO $pdo, string $name): int {
  $q = trim($name);
  $st = $pdo->prepare("SELECT suite_id FROM benchmarksuite WHERE LOWER(name)=LOWER(?) LIMIT 1");
  $st->execute([$q]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return (int)$row['suite_id'];

  $ins = $pdo->prepare("INSERT INTO benchmarksuite (name) VALUES (?)");
  $ins->execute([norm_name($q)]);
  return (int)$pdo->lastInsertId();
}

function ensure_tool_id(PDO $pdo, string $name): int {
  $q = trim($name);
  $st = $pdo->prepare("SELECT tool_id FROM tool WHERE LOWER(name)=LOWER(?) LIMIT 1");
  $st->execute([$q]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return (int)$row['tool_id'];

  $ins = $pdo->prepare("INSERT INTO tool (name) VALUES (?)");
  $ins->execute([norm_name($q)]);
  return (int)$pdo->lastInsertId();
}

function ensure_benchmark_id(PDO $pdo, int $suite_id, string $name): int {
  $q = trim($name);
  $st = $pdo->prepare("SELECT benchmark_id FROM benchmark WHERE suite_id=? AND LOWER(name)=LOWER(?) LIMIT 1");
  $st->execute([$suite_id, $q]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return (int)$row['benchmark_id'];

  $ins = $pdo->prepare("INSERT INTO benchmark (suite_id, name) VALUES (?, ?)");
  $ins->execute([$suite_id, norm_name($q)]);
  return (int)$pdo->lastInsertId();
}

function ensure_toolrelease_id(PDO $pdo, int $tool_id, string $name): int {
  $q = trim($name);
  $st = $pdo->prepare("SELECT tool_release_id FROM toolrelease WHERE tool_id=? AND LOWER(name)=LOWER(?) LIMIT 1");
  $st->execute([$tool_id, $q]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return (int)$row['tool_release_id'];

  $ins = $pdo->prepare("INSERT INTO toolrelease (tool_id, name) VALUES (?, ?)");
  $ins->execute([$tool_id, norm_name($q)]);
  return (int)$pdo->lastInsertId();
}
