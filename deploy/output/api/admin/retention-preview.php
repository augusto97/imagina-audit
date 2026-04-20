<?php
/**
 * GET /api/admin/retention-preview?months=N
 *
 * Vista previa de la retención: cuántos audits serían eliminados si se
 * activa la política de retención con N meses. Útil para mostrar al admin
 * el impacto antes de confirmar.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$months = (int) ($_GET['months'] ?? 6);
if ($months < 1 || $months > 120) {
    Response::error('months debe estar entre 1 y 120', 400);
}

$days = $months * 30;

try {
    $db = Database::getInstance();

    $total = (int) $db->scalar("SELECT COUNT(*) FROM audits");
    $pinned = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE is_pinned = 1");

    // Audits más viejos que el umbral — excluyendo los pinned
    $toDelete = (int) $db->scalar(
        "SELECT COUNT(*) FROM audits
         WHERE created_at < datetime('now', ?)
         AND is_pinned = 0",
        ["-$days days"]
    );

    // Fecha de corte (informativa)
    $cutoffRow = $db->queryOne("SELECT datetime('now', ?) AS cutoff", ["-$days days"]);
    $cutoffDate = $cutoffRow['cutoff'] ?? null;

    // Estimación de espacio liberado (sumando tamaño de result_json + waterfall_json)
    $sizeRow = $db->queryOne(
        "SELECT
            COALESCE(SUM(LENGTH(result_json)), 0) AS result_bytes,
            COALESCE(SUM(LENGTH(waterfall_json)), 0) AS waterfall_bytes
         FROM audits
         WHERE created_at < datetime('now', ?)
         AND is_pinned = 0",
        ["-$days days"]
    );
    $estimatedBytes = (int) ($sizeRow['result_bytes'] ?? 0) + (int) ($sizeRow['waterfall_bytes'] ?? 0);

    Response::success([
        'months' => $months,
        'cutoffDate' => $cutoffDate,
        'totalAudits' => $total,
        'pinnedAudits' => $pinned,
        'wouldDelete' => $toDelete,
        'wouldKeep' => $total - $toDelete,
        'estimatedBytesFreed' => $estimatedBytes,
    ]);
} catch (Throwable $e) {
    Logger::error('retention-preview falló: ' . $e->getMessage());
    Response::error('Error al calcular preview.', 500);
}
