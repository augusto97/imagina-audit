<?php
/**
 * Endpoints admin de backup.
 *
 *   GET                    → lista backups + estado del driver
 *   POST { action: 'create' } → genera un backup nuevo
 *   POST { action: 'restore', filename } → aplica un backup
 *   POST { action: 'delete', filename } → borra un backup
 *   GET ?download=filename → descarga el archivo
 *
 * El restore es destructivo — el front pide confirmación + escribe la
 * advertencia en la UI antes de mandarlo.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();

if ($method === 'GET' && isset($_GET['download'])) {
    $filename = basename((string) $_GET['download']);
    $path = Backup::dir() . '/' . $filename;
    if (!is_file($path)) Response::error('Backup not found', 404);
    header('Content-Type: ' . (str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/sql'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

if ($method === 'GET') {
    Response::success([
        'driver' => $db->driver(),
        'backups' => Backup::list(),
        'retention' => function_exists('env') ? (int) env('BACKUP_RETENTION_COUNT', '10') : 10,
    ]);
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = Response::getJsonBody();
$action = $body['action'] ?? '';

if ($action === 'create') {
    set_time_limit(300);
    try {
        $path = Backup::create($db);
        Logger::info('Backup created: ' . basename($path));
        Response::success([
            'ok' => true,
            'filename' => basename($path),
            'sizeBytes' => filesize($path),
        ]);
    } catch (Throwable $e) {
        Logger::error('Backup creation failed: ' . $e->getMessage());
        Response::error('Backup failed: ' . $e->getMessage(), 500);
    }
}

if ($action === 'restore') {
    $filename = basename((string) ($body['filename'] ?? ''));
    if ($filename === '') Response::error('filename is required', 400);
    $path = Backup::dir() . '/' . $filename;
    if (!is_file($path)) Response::error('Backup not found', 404);

    set_time_limit(300);
    try {
        $result = Backup::restore($db, $path);
        Logger::info('Backup restored: ' . $filename . ' (statements=' . ($result['statementsExecuted'] ?? '?') . ')');
        Response::success(['ok' => true, ...$result]);
    } catch (Throwable $e) {
        Logger::error('Restore failed: ' . $e->getMessage());
        Response::error('Restore failed: ' . $e->getMessage(), 500);
    }
}

if ($action === 'delete') {
    $filename = basename((string) ($body['filename'] ?? ''));
    if ($filename === '') Response::error('filename is required', 400);
    if (Backup::delete($filename)) {
        Response::success(['ok' => true]);
    }
    Response::error('Backup not found or could not be deleted', 404);
}

Response::error('Unknown action', 400);
