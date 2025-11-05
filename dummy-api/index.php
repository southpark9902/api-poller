<?php
// Dummy API for testing the poller.
// Supports:
//  - GET  /api/results
//  - POST /api/set
//  - POST /results/timeseries/search

header('Content-Type: application/json');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
// var_dump($uri, $method);
// die;

// GET /api/results -> simple random sensor value
if ($uri === '/api/results' && $method === 'GET') {
    $value = round(20 + (mt_rand() / mt_getrandmax()) * 10, 2);
    echo json_encode(['sensor' => 'temp', 'value' => $value, 'ts' => time()]);
    exit(0);
}

// POST /api/set -> echo posted JSON
if ($uri === '/api/set' && $method === 'POST') {
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

// POST /results/timeseries/search -> implements a subset of search params from the OpenAPI spec
if ($uri === '/results/timeseries/search' && $method === 'POST') {
    $data = file_get_contents('php://input');
    $req = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json']);
        exit(0);
    }

    // Supported params (per openapi): query, component_queries, sort, limit, offset, total
    $limit = isset($req['limit']) ? (int)$req['limit'] : 100;
    $offset = isset($req['offset']) ? (int)$req['offset'] : 0;
    $wantTotal = !empty($req['total']);

    // Detect canonical example request from the spec
    $is_example_request = true;

    // if (!empty($req['component_queries']) && is_array($req['component_queries'])) {
    //     $cats = isset($req['component_queries']['component_category']) && is_array($req['component_queries']['component_category'])
    //         ? $req['component_queries']['component_category'] : [];
    //     if (in_array('GuideTyre', $cats, true)
    //         && isset($req['query']['range']['pass_online_timestamp']['greater_than_equals'])
    //         && (int)$req['query']['range']['pass_online_timestamp']['greater_than_equals'] === 1658831580000) {
    //         $is_example_request = true;
    //     }
    // }

    $results = [];
    if ($is_example_request) {
        $results = [
            [
                'id' => 'res_001',
                'component_id' => 'comp_GT_001',
                'component_category' => 'GuideTyre',
                'pass_pass_id' => 'pass_1001',
                'pass_online_timestamp' => 1658831600000,
                'pass_direction' => 'Forward',
                'pass_avg_speed' => 85.3,
                'values' => [
                    ['timestamp' => 1658831600000, 'value' => 12.34],
                    ['timestamp' => 1658831610000, 'value' => 12.56]
                ],
                'details' => [
                    'threshold_value' => 15.0,
                    'comparison_operator' => 'LessThan'
                ]
            ],
            [
                'id' => 'res_002',
                'component_id' => 'comp_GT_002',
                'component_category' => 'GuideTyre',
                'pass_pass_id' => 'pass_1002',
                'pass_online_timestamp' => 1658831700000,
                'pass_direction' => 'Backward',
                'pass_avg_speed' => 80.1,
                'values' => [
                    ['timestamp' => 1658831700000, 'value' => 14.01],
                    ['timestamp' => 1658831710000, 'value' => 13.88]
                ],
                'details' => [
                    'threshold_value' => 15.0,
                    'comparison_operator' => 'LessThan'
                ]
            ]
        ];
    } else {
        $count = max(1, min($limit, 5));
        for ($i = 0; $i < $count; $i++) {
            $ts = round(microtime(true) * 1000) - ($offset + $i) * 60000;
            $results[] = [
                'id' => 'res_' . str_pad((string)($offset + $i + 1), 3, '0', STR_PAD_LEFT),
                'component_id' => 'comp_' . ($i + 1),
                'component_category' => 'GenericComponent',
                'pass_pass_id' => 'pass_' . (1000 + $i),
                'pass_online_timestamp' => $ts,
                'values' => [
                    ['timestamp' => $ts, 'value' => round(10 + $i + (mt_rand() / mt_getrandmax()), 2)]
                ]
            ];
        }
    }

    $resp = [
        'limit' => $limit,
        'offset' => $offset,
        'results' => $results
    ];
    if ($wantTotal) {
        $resp['total'] = $offset + count($results);
    }

    echo json_encode($resp);
    exit(0);
}

http_response_code(404);
echo json_encode(['error' => 'not_found', 'method' => $method, 'path' => $uri]);
