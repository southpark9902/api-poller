<?php
// Simple dummy API for testing the poller.
// Routes (very small router):
// GET  /api/result -> returns { sensor: 'temp', value: <random> }
// POST /api/set    -> echoes back the json posted

// !!! At the moment it is a stub only returning random values that does not reflect the real API behavior. !!!

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

header('Content-Type: application/json');

if ($uri === '/api/results' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $value = round(20 + (mt_rand() / mt_getrandmax()) * 10, 2);
    echo json_encode(['sensor' => 'temp', 'value' => $value, 'ts' => time()]);
    exit(0);
}

if ($uri === '/api/set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    $json = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json']);
        exit(0);
    }
    echo json_encode(['ok' => true, 'received' => $json]);
    exit(0);
}

http_response_code(404);
echo json_encode(['error' => 'not_found', 'path' => $uri]);
