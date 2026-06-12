<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $state = state_load();
    $user = current_user();
    json_response([
        'ok' => true,
        'state' => $state,
        'user' => $user ? public_user($user) : null,
        'users' => (($user['role'] ?? '') === 'owner') ? array_map('public_user', users_all()) : [],
    ]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
