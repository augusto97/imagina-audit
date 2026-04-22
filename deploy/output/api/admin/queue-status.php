<?php
/**
 * GET /api/admin/queue-status
 *
 * Snapshot en vivo de la cola + información del sistema. Úsalo para
 * monitorear en el admin cuántos audits están corriendo, cuántos
 * esperan, cuántos fallaron recientemente, y si el `audit_max_concurrent`
 * actual es coherente con la RAM detectada.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

try {
    $db = Database::getInstance();

    // Contadores de estado actual
    $running = QueueManager::runningCount();
    $queued = QueueManager::queuedCount();
    $maxConcurrent = QueueManager::getMaxConcurrent();

    // Fallidos en la última hora (para detectar sitios problemáticos)
    $failedLastHour = (int) $db->scalar(
        "SELECT COUNT(*) FROM audit_jobs WHERE status = 'failed' AND completed_at > datetime('now', '-1 hour')"
    );

    // Completados en la última hora (para medir throughput real)
    $completedLastHour = (int) $db->scalar(
        "SELECT COUNT(*) FROM audit_jobs WHERE status = 'completed' AND completed_at > datetime('now', '-1 hour')"
    );

    // Latencia media del último procesado (útil para estimar esperas)
    $avgDurationSec = (float) ($db->scalar(
        "SELECT AVG((julianday(completed_at) - julianday(started_at)) * 86400)
         FROM audit_jobs
         WHERE status = 'completed'
         AND completed_at > datetime('now', '-1 hour')
         AND started_at IS NOT NULL"
    ) ?? 0);

    // Jobs running ahora (con antigüedad, para detectar stuck)
    $runningJobs = $db->query(
        "SELECT audit_id, url, started_at,
                (julianday('now') - julianday(started_at)) * 86400 AS age_sec
         FROM audit_jobs WHERE status = 'running' ORDER BY started_at ASC"
    );

    // URL problemáticas (más de 3 fails en la última hora)
    $problematicUrls = $db->query(
        "SELECT url, COUNT(*) AS failures, MAX(error_message) AS last_error
         FROM audit_jobs
         WHERE status = 'failed'
         AND completed_at > datetime('now', '-1 hour')
         GROUP BY url
         HAVING COUNT(*) >= 3
         ORDER BY failures DESC LIMIT 10"
    );

    Response::success([
        'concurrency' => [
            'running' => $running,
            'queued' => $queued,
            'maxConcurrent' => $maxConcurrent,
            'utilizationPct' => $maxConcurrent > 0 ? (int) round(($running / $maxConcurrent) * 100) : 0,
        ],
        'lastHour' => [
            'completed' => $completedLastHour,
            'failed' => $failedLastHour,
            'avgDurationSec' => round($avgDurationSec, 1),
        ],
        'runningJobs' => array_map(fn($j) => [
            'auditId' => $j['audit_id'],
            'url' => $j['url'],
            'startedAt' => $j['started_at'],
            'ageSec' => round((float) $j['age_sec'], 1),
        ], $runningJobs),
        'problematicUrls' => array_map(fn($r) => [
            'url' => $r['url'],
            'failures' => (int) $r['failures'],
            'lastError' => $r['last_error'],
        ], $problematicUrls),
        'system' => SystemInfo::snapshot(),
        'recommendationTable' => SystemInfo::recommendationTable(),
    ]);
} catch (Throwable $e) {
    Logger::error('queue-status falló: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.queue_status.error'), 500);
}
