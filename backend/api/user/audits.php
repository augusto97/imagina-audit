<?php
/**
 * /api/user/audits
 *
 *   GET     ?page=1&limit=20   → historial del user actual (filtra soft-deleted)
 *   DELETE  ?id=AUDIT_ID        → soft-delete de un audit propio
 *
 * NOTA IMPORTANTE: soft-delete NO libera cupo de cuota mensual. La query
 * UserAuth::currentMonthAuditCount() cuenta todos los audits del mes,
 * incluyendo los que el user marcó como eliminados. Borrar desde el panel
 * solo oculta el audit del historial — el slot ya fue consumido al momento
 * de correr el scan y no se devuelve.
 */

require_once __DIR__ . '/../bootstrap.php';
UserAuth::requireAuth();

$user = UserAuth::currentUser();
$userId = (int) $user['id'];
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    try {
        $total = (int) $db->scalar(
            "SELECT COUNT(*) FROM audits WHERE user_id = ? AND is_deleted = 0",
            [$userId]
        );
        $totalPages = (int) ceil($total / $limit);

        $rows = $db->query(
            "SELECT id, url, domain, global_score, global_level, is_wordpress, scan_duration_ms, created_at
             FROM audits
             WHERE user_id = ? AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );

        $audits = array_map(fn($r) => [
            'id' => $r['id'],
            'url' => $r['url'],
            'domain' => $r['domain'],
            'globalScore' => (int) $r['global_score'],
            'globalLevel' => $r['global_level'],
            'isWordPress' => (int) $r['is_wordpress'] === 1,
            'scanDurationMs' => (int) $r['scan_duration_ms'],
            'createdAt' => $r['created_at'],
        ], $rows);

        Response::success([
            'audits' => $audits,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ]);
    } catch (Throwable $e) {
        Logger::error('user/audits GET falló: ' . $e->getMessage());
        Response::error(Translator::t('user_api.audits.fetch_error'), 500);
    }
}

if ($method === 'DELETE') {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        Response::error(Translator::t('api.common.param_required', ['param' => 'id']), 400);
    }

    try {
        $row = $db->queryOne("SELECT user_id, is_deleted FROM audits WHERE id = ?", [$id]);
        if (!$row) Response::error(Translator::t('api.audit.not_found'), 404);
        $ownerId = $row['user_id'] !== null ? (int) $row['user_id'] : null;
        if ($ownerId !== $userId) {
            Response::error(Translator::t('projects.not_owner'), 403);
        }
        if ((int) $row['is_deleted'] === 1) {
            Response::success(['ok' => true, 'alreadyDeleted' => true]);
        }

        // Soft-delete. No tocamos user_id ni project_id — queremos conservar
        // la atribución para la cuenta mensual de cuota (count incluye
        // is_deleted=1).
        $db->execute(
            "UPDATE audits SET is_deleted = 1 WHERE id = ?",
            [$id]
        );
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('user/audits DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('user_api.audits.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
