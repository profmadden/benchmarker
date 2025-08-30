<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=null){ return isset($_POST[$k])?trim($_POST[$k]):$d; }
function getv($k,$d=null){ return isset($_GET[$k])?trim($_GET[$k]):$d; }

function check_csrf(){ if(($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')){ http_response_code(400); exit('Bad CSRF token'); } }

function fetchAll($pdo, $sql, $params=[]){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC);} 
function fetchOne($pdo, $sql, $params=[]){ $r=fetchAll($pdo,$sql,$params); return $r[0]??null; }

// Lookups
function suites($pdo){ return fetchAll($pdo, "SELECT * FROM benchmarksuite ORDER BY name, variation"); }
function benchmarksBySuite($pdo,$suite_id){ return fetchAll($pdo, "SELECT * FROM benchmark WHERE suite_id=? ORDER BY name", [$suite_id]); }
function benchmarksAll($pdo){ return fetchAll($pdo, "SELECT b.*, s.name AS suite_name FROM benchmark b JOIN benchmarksuite s ON b.suite_id=s.suite_id ORDER BY s.name, b.name"); }
function tools($pdo){ return fetchAll($pdo, "SELECT * FROM tool ORDER BY name"); }
function releasesByTool($pdo,$tool_id){ return fetchAll($pdo, "SELECT * FROM toolrelease WHERE tool_id=? ORDER BY COALESCE(date,'0000-00-00') DESC, name", [$tool_id]); }
