<?php
/**
 * GET /api/setup — Estado del setup.
 * POST /api/setup — Configurar la password admin por primera vez.
 *
 * Este endpoint solo funciona mientras NO exista una password admin
 * configurada. Una vez configurada, devuelve 403 para todos los intentos
 * posteriores (así el admin no puede resetear la password sin acceso
 * al servidor).
 *
 * Reemplaza la necesidad del archivo hash-tmp.php manual que se pedía
 * antes. El admin sube los archivos, abre /admin en el navegador, ve
 * la pantalla de setup, pone su password, y listo.
 */
require_once __DIR__ . '/bootstrap.php';

function hasAdminConfigured(): bool {
    // Check .env
    $envHash = env('ADMIN_PASSWORD_HASH', '');
    if (!empty($envHash)) return true;

    // Check DB
    try {
        $db = Database::getInstance();
        $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_password_hash'");
        return $row && !empty($row['value']);
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::success([
        'needsSetup' => !hasAdminConfigured(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

// A partir de aquí, POST: intento de configurar password inicial
if (hasAdminConfigured()) {
    Response::error(
        'El setup ya se completó. Para cambiar la password, entra al admin y ve a Configuración → General.',
        403
    );
}

$body = Response::getJsonBody();
$password = (string) ($body['password'] ?? '');
$confirm = (string) ($body['confirm'] ?? '');

if (strlen($password) < 10) {
    Response::error('La password debe tener al menos 10 caracteres.', 400);
}
if ($password !== $confirm) {
    Response::error('Las passwords no coinciden.', 400);
}

try {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db = Database::getInstance();
    $db->execute(
        "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES ('admin_password_hash', ?, datetime('now'))",
        [$hash]
    );
    Logger::info('Setup inicial completado — admin password configurada');
    Response::success(['ok' => true]);
} catch (Throwable $e) {
    Logger::error('Setup falló: ' . $e->getMessage());
    Response::error('Error al guardar la configuración.', 500);
}
