<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
start_secure_session();

try {
    ensure_schema();
    $data = request_json();
    $action = (string) ($data['action'] ?? $_GET['action'] ?? '');

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'login') {
        $login = strtolower(clean_text($data['username'] ?? $data['login'] ?? '', 80));
        $password = (string) ($data['password'] ?? '');
        $ip = client_ip();
        if (login_is_limited($ip)) {
            json_response(['ok' => false, 'error' => 'Te veel mislukte logins. Probeer het later opnieuw.'], 429);
        }
        foreach (users_all() as $user) {
            if (strtolower((string) ($user['username'] ?? '')) === $login && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                session_regenerate_id(true);
                $_SESSION['userId'] = $user['id'];
                login_attempt($ip, $login, true);
                json_response(['ok' => true, 'user' => public_user($user)]);
            }
        }
        login_attempt($ip, $login, false);
        json_response(['ok' => false, 'error' => 'Gebruikersnaam of wachtwoord klopt niet.'], 401);
    }

    json_response(['ok' => false, 'error' => 'Onbekende auth actie.'], 400);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
