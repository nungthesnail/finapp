<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if ($uri === '/health' || $uri === '/api/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'service' => 'finwise-api',
        'status' => 'healthy',
    ], JSON_UNESCAPED_UNICODE);
    return true;
}

require __DIR__ . '/index.php';
return true;

