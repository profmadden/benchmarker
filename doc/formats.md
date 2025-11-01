# Benchmarker File Formats

The Benchmarker benchmark repository is set up to have the experimental
results stored in individual CSV files.  Under the data subdirectory,
there is a CSV file for each benchmark suite, which specifies the name of the
suite, the benchmarks contained within it, the figures of merit (FOM), and
some URLs and a short description.  Each benchmark suite may have a number
of different variants (corresponding to different optimization objectives).

Underneath the data directory, in subdirectories named after the suite and
variant, are the experiemental results for different optimization tools (in
individual appropriately named CSV files).  Each tool can have a short
description, URLs, list of publications, and then results for each benchmark.
If a figure of merit is not relevant, the value stord is 0.0.

This arrangement should make updating, adding new results, and so on,
relatively simple and error-proof.  On changes, the MySQL database can be
dropped and recreated.  The PHP script will walk the data subdirectory,
creating new database entries and setting the appropriate indexing keys.

To load the database, configure the config.php to have the appropriate
host address for the database, user name, password, and so on.  Then run
the insertion script.
<pre>
% cd data
% php ../scripts/insert.php suite_name.csv
</pre>

Each CSV file has a series of rows, parsed one by one.

* addsuite, suite_name, suite_version, fom1, fom2, fom3, fom4, bench_url, evaluator_url, description
* addbenchmark, name, url, description
* suite, suite_name, suite_version
* tool, name, url, description
* release, version_name, url, description
* result, benchmark_name, provenance, fom1, fom2, fom3, fom4, url, description
* publication, url, description