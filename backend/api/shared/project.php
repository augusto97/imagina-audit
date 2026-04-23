<?php
/**
 * Endpoint público de un proyecto compartido. NO requiere sesión — el
 * security model es "posesión del token es acceso".
 *
 *   GET /api/shared/project.php?token=TOKEN
 *
 * Response: project summary + score + timeline + audits list + evolution.
 *
 * Deliberadamente NO expone: user email/name, internal notes del user,
 * checklist items (son del owner, no del cliente que mira), IP/metadata
 * del log. El audit id de cada elemento del historial sí se devuelve
 * porque /results/:id ya es público por diseño.
 */

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || strlen($token) !== 32 || !ctype_xdigit($token)) {
    // Validación básica del formato antes de tocar la DB.
    Response::error(Translator::t('projects.share.invalid_token'), 404);
}

$db = Database::getInstance();
try {
    $project = $db->queryOne(
        "SELECT id, name, url, domain, icon, color, created_at FROM projects WHERE share_token = ?",
        [$token]
    );
    if (!$project) {
        Response::error(Translator::t('projects.share.invalid_token'), 404);
    }

    $audits = $db->query(
        "SELECT id, url, global_score, global_level, is_wordpress, scan_duration_ms, created_at
         FROM audits WHERE project_id = ? AND is_deleted = 0
         ORDER BY created_at DESC LIMIT 20",
        [$project['id']]
    );

    $evolution = Project::lastAuditsDiff($db, (int) $project['id']);

    Response::success([
        'project' => [
            'id' => (int) $project['id'],
            'name' => $project['name'],
            'url' => $project['url'],
            'domain' => $project['domain'],
            'icon' => $project['icon'],
            'color' => $project['color'],
            'createdAt' => $project['created_at'],
        ],
        'audits' => array_map(fn($a) => [
            'id' => $a['id'],
            'url' => $a['url'],
            'globalScore' => (int) $a['global_score'],
            'globalLevel' => $a['global_level'],
            'isWordPress' => (int) $a['is_wordpress'] === 1,
            'scanDurationMs' => (int) $a['scan_duration_ms'],
            'createdAt' => $a['created_at'],
        ], $audits),
        'evolution' => $evolution,
    ]);
} catch (Throwable $e) {
    Logger::error('shared/project falló: ' . $e->getMessage());
    Response::error(Translator::t('projects.share.invalid_token'), 404);
}
