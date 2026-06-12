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

    $teamId = (string) ($_POST['teamId'] ?? ($_SESSION['teamId'] ?? ''));
    $dropId = (string) ($_POST['dropId'] ?? '');
    $teamName = (string) ($_POST['teamName'] ?? $teamId);
    $userName = (string) ($_POST['userName'] ?? ($_SESSION['adminLogin'] ?? 'teamdrop'));
    $dropName = (string) ($_POST['dropName'] ?? $dropId);
    if ($dropId === '' || $teamId === '') {
        json_response(['ok' => false, 'error' => 'Drop of team ontbreekt.'], 400);
    }
    if (($_SESSION['role'] ?? '') !== 'admin' && $teamId !== ($_SESSION['teamId'] ?? '')) {
        json_response(['ok' => false, 'error' => 'Je mag alleen voor je eigen team uploaden.'], 403);
    }
    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'error' => 'Geen geldige screenshot ontvangen.'], 400);
    }

    $file = $_FILES['screenshot'];
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        json_response(['ok' => false, 'error' => 'Screenshot mag maximaal 8 MB zijn.'], 400);
    }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        json_response(['ok' => false, 'error' => 'Gebruik JPG, PNG, WEBP of GIF.'], 400);
    }

    $folderName = preg_replace('/[^a-zA-Z0-9 _.-]/', '', (string) (app_config()['upload_dir_name'] ?? 'Westland bingo 12-06-2026'));
    $uploadRoot = __DIR__ . '/uploads/' . $folderName;
    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0755, true)) {
        json_response(['ok' => false, 'error' => 'Uploadmap kon niet worden gemaakt.'], 500);
    }

    $id = bin2hex(random_bytes(16));
    $baseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $teamName . '-' . $userName . '-' . $dropName . '-' . date('Ymd-His'));
    $baseName = trim((string) $baseName, '-');
    $filename = $baseName . '-' . substr($id, 0, 8) . '.' . $extensions[$mime];
    $target = $uploadRoot . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        json_response(['ok' => false, 'error' => 'Upload opslaan mislukt.'], 500);
    }

    $relativePath = 'api/uploads/' . rawurlencode($folderName) . '/' . $filename;
    $stmt = db()->prepare(
        'INSERT INTO drop_screenshots (id, team_id, drop_id, original_name, mime_type, file_size, file_path, created_by_role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$id, $teamId, $dropId, (string) $file['name'], $mime, (int) $file['size'], $relativePath, (string) $_SESSION['role']]);

    json_response(['ok' => true, 'screenshot' => ['id' => $id, 'url' => $relativePath, 'name' => (string) $file['name']]]);
} catch (Throwable $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 500);
}
