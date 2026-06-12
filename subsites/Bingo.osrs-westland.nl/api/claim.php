<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    require_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $data = request_json();
    $action = (string) ($data['action'] ?? '');
    $teamId = (string) ($data['teamId'] ?? '');
    $dropId = (string) ($data['dropId'] ?? '');

    if (!in_array($action, ['submit', 'approve', 'reject'], true) || $teamId === '' || $dropId === '') {
        json_response(['ok' => false, 'error' => 'Ongeldige claim actie.'], 400);
    }

    if ($action !== 'submit') {
        require_admin();
    }

    if ($action === 'submit' && ($_SESSION['role'] ?? '') !== 'admin') {
        $teamId = (string) ($_SESSION['teamId'] ?? '');
    }

    $stmt = db()->prepare('SELECT state_json FROM app_state WHERE id = 1');
    $stmt->execute();
    $state = json_decode((string) $stmt->fetchColumn(), true);
    if (!is_array($state)) {
        $state = ['teams' => [], 'sources' => [], 'customUsers' => [], 'customDrops' => [], 'bosses' => []];
    }

    $state['teams'][$teamId] ??= ['completed' => [], 'owners' => [], 'pending' => []];
    $team = &$state['teams'][$teamId];
    $team['completed'] ??= [];
    $team['owners'] ??= [];
    $team['pending'] ??= [];
    $claim = $team['pending'][$dropId] ?? null;

    if ($action === 'submit') {
        $current = $team['completed'][$dropId] ?? false;
        $completedCount = is_array($current) ? (int) ($current['count'] ?? 0) : ($current ? 1 : 0);
        $pending = is_array($claim) ? $claim : [];
        $owner = trim((string) ($data['owner'] ?? ''));
        $source = trim((string) ($data['source'] ?? ''));
        $screenshot = $data['screenshot'] ?? null;
        if ($owner !== '') {
            $pending['owner'] = $owner;
            $team['owners'][$dropId] = $owner;
        }
        if ($source !== '') {
            $state['sources'][$dropId] = $source;
        }
        if (is_array($screenshot)) {
            $pending['screenshot'] = $screenshot;
        }
        $pending['requestedAt'] = $pending['requestedAt'] ?? date(DATE_ATOM);
        $pending['updatedAt'] = date(DATE_ATOM);
        $pending['claimNumber'] = $completedCount + 1;
        $team['pending'][$dropId] = $pending;
    } elseif ($action === 'approve') {
        if (is_array($claim) && !empty($claim['owner'])) {
            $team['owners'][$dropId] = (string) $claim['owner'];
        }
        $current = $team['completed'][$dropId] ?? false;
        $currentCount = is_array($current) ? (int) ($current['count'] ?? 0) : ($current ? 1 : 0);
        $team['completed'][$dropId] = ['count' => $currentCount + 1];
    }

    if ($action !== 'submit') {
        unset($team['pending'][$dropId]);
    }
    unset($team);

    $json = json_encode($state, JSON_UNESCAPED_SLASHES);
    $stmt = db()->prepare(
        'INSERT INTO app_state (id, state_json) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE state_json = VALUES(state_json)'
    );
    $stmt->execute([$json]);

    json_response(['ok' => true, 'state' => $state]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
