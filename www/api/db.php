<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            json_response(['ok' => false, 'error' => 'Maak eerst api/config.php aan vanuit config.example.php.'], 500);
        }
        $config = require $file;
    }
    return $config;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = app_config();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['db_host'], (int) ($config['db_port'] ?? 3306), $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    security_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) json_response(['ok' => false, 'error' => 'Ongeldige JSON.'], 400);
    return $data;
}

function clean_text(mixed $value, int $max = 220): string
{
    return substr(trim((string) $value), 0, $max);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 80);
}

function default_state(): array
{
    return [
        'clan' => [
            'name' => 'West land',
            'motto' => 'Gezellig grinden, strak organiseren, samen winnen.',
            'world' => 'World tbd',
            'home' => 'Grand Exchange',
            'requirement' => 'Iedere speler welkom, respect verplicht.',
            'discord' => '',
            'motd' => 'Welkom bij West land. Check de events en meld je aan voor de volgende clan trip.',
        ],
        'stats' => [
            ['label' => 'Members', 'value' => '128'],
            ['label' => 'Weekly events', 'value' => '9'],
            ['label' => 'Clan rank', 'value' => 'Rising'],
            ['label' => 'Loot split', 'value' => 'Fair'],
        ],
        'news' => [
            ['id' => 'news-1', 'title' => 'West land homepage live', 'tag' => 'Clan', 'body' => 'Nieuwe plek voor events, leden, loot en aanvragen.', 'date' => '2026-06-11'],
        ],
        'events' => [
            ['id' => 'event-1', 'title' => 'Nex mass', 'type' => 'PvM', 'date' => 'Vrijdag 20:00', 'host' => 'Robert', 'status' => 'Open'],
            ['id' => 'event-2', 'title' => 'Skill & chill', 'type' => 'Social', 'date' => 'Zondag 19:30', 'host' => 'Mods', 'status' => 'Open'],
        ],
        'members' => [
            ['id' => 'member-1', 'rsn' => 'TBD', 'rank' => 'Owner', 'role' => 'Clan lead', 'combat' => '126', 'total' => '2200', 'status' => 'Online'],
        ],
        'loot' => [
            ['id' => 'loot-1', 'item' => 'Torva platebody', 'player' => 'Robert', 'value' => '410M', 'date' => 'Deze week'],
        ],
        'applications' => [],
        'audit' => [],
        'updatedAt' => gmdate('c'),
    ];
}

function ensure_schema(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS westland_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS westland_state (
        id TINYINT UNSIGNED PRIMARY KEY,
        state_json LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS westland_login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(80) NOT NULL,
        login_name VARCHAR(80) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_created (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare('SELECT setting_value FROM westland_settings WHERE setting_key = ?');
    $stmt->execute(['users_json']);
    if (!$stmt->fetchColumn()) {
        $password = (string) (app_config()['admin_setup_password'] ?? '');
        if ($password === '' || str_starts_with($password, 'VERANDER_DIT') || strlen($password) < 12) {
            json_response([
                'ok' => false,
                'error' => 'Beveiliging: vul eerst api/config.php met een eigen admin_setup_password van minimaal 12 tekens.',
            ], 500);
        }
        $users = [['id' => 'user-owner', 'username' => 'admin', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'owner', 'rsn' => 'Robert']];
        setting_set('users_json', json_encode($users, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $stmt = $pdo->prepare('SELECT state_json FROM westland_state WHERE id = 1');
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        state_save(default_state());
    }
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO westland_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function users_all(): array
{
    $stmt = db()->prepare('SELECT setting_value FROM westland_settings WHERE setting_key = ?');
    $stmt->execute(['users_json']);
    $users = json_decode((string) $stmt->fetchColumn(), true);
    return is_array($users) ? $users : [];
}

function users_save(array $users): void
{
    setting_set('users_json', json_encode(array_values($users), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function public_user(array $user): array
{
    return ['id' => $user['id'] ?? '', 'username' => $user['username'] ?? '', 'role' => $user['role'] ?? '', 'rsn' => $user['rsn'] ?? ''];
}

function state_load(): array
{
    $stmt = db()->prepare('SELECT state_json FROM westland_state WHERE id = 1');
    $stmt->execute();
    $state = json_decode((string) $stmt->fetchColumn(), true);
    return is_array($state) ? $state : default_state();
}

function state_save(array $state): void
{
    $state['updatedAt'] = gmdate('c');
    $stmt = db()->prepare('INSERT INTO westland_state (id, state_json) VALUES (1, ?) ON DUPLICATE KEY UPDATE state_json = VALUES(state_json)');
    $stmt->execute([json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function current_user(): ?array
{
    $id = (string) ($_SESSION['userId'] ?? '');
    foreach (users_all() as $user) {
        if (($user['id'] ?? '') === $id) return $user;
    }
    return null;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || !in_array(($user['role'] ?? ''), ['admin', 'owner'], true)) {
        json_response(['ok' => false, 'error' => 'Admin rechten nodig.'], 403);
    }
    return $user;
}

function login_is_limited(string $ip): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM westland_login_attempts WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= 8;
}

function login_attempt(string $ip, string $login, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO westland_login_attempts (ip, login_name, success) VALUES (?, ?, ?)');
    $stmt->execute([$ip, substr($login, 0, 80), $success ? 1 : 0]);
    db()->exec("DELETE FROM westland_login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
}

function audit(array &$state, string $action, ?array $user, array $details = []): void
{
    array_unshift($state['audit'], ['id' => 'audit-' . time() . random_int(100, 999), 'at' => gmdate('c'), 'action' => $action, 'user' => $user['username'] ?? 'public', 'details' => $details]);
    $state['audit'] = array_slice($state['audit'], 0, 80);
}
