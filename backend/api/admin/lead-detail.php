<?php
/**
 * GET /api/admin/lead-detail?id=UUID
 */
Auth::requireAuth();

$id = $_GET['id'] ?? '';
if (empty($id)) {
    Response::error('El parámetro id es obligatorio.');
}

try {
    $db = Database::getInstance();
    $audit = $db->queryOne("SELECT result_json FROM audits WHERE id = ?", [$id]);

    if (!$audit) {
        Response::error('Auditoría no encontrada.', 404);
    }

    $result = json_decode($audit['result_json'], true);
    Response::success($result);
} catch (Throwable $e) {
    Response::error('Error al obtener el detalle.', 500);
}
