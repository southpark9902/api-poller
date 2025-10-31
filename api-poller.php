<?php

declare(strict_types=1);

// Include the project autoloader (keeps this file small and consistent).
// The autoloader maps simple class names to `php/request/{ClassName}.php`.
require_once __DIR__ . '/autoload.php';

// API base URL constant (used for all requests)
// API base URL and storage directory are read from environment variables first
// (set via shell, systemd EnvironmentFile, or .env) and fall back to safe defaults.
defined('API_BASE') || define('API_BASE', (getenv('API_BASE') ?: 'http://device.local/api/'));
defined('STORAGE_DIR') || define('STORAGE_DIR', (getenv('STORAGE_DIR') ?: __DIR__ . '/storage'));

// Ensure storage directory exists (writeable by the user running the script)
if (!is_dir(STORAGE_DIR)) {
    @mkdir(STORAGE_DIR, 0755, true);
}

// Basic GET
$isLocal = (strtolower(getenv('APP_ENV') ?: APP_ENV) === 'local');

$client = new HttpClient([
    'timeout' => 8,
    'connect_timeout' => 2,
    'retries' => 2,
    // Only disable certificate verification when running in local/dev
    // environment. In production keep verification enabled (default true).
    'verify' => $isLocal ? false : true,
]);


$response = $client->get(API_BASE . 'result', [
    'query' => ['sensor' => 'temp'],
    'headers' => ['Accept' => 'application/json'],
]);

if ($response->status >= 200 && $response->status < 300) {
    $data = $response->json(); // associative array
    // save to file
    file_put_contents(STORAGE_DIR . '/latest.json', json_encode($data));
} else {
    // log non-2xx
    error_log("API returned {$response->status}");
}

// POST with JSON and custom timeouts
// $response = $client->post(API_BASE . 'set', [
//     'json' => ['sampling' => 1],
//     'timeout' => 5,
// ]);

// if ($response->status >= 200 && $response->status < 300) {
//     $data = $response->json(); // associative array
//     // save to file
//     file_put_contents(STORAGE_DIR . '/latest.json', json_encode($data));
// } else {
//     // log non-2xx
//     error_log("API returned {$response->status}");
// }