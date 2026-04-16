<?php
/**
 * GET /api/audit-status?id=X — Obtiene resultado de auditoría por ID
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

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
    Logger::error('Error obteniendo auditoría: ' . $e->getMessage());
    Response::error('Error al obtener la auditoría.', 500);
}
