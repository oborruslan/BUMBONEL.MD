<?php

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/autoload.php';

try {
    $config = require dirname(__DIR__) . '/config.php';
    $catalog = new ProductCatalog($config['products']['file']);
    echo json_encode($catalog->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Nu am putut incarca produsele.'], JSON_UNESCAPED_UNICODE);
}
