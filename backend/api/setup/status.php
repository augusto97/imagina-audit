<?php
/**
 * GET /api/setup/status — Estado completo del setup.
 *
 * Endpoint público (sin auth) que el frontend consume al arrancar para
 * decidir si redirigir a /setup o seguir flujo normal. Devuelve:
 *
 *   - installed         bool  — existe el flag data/.installed
 *   - hasEnv            bool  — backend/.env existe
 *   - dbDriver          string — driver activo (sqlite|mysql)
 *   - dbConnected       bool  — la conexión al driver funciona
 *   - dbConnectError    string|null — mensaje si dbConnected=false
 *   - hasAdmin          bool  — hay password admin configurada
 *   - migrationsApplied int   — cuántas migraciones aplicadas
 *   - migrationsPending int   — cuántas pendientes
 *
 * Esto refleja el estado real del install, no "fue completado el wizard"
 * — un admin podría editar .env a mano y skipear el wizard, y el sistema
 * lo detecta correctamente como instalado.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$installFlag = dirname(__DIR__, 2) . '/data/.installed';
$installed = is_file($installFlag);
$hasEnv = is_file(dirname(__DIR__, 2) . '/.env');

$dbDriver = function_exists('env') ? strtolower(env('DB_DRIVER', 'sqlite')) : 'sqlite';
$dbConnected = false;
$dbError = null;
$migrationsApplied = 0;
$migrationsPending = 0;
$hasAdmin = false;

try {
    $db = Database::getInstance();
    $dbConnected = true;
    try {
        $migrator = new Migrator($db);
        $migrator->bootstrap();
        $status = $migrator->status();
        $migrationsApplied = $status['totalApplied'];
        $migrationsPending = count($status['pending']);
    } catch (Throwable $e) { /* tabla aún no creada */ }

    // hasAdmin = hay password en settings (post-migración) o en .env (legacy)
    $envHash = function_exists('env') ? env('ADMIN_PASSWORD_HASH', '') : '';
    if ($envHash !== '') {
        $hasAdmin = true;
    } else {
        try {
            $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'admin_password_hash'");
            $hasAdmin = $row !== null && !empty($row['value']);
        } catch (Throwable $e) { /* settings aún no existe */ }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

Response::success([
    'installed' => $installed,
    'hasEnv' => $hasEnv,
    'dbDriver' => $dbDriver,
    'dbConnected' => $dbConnected,
    'dbConnectError' => $dbError,
    'hasAdmin' => $hasAdmin,
    'migrationsApplied' => $migrationsApplied,
    'migrationsPending' => $migrationsPending,
]);
