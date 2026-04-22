<?php
/**
 * Vista admin de los proyectos de todos los users.
 *
 *   GET    /admin/projects.php[?user_id=N&search=X]
 *          Lista global con join al user dueño. Usado en dashboard admin y
 *          en la ficha del user (/admin/users/:id) para ver su portfolio.
 *   DELETE /admin/projects.php?id=N
 *          Borrado admin de un proyecto (soporte/limpieza). Los audits
 *          asociados pierden project_id pero quedan en el historial.
 *
 * No hay POST/PUT — los users crean y editan sus propios proyectos. El
 * admin solo observa + limpia.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
    $search = trim((string) ($_GET['search'] ?? ''));

    $where = '1=1';
    $params = [];
    if ($userId !== null && $userId > 0) {
        $where .= ' AND p.user_id = ?';
        $params[] = $userId;
    }
    if ($search !== '') {
        $where .= ' AND (p.name LIKE ? OR p.domain LIKE ? OR p.url LIKE ?)';
        $search = "%$search%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    try {
        $rows = $db->query(
            "SELECT p.id, p.user_id, p.name, p.url, p.domain, p.notes, p.icon, p.color,
                    p.share_token, p.created_at,
                    u.email AS user_email, u.name AS user_name,
                    (SELECT COUNT(*) FROM audits a WHERE a.project_id = p.id) AS audit_count,
                    (SELECT a.global_score FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_score,
                    (SELECT a.global_level FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_level,
                    (SELECT a.created_at   FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_at
             FROM projects p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE $where
             ORDER BY COALESCE(
                (SELECT a.created_at FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1),
                p.created_at
             ) DESC",
            $params
        );

        $projects = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'userId' => (int) $r['user_id'],
            'userEmail' => $r['user_email'],
            'userName' => $r['user_name'],
            'name' => $r['name'],
            'url' => $r['url'],
            'domain' => $r['domain'],
            'notes' => $r['notes'],
            'icon' => $r['icon'],
            'color' => $r['color'],
            'sharingEnabled' => !empty($r['share_token']),
            'auditCount' => (int) $r['audit_count'],
            'createdAt' => $r['created_at'],
            'latestAudit' => $r['latest_score'] !== null ? [
                'globalScore' => (int) $r['latest_score'],
                'globalLevel' => $r['latest_level'],
                'createdAt' => $r['latest_at'],
            ] : null,
        ], $rows);

        Response::success(['projects' => $projects, 'total' => count($projects)]);
    } catch (Throwable $e) {
        Logger::error('admin/projects GET falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.projects.fetch_error'), 500);
    }
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('admin_api.common.id_required'), 400);

    try {
        $exists = $db->scalar("SELECT 1 FROM projects WHERE id = ?", [$id]);
        if (!$exists) Response::error(Translator::t('admin_api.projects.not_found'), 404);

        // Mismas reglas que user/projects.php DELETE — preserva audits
        $db->execute("UPDATE audits SET project_id = NULL WHERE project_id = ?", [$id]);
        $db->execute("DELETE FROM project_checklist_items WHERE project_id = ?", [$id]);
        $db->execute("DELETE FROM projects WHERE id = ?", [$id]);
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/projects DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.projects.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
