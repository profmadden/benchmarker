
<?php


$h = gethostname();
$dbname="suites_db";
$dbuser="pmadden";
$dbpwd="";

$cid = mysqli_connect($host, $dbuser, $dbpwd, $dbname);

if (!$cid)
  {
    print "Error: " . msqli_error() . "\n";
    exit();
  }
else
  {
    print "Connected to database.";
  }

echo "<br>";

  $sql = "SELECT * from result";
  $rs = mysqli_query($cid, $sql);


  while ($entry = mysqli_fetch_array($rs, MYSQLI_ASSOC))
  {
    echo "Tool " . $entry["tool_id"] . " result ID " . $entry["result_id"] . " " . $entry["fom1"] .  "<br>\n";
  }


  phpinfo();
?>
