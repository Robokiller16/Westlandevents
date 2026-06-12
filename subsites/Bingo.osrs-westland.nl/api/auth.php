<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $data = request_json();
    $action = (string) ($data['action'] ?? $_GET['action'] ?? '');
    $config = app_config();

    if ($action === 'session') {
        json_response([
            'ok' => true,
            'role' => $_SESSION['role'] ?? '',
            'teamId' => $_SESSION['teamId'] ?? '',
        ]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'login') {
        $login = strtolower(trim((string) ($data['login'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        foreach (admin_users() as $admin) {
            if (($admin['login'] ?? '') === $login && password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
                $_SESSION['role'] = 'admin';
                $_SESSION['adminLogin'] = $login;
                $_SESSION['teamId'] = 'team1';
                json_response(['ok' => true, 'role' => 'admin', 'teamId' => 'team1', 'adminLogin' => $login]);
            }
        }

        $teamMap = [
            'beren' => 'team1',
            'flamingos' => 'team2',
            'snoesjes' => 'team3',
        ];
        if (isset($config['team_codes'][$login], $teamMap[$login]) && hash_equals((string) $config['team_codes'][$login], $password)) {
            $_SESSION['role'] = 'team';
            $_SESSION['teamId'] = $teamMap[$login];
            json_response(['ok' => true, 'role' => 'team', 'teamId' => $teamMap[$login]]);
        }

        json_response(['ok' => false, 'error' => 'Login of code klopt niet.'], 401);
    }

    if ($action === 'admins') {
        require_admin();
        json_response(['ok' => true, 'admins' => array_map(fn($user) => $user['login'], admin_users())]);
    }

    if ($action === 'addAdmin') {
        require_admin();
        $login = strtolower(trim((string) ($data['login'] ?? '')));
        $password = trim((string) ($data['password'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{3,32}$/', $login)) {
            json_response(['ok' => false, 'error' => 'Admin login moet 3-32 tekens zijn.'], 400);
        }
        if (strlen($password) < 6) {
            json_response(['ok' => false, 'error' => 'Kies minimaal 6 tekens.'], 400);
        }
        $users = array_values(array_filter(admin_users(), fn($user) => ($user['login'] ?? '') !== $login));
        $users[] = ['login' => $login, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)];
        save_admin_users($users);
        json_response(['ok' => true, 'admins' => array_map(fn($user) => $user['login'], $users)]);
    }

    if ($action === 'removeAdmin') {
        require_admin();
        $login = strtolower(trim((string) ($data['login'] ?? '')));
        if ($login === 'admin') {
            json_response(['ok' => false, 'error' => 'De hoofdadmin blijft bestaan.'], 400);
        }
        $users = array_values(array_filter(admin_users(), fn($user) => ($user['login'] ?? '') !== $login));
        save_admin_users($users);
        json_response(['ok' => true, 'admins' => array_map(fn($user) => $user['login'], $users)]);
    }

    if ($action === 'resetAdminPassword') {
        $answer = strtolower(trim((string) ($data['answer'] ?? '')));
        $newPassword = trim((string) ($data['password'] ?? ''));
        if (!hash_equals(strtolower((string) $config['admin_reset_answer']), $answer)) {
            json_response(['ok' => false, 'error' => 'Veiligheidsantwoord klopt niet.'], 400);
        }
        if (strlen($newPassword) < 4) {
            json_response(['ok' => false, 'error' => 'Kies een admin wachtwoord van minimaal 4 tekens.'], 400);
        }
        $users = admin_users();
        $found = false;
        foreach ($users as &$user) {
            if (($user['login'] ?? '') === 'admin') {
                $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $found = true;
            }
        }
        unset($user);
        if (!$found) {
            $users[] = ['login' => 'admin', 'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)];
        }
        save_admin_users($users);
        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
