<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

try {
    ensure_schema();
    json_response([
        'ok' => true,
        'php' => PHP_VERSION,
        'database' => 'connected',
    ]);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'error' => $error->getMessage(),
    ], 500);
}
