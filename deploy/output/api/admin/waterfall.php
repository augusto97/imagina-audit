<?php
/**
 * GET /api/admin/waterfall.php?id=AUDIT_ID
 * Retorna los datos del waterfall de una auditoría específica
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$auditId = $_GET['id'] ?? '';
if (empty($auditId)) {
    Response::error('id requerido', 400);
}

$db = Database::getInstance();
$row = $db->queryOne("SELECT waterfall_json FROM audits WHERE id = ?", [$auditId]);

if (!$row) {
    Response::error('Auditoría no encontrada', 404);
}

$waterfall = $row['waterfall_json'] ? json_decode($row['waterfall_json'], true) : [];

Response::success($waterfall ?: []);
