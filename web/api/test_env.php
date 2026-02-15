<?php
header('Content-Type: text/plain');
echo "getenv: " . (getenv('API_BMUPLOAD_TOKEN') ?: 'NULL') . PHP_EOL;
echo "_SERVER raw: " . ($_SERVER['API_BMUPLOAD_TOKEN'] ?? $_SERVER['REDIRECT_API_BMUPLOAD_TOKEN'] ?? 'NULL') . PHP_EOL;
echo "HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'NULL') . PHP_EOL;
