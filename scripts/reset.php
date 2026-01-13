<?php
require_once 'config.php';
require_once '../webTest/lib/helpers.php';

echo "Resetting the database.";

$pdo->query("DELETE from result");
$pdo->query("DELETE from benchmark");
$pdo->query("DELETE from benchmarksuite");

echo "Done.\n";
?>


