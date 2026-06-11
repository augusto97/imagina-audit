<?php
/**
 * GET /api/scan-progress?id=UUID — Estado actual de un audit.
 *
 * El frontend hace polling a este endpoint mientras espera que un audit
 * termine. Si el audit está 'queued', recalcula la posición en cola en
 * vivo (otros podrían haber terminado desde la última actualización).
 *
 * Retorna 404 si no hay progreso registrado (auditId inexistente o ya
 * expiró tras 10 min de completarse — para resultados usar
 * `/api/audit-status.php`).
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    Response::error(Translator::t('api.progress.id_required'), 400);
}

$state = AuditProgress::get($id);
if (!$state) {
    Response::error(Translator::t('api.progress.not_found'), 404);
}

// Si está en cola, recalcular posición y total en tiempo real
if (($state['status'] ?? '') === 'queued') {
    $state['position'] = QueueManager::getPosition($id);
    $state['totalInQueue'] = QueueManager::queuedCount();

    // Auto-sanado de la cola: si este job sigue 'queued' pero hay un slot
    // libre, el drain previo (kick de audit.php) no arrancó — re-disparamos
    // aquí. Como el frontend hace polling cada ~1.5s, esto convierte el
    // propio polling en el motor de la cola: aunque shell_exec esté
    // deshabilitado y el self-kick HTTP haya fallado, cada poll le da otra
    // oportunidad de arrancar. El dequeue es atómico, así que kicks
    // solapados nunca producen doble procesamiento. Cuando el job pasa a
    // 'running' dejamos de kickear (no hay slot libre o ya no está queued).
    try {
        if (QueueManager::runningCount() < QueueManager::getMaxConcurrent()) {
            QueueManager::kickDrain();
        }
    } catch (Throwable $e) {
        // El kick es best-effort; el cron de fallback drenará igualmente.
    }
}

Response::success($state);
