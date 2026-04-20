<?php
/**
 * POST /api/admin/pin-audit — Alterna la protección anti-borrado de un audit.
 *
 * Los audits con `is_pinned=1` quedan excluidos de la retención automática.
 * Útil para conservar informes importantes (clientes firmados, casos de
 * estudio) mientras el resto se purga por edad.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$auditId = trim($body['auditId'] ?? '');
if (empty($auditId)) {
    Response::error('auditId requerido', 400);
}
$pinned = !empty($body['pinned']) ? 1 : 0;

try {
    $db = Database::getInstance();
    $row = $db->queryOne("SELECT id FROM audits WHERE id = ?", [$auditId]);
    if (!$row) {
        Response::error('Auditoría no encontrada', 404);
    }
    $db->execute("UPDATE audits SET is_pinned = ? WHERE id = ?", [$pinned, $auditId]);
    Response::success(['auditId' => $auditId, 'isPinned' => (bool) $pinned]);
} catch (Throwable $e) {
    Logger::error('pin-audit falló: ' . $e->getMessage());
    Response::error('Error al actualizar.', 500);
}
