<?php
// ini_set('display_errors',1); error_reporting(E_ALL);

$host = '127.0.0.1';   // or localhost
$port = '3306';        // same as Workbench
$user = 'root';
$pass = 'root';   // <— put the real password
$db   = 'suites_db';              // your schema

$pdo = new PDO(
  "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
  $user,
  $pass
);
// echo "Connected OK!";
