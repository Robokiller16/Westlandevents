<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();

    if (empty($_SESSION['role'])) {
        json_response(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->prepare('SELECT state_json FROM app_state WHERE id = 1');
        $stmt->execute();
        $state = $stmt->fetchColumn();
        json_response([
            'ok' => true,
            'state' => $state ? json_decode((string) $state, true) : null,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = request_json();
        if (!isset($data['state']) || !is_array($data['state'])) {
            json_response(['ok' => false, 'error' => 'Geen geldige state ontvangen.'], 400);
        }

        $incoming = $data['state'];
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $stmt = db()->prepare('SELECT state_json FROM app_state WHERE id = 1');
            $stmt->execute();
            $existing = json_decode((string) $stmt->fetchColumn(), true);
            $teamId = (string) ($_SESSION['teamId'] ?? '');
            $safe = is_array($existing) ? $existing : ['teams' => [], 'sources' => [], 'customUsers' => [], 'bosses' => []];
            $safe['sources'] = $incoming['sources'] ?? ($safe['sources'] ?? []);
            $safe['bosses'] = $incoming['bosses'] ?? ($safe['bosses'] ?? []);
            $safe['teams'] ??= [];
            foreach (($incoming['teams'] ?? []) as $incomingTeamId => $teamState) {
                $safe['teams'][$incomingTeamId] ??= ['completed' => [], 'owners' => [], 'pending' => []];
                if ($incomingTeamId === $teamId) {
                    $safe['teams'][$incomingTeamId]['owners'] = $teamState['owners'] ?? [];
                    $safe['teams'][$incomingTeamId]['pending'] = $teamState['pending'] ?? [];
                }
            }
            $incoming = $safe;
        }

        $json = json_encode($incoming, JSON_UNESCAPED_SLASHES);
        $stmt = db()->prepare(
            'INSERT INTO app_state (id, state_json) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE state_json = VALUES(state_json)'
        );
        $stmt->execute([$json]);
        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
