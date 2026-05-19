<?php
/**
 * Acciones manuales sobre la cola de auditorías. Pensado para cuando el
 * kick automático falla (host sin shell_exec, sin curl, sin cron) y el
 * admin necesita destrabar la cola desde el panel.
 *
 *   POST { action: 'drain-now' }            → ejecuta drain en este request
 *   POST { action: 'clear-failures', url? } → borra audit_jobs failed
 *                                              (todos o por URL específica)
 *   POST { action: 'reset-running' }        → reinicia jobs colgados a queued
 *   POST { action: 'delete-job', auditId }  → elimina un job de la cola
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = Response::getJsonBody();
$action = $body['action'] ?? '';
$db = Database::getInstance();

if ($action === 'drain-now') {
    // Drena hasta 4 minutos. Cierra la sesión antes para no bloquear
    // el panel mientras el drain corre.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    set_time_limit(300);
    ignore_user_abort(true);

    try {
        $processed = QueueManager::drain(240);
        $remaining = QueueManager::queuedCount();
        $running = QueueManager::runningCount();
        Logger::info("Manual drain: processed=$processed queued_left=$remaining running=$running");
        Response::success([
            'processed' => $processed,
            'queuedLeft' => $remaining,
            'running' => $running,
        ]);
    } catch (Throwable $e) {
        Logger::error('Manual drain falló: ' . $e->getMessage());
        Response::error('Drain failed: ' . $e->getMessage(), 500);
    }
}

if ($action === 'clear-failures') {
    $url = trim((string) ($body['url'] ?? ''));
    try {
        if ($url !== '') {
            $deleted = $db->execute("DELETE FROM audit_jobs WHERE status = 'failed' AND url = ?", [$url]);
            Logger::info("Cleared failure cache for URL: $url ($deleted rows)");
        } else {
            $deleted = $db->execute("DELETE FROM audit_jobs WHERE status = 'failed'");
            Logger::info("Cleared all failure cache ($deleted rows)");
        }
        Response::success(['deleted' => $deleted]);
    } catch (Throwable $e) {
        Logger::error('Clear failures falló: ' . $e->getMessage());
        Response::error('Clear failed: ' . $e->getMessage(), 500);
    }
}

if ($action === 'reset-running') {
    // Jobs que llevan "running" demasiado tiempo (proceso PHP murió) →
    // los reseteamos a queued para que el próximo drain los reintente.
    try {
        $defaults = require dirname(__DIR__, 2) . '/config/defaults.php';
        $stale = (int) ($defaults['audit_stale_seconds'] ?? 180);
        $threshold = $db->nowMinus($stale);
        $count = $db->execute(
            "UPDATE audit_jobs SET status = 'queued', started_at = NULL, error_message = NULL WHERE status = 'running' AND started_at IS NOT NULL AND started_at < ?",
            [$threshold]
        );
        Logger::info("Reset $count stuck running jobs to queued");
        Response::success(['reset' => $count]);
    } catch (Throwable $e) {
        Response::error('Reset failed: ' . $e->getMessage(), 500);
    }
}

if ($action === 'delete-job') {
    $auditId = trim((string) ($body['auditId'] ?? ''));
    if ($auditId === '') Response::error('auditId required', 400);
    try {
        $deleted = $db->execute("DELETE FROM audit_jobs WHERE audit_id = ?", [$auditId]);
        Logger::info("Manual delete-job: $auditId ($deleted rows)");
        Response::success(['deleted' => $deleted]);
    } catch (Throwable $e) {
        Response::error('Delete failed: ' . $e->getMessage(), 500);
    }
}

Response::error('Unknown action', 400);
