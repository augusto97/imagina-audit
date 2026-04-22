<?php
/**
 * GET /api/admin/waterfall.php?id=AUDIT_ID
 * Retorna waterfall + CrUX + resource breakdown + lighthouse audits
 */

require_once __DIR__ . '/../bootstrap.php';

$auditId = $_GET['id'] ?? '';
if (empty($auditId)) {
    Response::error(Translator::t('admin_api.waterfall.id_required'), 400);
}
AuditAccess::require((string) $auditId);

$db = Database::getInstance();
$row = $db->queryOne("SELECT waterfall_json FROM audits WHERE id = ?", [$auditId]);

if (!$row) {
    Response::error(Translator::t('admin_api.common.audit_not_found'), 404);
}

$data = $row['waterfall_json'] ? (JsonStore::decode($row['waterfall_json']) ?? []) : [];

// Handle old format (array of requests) vs new format (object with waterfall + crux + etc)
if (isset($data['waterfall'])) {
    Response::success($data);
} else {
    // Old format: waterfall_json was just the array of requests
    Response::success([
        'waterfall' => $data,
        'crux' => null,
        'resourceBreakdown' => [],
        'lighthouseAudits' => [],
    ]);
}
