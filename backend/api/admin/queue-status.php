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
    $hourAgo = $db->nowMinus(3600);

    // Contadores de estado actual
    $running = QueueManager::runningCount();
    $queued = QueueManager::queuedCount();
    $maxConcurrent = QueueManager::getMaxConcurrent();

    // Fallidos en la última hora (para detectar sitios problemáticos)
    $failedLastHour = (int) $db->scalar(
        "SELECT COUNT(*) FROM audit_jobs WHERE status = 'failed' AND completed_at > ?",
        [$hourAgo]
    );

    // Completados en la última hora (para medir throughput real)
    $completedLastHour = (int) $db->scalar(
        "SELECT COUNT(*) FROM audit_jobs WHERE status = 'completed' AND completed_at > ?",
        [$hourAgo]
    );

    // Latencia media — calculada en PHP para evitar julianday (SQLite) /
    // TIMESTAMPDIFF (MySQL). Trae N filas y promedia.
    $completedRows = $db->query(
        "SELECT started_at, completed_at FROM audit_jobs
         WHERE status = 'completed' AND completed_at > ? AND started_at IS NOT NULL",
        [$hourAgo]
    );
    $avgDurationSec = 0.0;
    if (!empty($completedRows)) {
        $sum = 0;
        foreach ($completedRows as $r) {
            $sum += max(0, strtotime((string) $r['completed_at']) - strtotime((string) $r['started_at']));
        }
        $avgDurationSec = $sum / count($completedRows);
    }

    // Jobs running ahora — age_sec se computa en PHP.
    $runningJobsRaw = $db->query(
        "SELECT audit_id, url, started_at
         FROM audit_jobs WHERE status = 'running' ORDER BY started_at ASC"
    );
    $now = time();
    $runningJobs = array_map(function ($j) use ($now) {
        $startTs = $j['started_at'] ? strtotime((string) $j['started_at']) : null;
        $j['age_sec'] = $startTs ? max(0, $now - $startTs) : 0;
        return $j;
    }, $runningJobsRaw);

    // URL problemáticas (más de 3 fails en la última hora)
    $problematicUrls = $db->query(
        "SELECT url, COUNT(*) AS failures, MAX(error_message) AS last_error
         FROM audit_jobs
         WHERE status = 'failed'
         AND completed_at > ?
         GROUP BY url
         HAVING COUNT(*) >= 3
         ORDER BY failures DESC LIMIT 10",
        [$hourAgo]
    );

    // Comando de cron recomendado, con la ruta real del script resuelta.
    // El admin lo copia tal cual a cPanel → Cron Jobs. Es el fallback que
    // garantiza que la cola se drene aunque el self-kick falle en este
    // hosting (shell_exec deshabilitado + self-HTTP bloqueado).
    $drainScript = realpath(dirname(__DIR__, 2) . '/cron/drain-queue.php')
        ?: (dirname(__DIR__, 2) . '/cron/drain-queue.php');
    $phpBin = PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php';
    $cronInfo = [
        'command'   => sprintf('* * * * * %s %s', $phpBin, $drainScript),
        'scriptPath' => $drainScript,
        'phpBinary' => $phpBin,
        'frequency' => 'cada 1 minuto',
        'note'      => 'Configúralo en cPanel → Cron Jobs. La cola igual se procesa sin él (el propio polling la drena), pero el cron es la red de seguridad recomendada.',
    ];

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
        'cron' => $cronInfo,
    ]);
} catch (Throwable $e) {
    Logger::error('queue-status falló: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.queue_status.error'), 500);
}
