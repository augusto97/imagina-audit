<?php
/**
 * GET /api/diag.php — Diagnóstico público del sistema.
 *
 * NO requiere autenticación a propósito — si el admin no puede loguearse
 * porque algo está mal, necesita este endpoint para ver qué está roto.
 *
 * Expone solo metadata útil para diagnóstico (nunca datos de leads, nunca
 * contenido de audits, nunca tokens). Cada check es un semáforo verde/
 * amarillo/rojo con un mensaje corto.
 */
require_once __DIR__ . '/bootstrap.php';

$checks = [];

function addCheck(array &$checks, string $id, string $label, string $status, string $message, array $details = []): void {
    $checks[] = [
        'id' => $id,
        'label' => $label,
        'status' => $status, // 'ok' | 'warn' | 'fail'
        'message' => $message,
        'details' => $details,
    ];
}

// 1. PHP version
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
addCheck($checks, 'php_version', 'Versión de PHP',
    $phpOk ? 'ok' : 'fail',
    $phpOk ? 'PHP ' . PHP_VERSION : 'Se requiere PHP 8.0+, tienes ' . PHP_VERSION,
    ['version' => PHP_VERSION, 'sapi' => PHP_SAPI]
);

// 2. Extensiones requeridas
$requiredExt = ['curl', 'dom', 'json', 'openssl', 'mbstring', 'pdo', 'pdo_sqlite'];
$missing = array_filter($requiredExt, fn($e) => !extension_loaded($e));
addCheck($checks, 'php_extensions', 'Extensiones PHP',
    empty($missing) ? 'ok' : 'fail',
    empty($missing) ? 'Todas presentes' : 'Faltan: ' . implode(', ', $missing),
    ['required' => $requiredExt, 'missing' => array_values($missing)]
);

// 3. fastcgi_finish_request (óptimo) o fallback
$hasFastcgi = function_exists('fastcgi_finish_request');
addCheck($checks, 'fastcgi_finish_request', 'Background processing',
    $hasFastcgi ? 'ok' : 'warn',
    $hasFastcgi ? 'fastcgi_finish_request disponible' : 'Modo fallback (ignore_user_abort). Funciona pero menos eficiente — considera cambiar a PHP-FPM',
    ['sapi' => PHP_SAPI]
);

// 4. Carpetas writables críticas
$backendDir = dirname(__DIR__);
$writables = [
    'cache' => $backendDir . '/cache',
    'logs' => $backendDir . '/logs',
    'database' => $backendDir . '/database',
];
foreach ($writables as $key => $path) {
    if (!is_dir($path)) {
        addCheck($checks, "dir_$key", "Carpeta $key/", 'fail',
            'No existe: ' . basename($path) . '/',
            ['path' => basename($path)]
        );
        continue;
    }
    $writable = is_writable($path);
    // Test real: intentar escribir un archivo temporal
    $testFile = $path . '/.diag_test_' . uniqid();
    $canWrite = $writable && @file_put_contents($testFile, 'x') !== false;
    if ($canWrite) @unlink($testFile);

    addCheck($checks, "dir_$key", "Carpeta $key/ escribible",
        $canWrite ? 'ok' : 'fail',
        $canWrite ? 'OK' : 'El proceso PHP no puede escribir. Ajustar permisos a 755.',
        ['path' => basename($path), 'perms' => substr(sprintf('%o', @fileperms($path)), -4)]
    );
}

// 5. Base de datos accesible
try {
    $db = Database::getInstance();
    $db->initSchema();
    $tableCount = (int) $db->scalar("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'");
    addCheck($checks, 'db_connection', 'Base de datos SQLite', 'ok',
        "Operativa — $tableCount tablas",
        ['tables' => $tableCount]
    );

    // Check tablas críticas
    $requiredTables = ['audits', 'settings', 'audit_jobs', 'rate_limits', 'vulnerabilities'];
    $foundTables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'");
    $tableNames = array_column($foundTables, 'name');
    $missingTables = array_diff($requiredTables, $tableNames);
    addCheck($checks, 'db_schema', 'Esquema de BD',
        empty($missingTables) ? 'ok' : 'fail',
        empty($missingTables) ? 'Todas las tablas presentes' : 'Faltan tablas: ' . implode(', ', $missingTables),
        ['required' => $requiredTables, 'missing' => array_values($missingTables)]
    );

    // Check is_pinned column (migración)
    $hasIsPinned = false;
    $cols = $db->query("PRAGMA table_info(audits)");
    foreach ($cols as $c) {
        if ($c['name'] === 'is_pinned') { $hasIsPinned = true; break; }
    }
    addCheck($checks, 'db_migration', 'Migraciones aplicadas',
        $hasIsPinned ? 'ok' : 'warn',
        $hasIsPinned ? 'Columna is_pinned presente' : 'Migración pendiente — recarga la página para aplicar',
        []
    );
} catch (Throwable $e) {
    addCheck($checks, 'db_connection', 'Base de datos', 'fail',
        'Error: ' . $e->getMessage(),
        []
    );
}

