<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        (int) ($config['db_port'] ?? 3306),
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }

    return $data;
}

function ensure_schema(): void
{
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_state (
            id TINYINT UNSIGNED PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS drop_screenshots (
            id VARCHAR(80) PRIMARY KEY,
            team_id VARCHAR(40) NOT NULL,
            drop_id VARCHAR(120) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(80) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            created_by_role VARCHAR(40) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute(['admin_password_hash']);
    if (!$stmt->fetchColumn()) {
        $config = app_config();
        $hash = password_hash((string) $config['admin_default_password'], PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
        $insert->execute(['admin_password_hash', $hash]);
    }

    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute(['admin_users_json']);
    if (!$stmt->fetchColumn()) {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $stmt->execute(['admin_password_hash']);
        $existingHash = (string) $stmt->fetchColumn();
        $hash = $existingHash !== '' ? $existingHash : password_hash((string) app_config()['admin_default_password'], PASSWORD_DEFAULT);
        $users = [['login' => 'admin', 'password_hash' => $hash]];
        $insert = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
        $insert->execute(['admin_users_json', json_encode($users, JSON_UNESCAPED_SLASHES)]);
    }
}

function admin_users(): array
{
    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute(['admin_users_json']);
    $users = json_decode((string) $stmt->fetchColumn(), true);
    return is_array($users) ? $users : [];
}

function save_admin_users(array $users): void
{
    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute(['admin_users_json', json_encode(array_values($users), JSON_UNESCAPED_SLASHES)]);
}

function require_login(): void
{
    if (empty($_SESSION['role'])) {
        json_response(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        json_response(['ok' => false, 'error' => 'Admin rechten nodig.'], 403);
    }
}
