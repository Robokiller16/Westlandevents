<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $user = require_admin();
    if (($user['role'] ?? '') !== 'owner') json_response(['ok' => false, 'error' => 'Owner rechten nodig.'], 403);
    $data = request_json();
    $users = users_all();

    if (($data['action'] ?? '') === 'delete') {
        $id = (string) ($data['id'] ?? '');
        $target = null;
        foreach ($users as $item) {
            if (($item['id'] ?? '') === $id) $target = $item;
        }
        if (!$target) json_response(['ok' => false, 'error' => 'Gebruiker niet gevonden.'], 404);
        if (($target['id'] ?? '') === ($user['id'] ?? '')) json_response(['ok' => false, 'error' => 'Je kunt jezelf niet verwijderen.'], 400);
        if (($target['role'] ?? '') === 'owner' && count(array_filter($users, fn($item) => ($item['role'] ?? '') === 'owner')) <= 1) {
            json_response(['ok' => false, 'error' => 'Minimaal een owner moet blijven bestaan.'], 400);
        }
        $users = array_values(array_filter($users, fn($item) => ($item['id'] ?? '') !== $id));
    } else {
        $username = strtolower(clean_text($data['username'] ?? '', 80));
        $password = (string) ($data['password'] ?? '');
        if (!preg_match('/^[a-z0-9_-]{3,32}$/', $username) || strlen($password) < 12) {
            json_response(['ok' => false, 'error' => 'Naam 3-32 tekens en wachtwoord minimaal 12 tekens.'], 400);
        }
        foreach ($users as $existing) {
            if (strtolower((string) ($existing['username'] ?? '')) === $username) {
                json_response(['ok' => false, 'error' => 'Deze gebruikersnaam bestaat al.'], 409);
            }
        }
        $users[] = [
            'id' => 'user-' . time() . random_int(100, 999),
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => ($data['role'] ?? '') === 'owner' ? 'owner' : 'admin',
            'rsn' => clean_text($data['rsn'] ?? $username, 80),
        ];
    }

    users_save($users);
    $state = state_load();
    audit($state, 'users-updated', $user);
    state_save($state);
    json_response(['ok' => true, 'users' => array_map('public_user', $users)]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
