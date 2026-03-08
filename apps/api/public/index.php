<?php

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

echo json_encode([
    'service' => 'finwise-api',
    'status' => 'ok',
    'message' => 'Stage 0 API stub',
], JSON_UNESCAPED_UNICODE);