// 6. .env configurado
$envKeys = [
    'APP_ENV' => false,
    'ADMIN_PASSWORD_HASH' => true,  // crítico
    'ALLOWED_ORIGIN' => false,
    'CRON_SECRET_TOKEN' => false,
];
$envProblems = [];
$hasAdminHash = false;
foreach ($envKeys as $key => $critical) {
    $val = env($key, null);
    if (empty($val)) {
        if ($critical) $envProblems[] = "$key (crítico)";
        else $envProblems[] = $key;
    }
    if ($key === 'ADMIN_PASSWORD_HASH' && !empty($val)) $hasAdminHash = true;
}

// Check también en DB (el setup wizard guarda ahí en vez de .env)
if (!$hasAdminHash) {
    try {
        $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'admin_password_hash'");
        if ($row && !empty($row['value'])) $hasAdminHash = true;
    } catch (Throwable $e) {}
}

addCheck($checks, 'env_admin_hash', 'Password admin configurada',
    $hasAdminHash ? 'ok' : 'warn',
    $hasAdminHash ? 'OK' : 'Primera instalación: ve a /admin para configurar la password',
    []
);

// 7. URL Rewriting funcional (si llegamos aquí vía /api/diag, sí funciona)
$viaRewrite = isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/api/diag');
addCheck($checks, 'url_rewrite', 'URL rewriting del backend',
    $viaRewrite ? 'ok' : 'warn',
    $viaRewrite ? 'Apache rutea /api/* correctamente' : 'Respuesta vía path directo',
    ['requestUri' => $_SERVER['REQUEST_URI'] ?? '']
);

// 8. Cron drain-queue activo (heurística: si hay jobs running > audit_stale, el cron no está corriendo)
try {
    $db = Database::getInstance();
    $staleRunning = (int) $db->scalar(
        "SELECT COUNT(*) FROM audit_jobs WHERE status = 'running' AND started_at < datetime('now', '-5 minutes')"
    );
    $totalJobs = (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs");
    if ($totalJobs === 0) {
        addCheck($checks, 'cron_drain', 'Cron drain-queue', 'warn',
            'Aún no hay jobs procesados — lanza un audit de prueba primero',
            []
        );
    } elseif ($staleRunning > 0) {
        addCheck($checks, 'cron_drain', 'Cron drain-queue', 'fail',
            "Hay $staleRunning job(s) atascados en 'running' > 5 min. El cron */5 no está ejecutando drain-queue.php",
            ['stuckJobs' => $staleRunning]
        );
    } else {
        addCheck($checks, 'cron_drain', 'Cron drain-queue', 'ok',
            'Sin jobs atascados',
            []
        );
    }
} catch (Throwable $e) {
    addCheck($checks, 'cron_drain', 'Cron drain-queue', 'warn', 'Check no disponible', []);
}

// 9. Google PageSpeed conectable (check rápido, 3s timeout)
try {
    $ch = curl_init('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://google.com&category=performance&strategy=mobile');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $reachable = curl_errno($ch) === 0;
    curl_close($ch);
    addCheck($checks, 'google_pagespeed', 'Google PageSpeed API',
        $reachable ? 'ok' : 'warn',
        $reachable ? 'Alcanzable (los audits podrán correr)' : 'No accesible — los audits fallarán en el paso performance',
        []
    );
} catch (Throwable $e) {
    addCheck($checks, 'google_pagespeed', 'Google PageSpeed API', 'warn', 'Check falló', []);
}

// 10. RAM del sistema (via SystemInfo si está disponible)
try {
    if (class_exists('SystemInfo')) {
        $snap = SystemInfo::snapshot();
        $ramLabel = $snap['totalRamMb'] !== null ? ($snap['totalRamMb'] . ' MB') : 'no detectable';
        addCheck($checks, 'system_ram', 'Memoria RAM del servidor', 'ok',
            "RAM: $ramLabel · Recomendado max_concurrent: " . ($snap['recommendedConcurrency'] ?? '?'),
            $snap
        );
    }
} catch (Throwable $e) {}

// Resumen
$summary = [
    'ok' => count(array_filter($checks, fn($c) => $c['status'] === 'ok')),
    'warn' => count(array_filter($checks, fn($c) => $c['status'] === 'warn')),
    'fail' => count(array_filter($checks, fn($c) => $c['status'] === 'fail')),
];
$overall = $summary['fail'] > 0 ? 'fail' : ($summary['warn'] > 0 ? 'warn' : 'ok');

Response::success([
    'overall' => $overall,
    'summary' => $summary,
    'checks' => $checks,
    'generatedAt' => date('c'),
]);
