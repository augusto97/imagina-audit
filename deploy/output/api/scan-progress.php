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
}

Response::success($state);
