<?php
define('__ROOT__', dirname(__FILE__));
require_once (__ROOT__ . '/config.php');
require_once (__ROOT__ . '/../webTest/lib/helpers.php');

function fetchSuiteId($pdo, $suite, $variant): int {
    $query = "SELECT suite_id from benchmarksuite where name='$suite' and variation='$variant';";
    $r = $pdo->query($query);
    // print_r("Suite query finished\n");
    // print_r($r);
    foreach ($r as $row) {
        // print_r($row);
        return $row["suite_id"];
    }
    return -1;
}

function fetchToolId($pdo, $tool): int
{
    $query = "SELECT tool_id from tool where name='$tool';";
    $r     = $pdo->query($query);
    // print_r("Query finished\n");
    // print_r($r);
    foreach ($r as $row) {
        // print_r($row);
        return $row["tool_id"];
    }
    return -1;
}


function fetchToolReleaseId($pdo, $tool, $release): int {
    $tool_id = fetchToolId($pdo, $tool);

    $query = "SELECT tool_release_id from toolrelease where tool_id=$tool_id and tool_release_version='$release';";
    $r     = $pdo->query($query);
    foreach ($r as $row) {
        // print_r($row);
        print("Found tool release $tool $release -> $row[tool_release_id]\n");
        return $row["tool_release_id"];
    }
    return -1;
}

function fetchBenchmarkId($pdo, $suite_id, $benchmark): int {
    $query = "SELECT benchmark_id from benchmark where suite_id=$suite_id and name='$benchmark';";
    $r =$pdo->query($query);
    foreach ($r as $row) {
        print("Found benchmark $benchmark\n");
        return $row["benchmark_id"];
    }
    return -1;
}


function parseCSV($pdo, $file, $suite_id, $tool_id, $tool_release_id)
{
    $row = 1;
    if (($handle = fopen($file, "r")) !== false) {
        while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== false) {
            $num = count($data);
            // echo "<p> $num fields in line $row: <br /></p>\n";
            $row++;
            // for ($c=0; $c < $num; $c++) {
            //     echo $data[$c] . " ";
            // }
            // echo "\n";
            if ($num > 0) {
                if ($data[0] == "suite") {
                    print("Using suite $data[1] $data[2]\n");
                    $suite_id = fetchSuiteId($pdo, $data[1], $data[2]);
                }
                if ($data[0] == "tool") {
                    $tool_id = fetchToolId($pdo, $data[1]);
                    $tool_release_id = fetchToolReleaseId($pdo, $data[1], $data[2]);
                }
                if ($data[0] == "publication") {
                    print("PUB:\n  $data[1]\n  $data[2]\n");
                }

                if (($data[0] == "addsuite") && ($num >= 10)) {
                    $suite_id = fetchSuiteId($pdo, $data[1], $data[2]);
                    if ($suite_id == -1) {
                        $query    = "INSERT INTO benchmarksuite (name, variation, url_benchmarks, url_evaluator, fom1_label, fom2_label, fom3_label, fom4_label, text_description) VALUES ('$data[1]','$data[2]','$data[3]','$data[4]','$data[5]','$data[6]','$data[7]','$data[8]',\"$data[9]\");";
                        print("Query is $query\n");
                        $r        = $pdo->query($query);
                        $suite_id = fetchSuiteId($pdo, $data[1],$data[2]);
                        //$suite_id = fetchSuiteId($pdo, $data[1], $data[2]);
                        // print("Added suite $data[1] $data[2] ID number $suite_id");
                    } else {
                        print("Suite $data[1] $data[2] already exists, and is suite ID $suite_id\n");
                    }
                }
                if (($data[0] == "addbench") && ($num >= 4)) {
                    $query = "INSERT INTO benchmark (suite_id, name, url, text_description) VALUES ($suite_id,'$data[1]','$data[2]','$data[3]');";
		    print("Insert benchmark query $query");
                    $r     = $pdo->query($query);
                }
                if (($data[0] == "addtool") && ($num >= 4)) {
                    $tool_id = fetchToolId($pdo, $data[1]);
                    if ($tool_id == -1) {
                       $query = "INSERT INTO tool (name, URL, text_description) VALUES ('$data[1]','$data[2]','$data[3]');";
                       $r    = $pdo->query($query); 
                    }
                    $tool_id = fetchToolId($pdo, $data[1]);
		    print("Tool ID for addtool $data[1] is $tool_id");
                }
                if (($data[0] == "addrelease") && ($num >= 3)) {
                    $tool_release_id = fetchToolReleaseId($pdo,$data[1],$data[2]);
                    if ($tool_release_id == -1) {
                        $query = "INSERT INTO toolrelease (tool_id, name, tool_release_version) VALUES ($tool_id,'$data[1]','$data[2]');";
			print("Add release $query");
                        $r     = $pdo->query($query);
                        $query = "SELECT tool_id from toolrelease where name='$data[1]' and tool_release_version='$data[2]'";
                        $r     = $pdo->query($query);
                        $tool_release_id = fetchToolReleaseId($pdo,$data[1],$data[2]);
                        print("Tool release ID is $tool_release_id");
                    }
                }
                if ($data[0] == "result") {
                    $benchmark_id =fetchBenchmarkId($pdo, $suite_id, $data[1]);
                    $query = "INSERT INTO result (tool_id,tool_release_id,suite_id,benchmark_id,fom1,fom2,fom3,fom4,text_description,URL) VALUE ($tool_id,$tool_release_id,$suite_id,$benchmark_id,$data[2],$data[3],$data[4],$data[5],\"$data[6]\",\"$data[7]\");";
                    print("RESULT query $query\n");
                    $r = $pdo->query($query);
                }

                if ($data[0] == "comment") {
                    print("$data[1]\n");
                }
                if ($data[0] == "import") {
                    parseCSV($pdo, $data[1], $suite_id, $tool_id, $tool_release_id);
                }
                if ($data[0] == "importdir") {
                    if ($handle2 = opendir($data[1])) {
                        while (false != ($entry = readdir($handle2))) {
                            print("Operate on file $data[1]/$entry\n");
                            $ext = pathinfo($data[1] . $entry, PATHINFO_EXTENSION);
                            if ($ext == "csv") {
                                print("  This is a CSV file\n");
                                parseCSV($pdo, $data[1] . "/" . $entry, $suite_id, $tool_id, $tool_release_id);
                            }
                        }
                        closedir($handle2);
                    }
                }

                // tool name url text_description
                // release name tool_release_version url text_description
                // result tool_id tool_release_id <run version> suite_id benchmark_id *provenance* fom1 fom2 fom3 fom4 url description
                // publication URL DOI text_description
                // benchpublication URL DOI text_description

            }
        }
        fclose($handle);
    }
}


parseCSV($pdo, $argv[1], -1, -1, -1);

// $n = fetchToolId($pdo, "xyz", "1.0");
// print("Tool " . $n);
// $n = fetchSuiteId($pdo, "iccad04", "default");
//print("Suite ID: " . $n . "\n");

// $n = fetchSuiteId($pdo, "gsrc", "var2");
// print("Suite ID: " . $n . "\n");

// echo "Done.\n";
?>


