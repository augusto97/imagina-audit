<?php
/**
 * GET /api/audit-status?id=X — Obtiene resultado de auditoría por ID
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    Response::error(Translator::t('api.audit.id_required'));
}

try {
    $db = Database::getInstance();
    $audit = $db->queryOne("SELECT result_json FROM audits WHERE id = ?", [$id]);

    if (!$audit) {
        Response::error(Translator::t('api.audit.not_found'), 404);
    }

    $result = JsonStore::decode($audit['result_json']);
    Response::success($result);
} catch (Throwable $e) {
    Logger::error('Error obteniendo auditoría: ' . $e->getMessage());
    Response::error(Translator::t('api.audit.fetch_error'), 500);
}
