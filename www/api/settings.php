<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $user = require_admin();
    $data = request_json();
    $state = state_load();
    if (isset($data['clan']) && is_array($data['clan'])) {
        foreach (['name', 'motto', 'world', 'home', 'requirement', 'discord', 'motd'] as $field) {
            if (array_key_exists($field, $data['clan'])) $state['clan'][$field] = clean_text($data['clan'][$field], 260);
        }
    }
    audit($state, 'settings-saved', $user);
    state_save($state);
    json_response(['ok' => true, 'state' => $state]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
