<?php

// Include the project autoloader (keeps this file small and consistent).
// The autoloader maps simple class names to `php/request/{ClassName}.php`.
require_once __DIR__ . '/../../autoload.php';

// API base URL constant (used for all requests)
// API base URL and storage directory are read from environment variables first
// (set via shell, systemd EnvironmentFile, or .env) and fall back to safe defaults.
// defined('API_BASE') || define('API_BASE', (getenv('API_BASE') ?: 'http://localhost:8000/'));
define('API_BASE', 'https://my-json-server.typicode.com/southpark9902/dummy-api-server/');
defined('STORAGE_DIR') || define('STORAGE_DIR', (getenv('STORAGE_DIR') ?: __DIR__ . '/storage'));

// Ensure storage directory exists (writeable by the user running the script)
if (!is_dir(STORAGE_DIR)) {
    @mkdir(STORAGE_DIR, 0755, true);
}

if (!is_dir(STORAGE_DIR . "/my_json_server")) {
    @mkdir(STORAGE_DIR . "/my_json_server", 0755, true);
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

// https://my-json-server.typicode.com/southpark9902/dummy-api-server/results
$response = $client->get(API_BASE . 'results', [
    'query' => ['sensor' => 'temp'],
    'headers' => ['Accept' => 'application/json'],
]);

if ($response->status >= 200 && $response->status < 300) {
    $data = $response->json(); // associative array
    // Save response to a timestamped file in STORAGE_DIR.
    // Use a compact but sortable timestamp in the filename.
    $dt = new \DateTime();
    $stamp = $dt->format('Ymd_His'); // e.g. 20251104_134501    
    $filename = STORAGE_DIR . '/my_json_server/hierarchical_search_' . $stamp . '.json';

    // Pretty-print JSON for easier inspection and keep slashes unescaped
    $jsonOut = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonOut === false) {
        error_log('Failed to encode API response to JSON: ' . json_last_error_msg());
    } else {
        $bytes = @file_put_contents($filename, $jsonOut, LOCK_EX);
        if ($bytes === false) {
            error_log("Failed to write api response to file: {$filename}");
        } else {
            // Informational message; tests can rely on storage files being present
            error_log("Saved results to {$filename} ({$bytes} bytes)");
        }
    }
} else {
    // log non-2xx
    error_log("API returned {$response->status}");
    // var_dump($response->body);
}