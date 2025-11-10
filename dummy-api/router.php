<?php

// To run and test this dummy API server locally on windows machine from power shell:
// from the folder C:\Users\sszabo, run the below command:
// Start-Process php -ArgumentList '-S','localhost:8000','-t','"c:\Users\sszabo\OneDrive - Southeastern\Desktop\development\VEMS\api-poller\dummy-api"','"c:\Users\sszabo\OneDrive - Southeastern\Desktop\development\VEMS\api-poller\dummy-api\router.php"' -NoNewWindow -WorkingDirectory 'c:\Users\sszabo\OneDrive - Southeastern\Desktop\development\VEMS\api-poller'
// Then to test run the below from another terminal to poll and endpoint:
// make sure you are in this folder: C:\Users\sszabo\OneDrive - Southeastern\Desktop\development\VEMS\api-poller
// and run: php .\api-pollers\timeseries-search.php

// Simple router for PHP built-in server
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$full = __DIR__ . $path;

// If the requested path maps to an existing file, let the server serve it directly
if (php_sapi_name() === 'cli-server' && is_file($full)) {
    return false;
}

// Otherwise forward everything to index.php
require __DIR__ . '/index.php';
