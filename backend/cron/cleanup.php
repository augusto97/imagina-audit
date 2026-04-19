<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * CRON — Limpieza diaria de registros caducados
 * Frecuencia recomendada: diario a las 3:00 AM
 * Comando cPanel:
 *   php /home/TUUSUARIO/public_html/audit/cron/cleanup.php
 * ═══════════════════════════════════════════════════════════════
 *
 * Ejecutable por CLI o por HTTP con token (?token=XXX).
 *
 * Acciones:
 *   - Borra rate_limits de más de 1 hora.
 *   - Borra login_attempts de más de 15 minutos.
 *   - Borra archivos de cache expirados.
 *   - Rota logs > 30 días (Logger::rotate).
 */

if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    $expectedToken = getenv('CRON_SECRET_TOKEN') ?: 'cambiar-este-token';
    if ($token !== $expectedToken) {
        http_response_code(403);
        die('Acceso denegado');
    }
}

require_once dirname(__DIR__) . '/config/env.php';
spl_autoload_register(function (string $class) {
    $paths = [dirname(__DIR__) . '/lib/' . $class . '.php', dirname(__DIR__) . '/analyzers/' . $class . '.php'];
    foreach ($paths as $p) { if (file_exists($p)) { require_once $p; return; } }
});

set_time_limit(120);

$stats = ['rate_limits' => 0, 'login_attempts' => 0, 'audit_jobs' => 0, 'cache_files' => 0, 'log_files' => 0, 'errors' => []];

try {
    $db = Database::getInstance();
    $db->initSchema();

    // Rate limits (ventana de 1 hora)
    $stats['rate_limits'] = $db->execute("DELETE FROM rate_limits WHERE request_time < datetime('now', '-1 hour')");

    // Login attempts (ventana de 15 minutos)
    try {
        $stats['login_attempts'] = $db->execute("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-15 minutes')");
    } catch (Throwable $e) {
        // Tabla podría no existir aún si nadie ha intentado loguearse
    }

    // Audit jobs completed/failed viejos — ya se guardó el resultado en `audits`,
    // no hace falta mantener el registro de la cola más allá del período de
    // retención. Además, liberar espacio e índice hace el dequeue más rápido.
    try {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $retentionDays = (int) ($defaults['audit_jobs_retention_days'] ?? 7);
        if ($retentionDays > 0) {
            $stats['audit_jobs'] = $db->execute(
                "DELETE FROM audit_jobs
                 WHERE status IN ('completed', 'failed')
                 AND completed_at < datetime('now', ?)",
                ["-$retentionDays days"]
            );
        }
    } catch (Throwable $e) {
        $stats['errors'][] = 'audit_jobs cleanup: ' . $e->getMessage();
    }
} catch (Throwable $e) {
    $stats['errors'][] = 'DB cleanup: ' . $e->getMessage();
}

// Cache files expirados
try {
    $cacheDir = dirname(__DIR__) . '/cache';
    $ttl = (int) (env('CACHE_TTL_SECONDS', 86400));
    $cutoff = time() - $ttl;
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.json') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $stats['cache_files']++;
            }
        }
    }
} catch (Throwable $e) {
    $stats['errors'][] = 'Cache cleanup: ' . $e->getMessage();
}

// Rotación de logs
try {
    $before = count(glob(dirname(__DIR__) . '/logs/*.log') ?: []);
    Logger::rotate();
    $after = count(glob(dirname(__DIR__) . '/logs/*.log') ?: []);
    $stats['log_files'] = max(0, $before - $after);
} catch (Throwable $e) {
    $stats['errors'][] = 'Log rotation: ' . $e->getMessage();
}

$msg = sprintf(
    '[%s] cleanup: rate_limits=%d login_attempts=%d audit_jobs=%d cache_files=%d log_files=%d errors=%d',
    date('Y-m-d H:i:s'),
    $stats['rate_limits'], $stats['login_attempts'], $stats['audit_jobs'],
    $stats['cache_files'], $stats['log_files'],
    count($stats['errors'])
);

if (php_sapi_name() === 'cli') {
    echo $msg . PHP_EOL;
    foreach ($stats['errors'] as $err) echo "  err: $err" . PHP_EOL;
    exit(count($stats['errors']) > 0 ? 1 : 0);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'stats' => $stats]);
}
