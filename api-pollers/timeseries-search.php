<?php

// Include the project autoloader (keeps this file small and consistent).
// The autoloader maps simple class names to `php/request/{ClassName}.php`.
require_once __DIR__ . '/../autoload.php';

// API base URL constant (used for all requests)
// API base URL and storage directory are read from environment variables first
// (set via shell, systemd EnvironmentFile, or .env) and fall back to safe defaults.
defined('API_BASE') || define('API_BASE', (getenv('API_BASE') ?: 'http://localhost:8000/'));
defined('STORAGE_DIR') || define('STORAGE_DIR', (getenv('STORAGE_DIR') ?: __DIR__ . '/storage'));

// Ensure storage directory exists (writeable by the user running the script)
if (!is_dir(STORAGE_DIR)) {
    @mkdir(STORAGE_DIR, 0755, true);
}

$csvFile = STORAGE_DIR . '/results_timeseries_search.csv';
$csvHeaders = [
    'id',
    'component_id',
    'component_category',
    'pass_pass_id',
    'pass_online_timestamp',
    'pass_direction',
    'pass_avg_speed',
    'values',   // will contain JSON (array)
    'details'   // will contain JSON (object)
];

// If file missing or empty, write header row
if (!file_exists($csvFile) || filesize($csvFile) === 0) {
    $fh = @fopen($csvFile, 'a');
    if ($fh !== false) {
        if (flock($fh, LOCK_EX)) {
            fputcsv($fh, $csvHeaders);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    } else {
        error_log("Failed to create CSV file: {$csvFile}");
    }
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

// var_dump($client);
// die(API_BASE);

// POST /results/timeseries/search
// Send a JSON body matching the OpenAPI example and the dummy-api handler expectations.
$response = $client->post(API_BASE . 'results/timeseries/search', [
    // send JSON body (HttpClient will set Content-Type: application/json)
    'json' => [
        'component_queries' => [
            'component_category' => ['GuideTyre']
        ],
        'query' => [
            'range' => [
                'pass_online_timestamp' => [
                    'greater_than_equals' => 1658831580000
                ]
            ]
        ],
        'sort' => ['-pass_online_timestamp'],
        'limit' => 500,
        'total' => true,
    ],
    'headers' => ['Accept' => 'application/json'],
]);

// die('debug stop');

if ($response->status >= 200 && $response->status < 300) {
    $data = $response->json(); // associative array
    // var_dump($data);

    // Save response to a timestamped file in STORAGE_DIR.
    // Use a compact but sortable timestamp in the filename.
    $dt = new \DateTime();
    $stamp = $dt->format('Ymd_His'); // e.g. 20251104_134501
    $filename = STORAGE_DIR . '/results_timeseries_search_' . $stamp . '.json';

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

            // Append one CSV row per result, mapping columns to the hardcoded header
            $results = isset($data['results']) && is_array($data['results']) ? $data['results'] : [];
            if (!empty($results)) {
                $fh = @fopen($csvFile, 'a');
                if ($fh === false) {
                    error_log("Failed to open CSV file for appending: {$csvFile}");
                } else {
                    if (flock($fh, LOCK_EX)) {
                        foreach ($results as $r) {
                            $rowOut = [];
                            foreach ($csvHeaders as $col) {
                                if (!is_array($r) || !array_key_exists($col, $r) || $r[$col] === null) {
                                    $rowOut[] = '';
                                    continue;
                                }
                                $val = $r[$col];
                                if (is_scalar($val)) {
                                    $rowOut[] = $val;
                                } else {
                                    // encode arrays/objects as compact JSON inside the cell
                                    $enc = json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                    $rowOut[] = $enc === false ? '' : $enc;
                                }
                            }
                            fputcsv($fh, $rowOut);
                        }
                        fflush($fh);
                        flock($fh, LOCK_UN);
                        error_log("Appended " . count($results) . " result(s) to CSV: {$csvFile}");
                    } else {
                        error_log("Could not acquire lock to append CSV: {$csvFile}");
                    }
                    fclose($fh);
                }
            }

        }
    }
} else {
    // log non-2xx
    error_log("API returned {$response->status}");
    // var_dump($response->body);
}