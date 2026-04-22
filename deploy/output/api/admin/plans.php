<?php
/**
 * CRUD de planes de cuota (admin-only).
 *
 *   GET    /admin/plans.php             → lista con conteo de usuarios asignados
 *   POST   /admin/plans.php             → crea plan (name, monthly_limit, description, is_active)
 *   PUT    /admin/plans.php             → actualiza plan existente (body.id)
 *   DELETE /admin/plans.php?id=N        → borra plan si no está asignado a usuarios
 *
 * monthly_limit = 0 se interpreta como cuota ilimitada (para el plan interno
 * del equipo). Valores negativos se rechazan.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rows = $db->query(
            "SELECT p.id, p.name, p.monthly_limit, p.max_projects, p.description, p.is_active, p.created_at,
                    COALESCE(uc.cnt, 0) AS user_count
             FROM plans p
             LEFT JOIN (SELECT plan_id, COUNT(*) AS cnt FROM users WHERE plan_id IS NOT NULL GROUP BY plan_id) uc
                ON uc.plan_id = p.id
             ORDER BY p.is_active DESC, p.monthly_limit ASC, p.name ASC"
        );
        $plans = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'monthlyLimit' => (int) $r['monthly_limit'],
            'maxProjects' => (int) ($r['max_projects'] ?? 0),
            'description' => $r['description'],
            'isActive' => (int) $r['is_active'] === 1,
            'createdAt' => $r['created_at'],
            'userCount' => (int) $r['user_count'],
        ], $rows);
        Response::success(['plans' => $plans, 'total' => count($plans)]);
    } catch (Throwable $e) {
        Logger::error('admin/plans GET falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.plans.fetch_error'), 500);
    }
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $name = trim((string) ($body['name'] ?? ''));
    $limit = (int) ($body['monthlyLimit'] ?? 0);
    $maxProjects = (int) ($body['maxProjects'] ?? 0);
    $description = trim((string) ($body['description'] ?? ''));
    $isActive = !empty($body['isActive']) ? 1 : 0;

    if ($name === '') {
        Response::error(Translator::t('admin_api.plans.name_required'), 400);
    }
    if ($limit < 0 || $maxProjects < 0) {
        Response::error(Translator::t('admin_api.plans.limit_invalid'), 400);
    }

    try {
        $db->execute(
            "INSERT INTO plans (name, monthly_limit, max_projects, description, is_active) VALUES (?, ?, ?, ?, ?)",
            [$name, $limit, $maxProjects, $description !== '' ? $description : null, $isActive]
        );
        Response::success(['id' => (int) $db->lastInsertId()], 201);
    } catch (Throwable $e) {
        Logger::error('admin/plans POST falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.plans.create_error'), 500);
    }
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $id = (int) ($body['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('admin_api.common.id_required'), 400);

    $name = trim((string) ($body['name'] ?? ''));
    $limit = (int) ($body['monthlyLimit'] ?? 0);
    $maxProjects = (int) ($body['maxProjects'] ?? 0);
    $description = trim((string) ($body['description'] ?? ''));
    $isActive = !empty($body['isActive']) ? 1 : 0;

    if ($name === '') {
        Response::error(Translator::t('admin_api.plans.name_required'), 400);
    }
    if ($limit < 0 || $maxProjects < 0) {
        Response::error(Translator::t('admin_api.plans.limit_invalid'), 400);
    }

    try {
        $exists = $db->scalar("SELECT 1 FROM plans WHERE id = ?", [$id]);
        if (!$exists) Response::error(Translator::t('admin_api.plans.not_found'), 404);

        $db->execute(
            "UPDATE plans SET name = ?, monthly_limit = ?, max_projects = ?, description = ?, is_active = ? WHERE id = ?",
            [$name, $limit, $maxProjects, $description !== '' ? $description : null, $isActive, $id]
        );
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/plans PUT falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.plans.update_error'), 500);
    }
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('admin_api.common.id_required'), 400);

    try {
        $inUse = (int) $db->scalar("SELECT COUNT(*) FROM users WHERE plan_id = ?", [$id]);
        if ($inUse > 0) {
            Response::error(Translator::t('admin_api.plans.in_use', ['count' => $inUse]), 409);
        }
        $db->execute("DELETE FROM plans WHERE id = ?", [$id]);
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/plans DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.plans.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
