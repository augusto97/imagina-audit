<?php
/**
 * Toggle + rotación del share_token de un proyecto.
 *
 *   POST   /user/project-share.php
 *          Body: { projectId, rotate?: boolean }
 *          Si el proyecto no tiene token: genera uno nuevo.
 *          Si tiene token y rotate=true: invalida el viejo + genera uno nuevo.
 *          Si tiene token y rotate=false: idempotente, devuelve el actual.
 *   DELETE /user/project-share.php?project_id=N
 *          Borra el share_token (el link público queda 404 instantáneo).
 *
 * El enforcement de auth + ownership está arriba del todo; el endpoint
 * asume que si llegamos al switch es que UserAuth + project-owner pasaron.
 */

require_once __DIR__ . '/../bootstrap.php';
UserAuth::requireAuth();

$user = UserAuth::currentUser();
$userId = (int) $user['id'];
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

function requireOwnedProjectShare(Database $db, int $userId, int $projectId): array {
    $p = $db->queryOne("SELECT id, user_id, share_token FROM projects WHERE id = ?", [$projectId]);
    if (!$p) Response::error(Translator::t('projects.not_found'), 404);
    if ((int) $p['user_id'] !== $userId) Response::error(Translator::t('projects.not_owner'), 403);
    return $p;
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $projectId = (int) ($body['projectId'] ?? 0);
    $rotate = !empty($body['rotate']);
    if ($projectId === 0) Response::error(Translator::t('api.common.param_required', ['param' => 'projectId']), 400);

    $p = requireOwnedProjectShare($db, $userId, $projectId);

    try {
        $currentToken = $p['share_token'] ?? null;
        if ($currentToken && !$rotate) {
            Response::success(['enabled' => true, 'token' => $currentToken]);
        }
        $newToken = Project::generateShareToken($db);
        $db->execute("UPDATE projects SET share_token = ? WHERE id = ?", [$newToken, $projectId]);
        Response::success(['enabled' => true, 'token' => $newToken]);
    } catch (Throwable $e) {
        Logger::error('user/project-share POST falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.share.toggle_error'), 500);
    }
}

if ($method === 'DELETE') {
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId === 0) Response::error(Translator::t('api.common.param_required', ['param' => 'project_id']), 400);
    requireOwnedProjectShare($db, $userId, $projectId);

    try {
        $db->execute("UPDATE projects SET share_token = NULL WHERE id = ?", [$projectId]);
        Response::success(['enabled' => false]);
    } catch (Throwable $e) {
        Logger::error('user/project-share DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.share.toggle_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
