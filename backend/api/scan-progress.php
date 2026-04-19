<?php
/**
 * GET /api/scan-progress?id=UUID — Estado actual de un audit en curso.
 *
 * El frontend hace polling a este endpoint mientras espera que un audit
 * termine. Retorna 404 si el audit no está en curso o ya expiró (10 min tras
 * completarse). Para leer resultados ya guardados en BD usar
 * `/api/audit-status.php`.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    Response::error('id requerido', 400);
}

$state = AuditProgress::get($id);
if (!$state) {
    Response::error('Progreso no encontrado o expirado', 404);
}

Response::success($state);
