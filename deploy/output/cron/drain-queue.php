<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * CRON — Dead-man switch de la cola de auditorías
 * Frecuencia recomendada: cada 5 minutos
 * Comando cPanel:
 *   php /home/TUUSUARIO/public_html/audit/cron/drain-queue.php
 * ═══════════════════════════════════════════════════════════════
 *
 * En condiciones normales la cola se drena sola: cuando un audit
 * termina, el mismo request coge el siguiente job 'queued' y lo
 * procesa. Este cron es para cuando algo se rompe:
 *
 *   - Un proceso PHP murió a mitad del audit (OOM, crash del
 *     servidor, timeout) dejando un job en estado 'running' para
 *     siempre: lo marca como 'failed' tras audit_stale_seconds.
 *
 *   - Hay jobs 'queued' pero nadie los está procesando (p. ej. la
 *     cola quedó llena justo cuando crasheó el último worker): los
 *     drena activamente.
 *
 * Si todo está fluyendo bien, este cron sale rápido sin hacer nada.
 */

// Bootstrap mínimo ANTES de validar token: necesitamos QueueManager
// para resolver el token (puede vivir en settings, no solo en .env).
require_once dirname(__DIR__) . '/config/env.php';
spl_autoload_register(function (string $class) {
    $paths = [
        dirname(__DIR__) . '/lib/' . $class . '.php',
        dirname(__DIR__) . '/lib/db/' . $class . '.php',
        dirname(__DIR__) . '/analyzers/' . $class . '.php',
    ];
    foreach ($paths as $p) { if (file_exists($p)) { require_once $p; return; } }
});

if (php_sapi_name() !== 'cli') {
    $token = (string) ($_GET['token'] ?? '');
    // Resolvemos el token esperado: .env > settings (auto-generado).
    // Si ensureCronToken falla (DB caída), denegamos. Antes caíamos al
    // literal 'cambiar-este-token' como fallback, que dejaba el endpoint
    // accesible si el admin no había configurado el token.
    $expected = '';
    try { $expected = QueueManager::ensureCronToken(); } catch (Throwable $e) {}
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        die('Acceso denegado');
    }
}

// Margen generoso — 4 min, para procesar ~5-6 audits seguidos si hace falta
set_time_limit(240);
ini_set('memory_limit', '256M');

// CRÍTICO para HTTP kicks: cuando /api/audit nos llama con curl + timeout
// muy bajo, el cliente cierra la conexión casi inmediato. Sin esto PHP
// mataría el script al detectar el cliente desconectado, abortando el scan.
ignore_user_abort(true);

// Cerrar la sesión PHP del request entrante (si la había) para no
// retener el lock mientras drenamos jobs largos.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    Database::getInstance()->initSchema();

    // 1. Matar jobs huérfanos ('running' que llevan mucho tiempo)
    $reaped = QueueManager::reapStaleRunning();

    // 2. Drenar la cola si hay jobs 'queued' y slots libres
    $queued = QueueManager::queuedCount();
    $running = QueueManager::runningCount();
    $max = QueueManager::getMaxConcurrent();

    $processed = 0;
    if ($queued > 0 && $running < $max) {
        // Dejamos 30s de margen al set_time_limit
        $processed = QueueManager::drain(200);
    }

    $msg = sprintf(
        '[%s] drain-queue: reaped=%d queued_before=%d running=%d max=%d processed=%d',
        date('Y-m-d H:i:s'), $reaped, $queued, $running, $max, $processed
    );

    if (php_sapi_name() === 'cli') {
        echo $msg . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stats' => [
                'reaped' => $reaped,
                'queuedBefore' => $queued,
                'running' => $running,
                'max' => $max,
                'processed' => $processed,
            ],
        ]);
    }
    CronHealth::markRun('drain-queue', null, "processed=$processed");
} catch (Throwable $e) {
    Logger::error('drain-queue cron falló: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
