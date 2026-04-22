<?php
/**
 * GET /api/user/audits — historial de audits del user actual.
 *
 * Query: ?page=1&limit=20
 * Respuesta: { audits: [...], total, page, totalPages }
 *
 * Cada audit incluye: id, url, domain, globalScore, globalLevel,
 * isWordPress, createdAt. No devolvemos result_json entero — el user
 * consulta el detalle con /api/audit-status.php?id=X (ya existente).
 */

require_once __DIR__ . '/../bootstrap.php';
UserAuth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$user = UserAuth::currentUser();
$userId = $user['id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    $total = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE user_id = ?", [$userId]);
    $totalPages = (int) ceil($total / $limit);

    $rows = $db->query(
        "SELECT id, url, domain, global_score, global_level, is_wordpress, scan_duration_ms, created_at
         FROM audits
         WHERE user_id = ?
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
    Logger::error('user/audits falló: ' . $e->getMessage());
    Response::error(Translator::t('user_api.audits.fetch_error'), 500);
}
