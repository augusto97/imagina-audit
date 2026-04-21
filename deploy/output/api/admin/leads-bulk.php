<?php
/**
 * POST /admin/leads-bulk.php
 *   Body: { ids: [...], action: 'delete' | 'pin' | 'unpin' }
 *
 * Ejecuta la acción sobre múltiples audits a la vez.
 * - delete: respeta el lock de pinned (skip + reporta cuántos se saltaron)
 * - pin/unpin: toggle is_pinned
 *
 * Responde { processed, skipped, action }.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$ids = $body['ids'] ?? [];
$action = $body['action'] ?? '';

if (!is_array($ids) || empty($ids)) {
    Response::error('ids requerido (array no vacío)', 400);
}
if (!in_array($action, ['delete', 'pin', 'unpin'], true)) {
    Response::error('action inválida (delete|pin|unpin)', 400);
}

// Limitar batch para evitar abuse o locks largos
$ids = array_slice(array_values(array_unique(array_filter($ids, 'is_string'))), 0, 500);
if (empty($ids)) {
    Response::error('Ningún id válido en el batch', 400);
}

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$processed = 0;
$skipped = 0;

try {
    if ($action === 'delete') {
        // Saltar protegidos
        $pinnedIds = array_column(
            $db->query("SELECT id FROM audits WHERE id IN ($placeholders) AND is_pinned = 1", $ids),
            'id'
        );
        $deletable = array_values(array_diff($ids, $pinnedIds));
        $skipped = count($pinnedIds);

        if (!empty($deletable)) {
            $ph2 = implode(',', array_fill(0, count($deletable), '?'));
            $db->execute("DELETE FROM audits WHERE id IN ($ph2)", $deletable);
            try { $db->execute("DELETE FROM wp_snapshots WHERE audit_id IN ($ph2)", $deletable); } catch (Throwable $e) {}
            $processed = count($deletable);
        }
    } elseif ($action === 'pin') {
        $db->execute(
            "UPDATE audits SET is_pinned = 1
             WHERE id IN ($placeholders) AND (is_pinned = 0 OR is_pinned IS NULL)",
            $ids
        );
        $processed = (int) $db->scalar(
            "SELECT COUNT(*) FROM audits WHERE id IN ($placeholders) AND is_pinned = 1",
            $ids
        );
    } elseif ($action === 'unpin') {
        $db->execute(
            "UPDATE audits SET is_pinned = 0 WHERE id IN ($placeholders) AND is_pinned = 1",
            $ids
        );
        $processed = count($ids) - (int) $db->scalar(
            "SELECT COUNT(*) FROM audits WHERE id IN ($placeholders) AND is_pinned = 1",
            $ids
        );
    }

    Response::success([
        'processed' => $processed,
        'skipped'   => $skipped,
        'action'    => $action,
    ]);
} catch (Throwable $e) {
    Logger::error("Error en leads-bulk ($action): " . $e->getMessage());
    Response::error('Error ejecutando la acción en lote.', 500);
}
