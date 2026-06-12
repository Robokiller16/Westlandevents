<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $user = require_admin();
    $data = request_json();
    $state = state_load();
    $collection = (string) ($data['collection'] ?? '');
    $allowed = [
        'news' => ['title', 'tag', 'body', 'date'],
        'events' => ['title', 'type', 'date', 'host', 'status'],
        'members' => ['rsn', 'rank', 'role', 'combat', 'total', 'status'],
        'loot' => ['item', 'player', 'value', 'date'],
    ];

    if (!isset($allowed[$collection])) json_response(['ok' => false, 'error' => 'Onbekende collectie.'], 400);

    if (($data['action'] ?? '') === 'delete') {
        $id = (string) ($data['id'] ?? '');
        $state[$collection] = array_values(array_filter($state[$collection], fn($item) => ($item['id'] ?? '') !== $id));
        audit($state, "{$collection}-delete", $user, ['id' => $id]);
    } else {
        $item = is_array($data['item'] ?? null) ? $data['item'] : [];
        $next = ['id' => $item['id'] ?? $collection . '-' . time() . random_int(100, 999)];
        foreach ($allowed[$collection] as $field) {
            $next[$field] = clean_text($item[$field] ?? '', $field === 'body' ? 700 : 180);
        }
        array_unshift($state[$collection], $next);
        audit($state, "{$collection}-save", $user, ['id' => $next['id']]);
    }

    state_save($state);
    json_response(['ok' => true, 'state' => $state]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
