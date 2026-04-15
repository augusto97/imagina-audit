<?php
/**
 * POST /api/admin/update-vulnerabilities.php — Actualiza vulnerabilidades manualmente
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

set_time_limit(180);

try {
    $stats = VulnerabilityUpdater::run();
    Response::success([
        'newVulnerabilities' => $stats['new'],
        'updatedVulnerabilities' => $stats['updated'],
        'pluginsChecked' => $stats['checked'],
        'errors' => $stats['errors'],
    ]);
} catch (Throwable $e) {
    Logger::error('Error actualizando vulnerabilidades: ' . $e->getMessage());
    Response::error('Error al actualizar: ' . $e->getMessage(), 500);
}
