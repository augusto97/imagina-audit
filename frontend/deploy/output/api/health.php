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
try {
    $db = Database::getInstance();
    $db->scalar("SELECT 1");
    $dbOk = true;
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

http_response_code($allOk ? 200 : 503);
Response::success([
    'status' => $allOk ? 'healthy' : 'degraded',
    'checks' => $checks,
    'timestamp' => date('c'),
]);
