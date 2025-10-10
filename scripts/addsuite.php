<?php
require_once 'config.php';
require_once '../webTest/lib/helpers.php';

echo "Adding suite " . $argv[1] . " " . $argv[2] . "\n";
echo "DB is " . $db . "\n";

$query = "INSERT INTO benchmarksuite (name, variation) VALUES ('" . $argv[1] . "','" . $argv[2] . "')";
echo "Query is " . $query . "\n"; 
$pdo->query($query);

echo "Inserted.";

$suites = $pdo->query("SELECT suite_id, name, variation FROM benchmarksuite");
foreach ($suites as $r) {
 print_r($r);
 // $bm = benchmarksBySuite($pdo, $r['suite_id']);
 // print_r($bm);
}
print_r($suites);

echo "Done.\n";
?>


