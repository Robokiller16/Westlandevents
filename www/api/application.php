<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $data = request_json();
    $state = state_load();

    if (($data['action'] ?? '') === 'create') {
        $application = [
            'id' => 'app-' . time() . random_int(100, 999),
            'rsn' => clean_text($data['rsn'] ?? ''),
            'discord' => clean_text($data['discord'] ?? ''),
            'combat' => clean_text($data['combat'] ?? '', 20),
            'total' => clean_text($data['total'] ?? '', 20),
            'playstyle' => clean_text($data['playstyle'] ?? ''),
            'message' => clean_text($data['message'] ?? '', 700),
            'status' => 'pending',
            'createdAt' => gmdate('c'),
        ];
        if ($application['rsn'] === '' || $application['discord'] === '') {
            json_response(['ok' => false, 'error' => 'RSN en Discord zijn verplicht.'], 400);
        }
        array_unshift($state['applications'], $application);
        audit($state, 'application-created', null, ['rsn' => $application['rsn']]);
        state_save($state);
        json_response(['ok' => true, 'state' => $state]);
    }

    $user = require_admin();
    $id = (string) ($data['id'] ?? '');
    $status = ($data['status'] ?? '') === 'approved' ? 'approved' : 'rejected';
    foreach ($state['applications'] as &$application) {
        if (($application['id'] ?? '') === $id) {
            $application['status'] = $status;
            if ($status === 'approved') {
                array_unshift($state['members'], [
                    'id' => 'member-' . time() . random_int(100, 999),
                    'rsn' => $application['rsn'],
                    'rank' => 'Recruit',
                    'role' => $application['playstyle'] ?: 'Nieuw lid',
                    'combat' => $application['combat'],
                    'total' => $application['total'],
                    'status' => 'Nieuw',
                ]);
            }
            audit($state, "application-{$status}", $user, ['rsn' => $application['rsn']]);
            state_save($state);
            json_response(['ok' => true, 'state' => $state]);
        }
    }
    json_response(['ok' => false, 'error' => 'Aanvraag niet gevonden.'], 404);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
