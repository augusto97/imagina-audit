<?php
/**
 * GET /api/health — Healthcheck del backend
 */
require_once __DIR__ . '/bootstrap.php';

$checks = [
    'php' => PHP_VERSION,
    'sqlite' => extension_loaded('pdo_sqlite'),
    'curl' => extension_loaded('curl'),
    'dom' => extension_loaded('dom'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl'),
    'mbstring' => extension_loaded('mbstring'),
];

// Verificar conexión a la DB
$dbOk = false;
$dbDriver = null;
$dbPingMs = null;
$migrationsPending = null;
try {
    $db = Database::getInstance();
    $start = microtime(true);
    $db->scalar("SELECT 1");
    $dbPingMs = (int) ((microtime(true) - $start) * 1000);
    $dbOk = true;
    $dbDriver = $db->driver();
    if (class_exists('Migrator')) {
        try {
            $migrator = new Migrator($db);
            $migrator->bootstrap();
            $migrationsPending = count($migrator->pending());
        } catch (Throwable $e) { /* ignore */ }
    }
} catch (Throwable $e) {
    // DB no disponible
}
$checks['database'] = $dbOk;

// Verificar escritura en cache
$cacheOk = false;
$cacheDir = dirname(__DIR__) . '/cache';
if (is_writable($cacheDir)) {
    $cacheOk = true;
}
$checks['cache_writable'] = $cacheOk;

// Verificar escritura en logs
$logsOk = false;
$logsDir = dirname(__DIR__) . '/logs';
if (is_writable($logsDir)) {
    $logsOk = true;
}
$checks['logs_writable'] = $logsOk;

$allOk = !in_array(false, $checks, true);

// ?ready=1[&expect=X.Y.Z] — usado por el actualizador tras cambiar de
// versión: exige DB viva, 0 migraciones pendientes, carpetas escribibles y
// (si se pasa expect) que el código en ejecución sea esa versión. La
// versión sale de BuildInfo (compilada en el código), así que si OPcache
// siguiera sirviendo la versión vieja el check falla en vez de mentir.
if (isset($_GET['ready'])) {
    header('Cache-Control: no-store');
    $version = AppVersion::current();
    $expect = (string) ($_GET['expect'] ?? '');
    $problems = [];
    if (!$dbOk) $problems[] = 'base de datos no disponible';
    if ($migrationsPending !== 0) $problems[] = 'migraciones pendientes: ' . var_export($migrationsPending, true);
    if (!$cacheOk || !$logsOk) $problems[] = 'cache/logs sin permiso de escritura';
    if ($expect !== '' && $expect !== $version) $problems[] = "versión en ejecución $version (se esperaba $expect)";
    http_response_code($problems ? 503 : 200);
    echo json_encode(['success' => !$problems, 'data' => [
        'ready' => !$problems,
        'version' => $version,
        'problems' => $problems,
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code($allOk ? 200 : 503);
Response::success([
    'status' => $allOk ? 'healthy' : 'degraded',
    'checks' => $checks,
    'db' => [
        'driver' => $dbDriver,
        'pingMs' => $dbPingMs,
        'migrationsPending' => $migrationsPending,
    ],
    'timestamp' => date('c'),
]);
