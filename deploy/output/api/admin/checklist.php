<?php
/**
 * CRUD para checklist del reporte técnico
 * GET    /api/admin/checklist.php?audit_id=X  — obtener items de una auditoría
 * PUT    /api/admin/checklist.php              — toggle item (crear o actualizar)
 * DELETE /api/admin/checklist.php?audit_id=X   — borrar todos los items de una auditoría
 */

require_once __DIR__ . '/../bootstrap.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// GET lo pueden ejecutar admin o dueño del audit (P5.10). Las mutaciones
// quedan admin-only porque los users administran el checklist vivo vía
// /api/user/project-checklist.php (es un concepto distinto).
if ($method === 'GET') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) {
        Response::error(Translator::t('admin_api.common.audit_id_required'), 400);
    }
    AuditAccess::require((string) $auditId);

    $items = $db->query(
        "SELECT metric_id, completed, notes, completed_at FROM checklist_items WHERE audit_id = ? ORDER BY created_at",
        [$auditId]
    );

    Response::success($items);
}

// A partir de acá, mutaciones — admin-only
Auth::requireAuth();

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $auditId = $body['auditId'] ?? '';
    $metricId = $body['metricId'] ?? '';
    $completed = $body['completed'] ?? false;
    $notes = $body['notes'] ?? null;

    if (empty($auditId) || empty($metricId)) {
        Response::error(Translator::t('admin_api.checklist.audit_and_metric_required'), 400);
    }

    // Upsert: insertar o actualizar
    $existing = $db->queryOne(
        "SELECT id FROM checklist_items WHERE audit_id = ? AND metric_id = ?",
        [$auditId, $metricId]
    );

    if ($existing) {
        $db->execute(
            "UPDATE checklist_items SET completed = ?, notes = ?, completed_at = ? WHERE audit_id = ? AND metric_id = ?",
            [$completed ? 1 : 0, $notes, $completed ? date('c') : null, $auditId, $metricId]
        );
    } else {
        $db->execute(
            "INSERT INTO checklist_items (audit_id, metric_id, completed, notes, completed_at) VALUES (?, ?, ?, ?, ?)",
            [$auditId, $metricId, $completed ? 1 : 0, $notes, $completed ? date('c') : null]
        );
    }

    Response::success(['ok' => true]);
}

if ($method === 'DELETE') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) {
        Response::error(Translator::t('admin_api.common.audit_id_required'), 400);
    }

    $db->execute("DELETE FROM checklist_items WHERE audit_id = ?", [$auditId]);
    Response::success(['ok' => true]);
}
