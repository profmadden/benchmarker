<?php
define('__ROOT__', dirname(__FILE__));
require_once (__ROOT__ . '/config.php');
require_once (__ROOT__ . '/../webTest/lib/helpers.php');

echo "Resetting the database.";

$pdo->query("DELETE from result");
$pdo->query("DELETE from benchmark");
$pdo->query("DELETE from benchmarksuite");
$pdo->query("DELETE from tool");
$pdo->query("DELETE from toolrelease");

echo "Done.\n";
?>


