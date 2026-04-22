<?php
/**
 * CRUD de proyectos para el user autenticado.
 *
 *   GET    /user/projects.php              → lista con resumen (último audit, # audits, checklist counts)
 *   GET    /user/projects.php?id=N         → detalle (último audit + timeline + checklist summary + share state)
 *   POST   /user/projects.php              → crea (name, url, notes?, icon?, color?)
 *   PUT    /user/projects.php              → actualiza (id, name?, notes?, icon?, color?). La URL es inmutable.
 *   DELETE /user/projects.php?id=N         → elimina (los audits quedan con project_id=NULL, el checklist se borra por cascade)
 *
 * max_projects se valida SOLO en POST — si se baja el plan después, el user
 * conserva los proyectos que ya tenía (no se borran automáticamente).
 */

require_once __DIR__ . '/../bootstrap.php';
UserAuth::requireAuth();

$user = UserAuth::currentUser();
$userId = (int) $user['id'];
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && !isset($_GET['id'])) {
    try {
        // Usamos correlated subqueries en SELECT para evitar el problema de
        // SQLite con subqueries correlacionadas en FROM (no soporta LATERAL
        // estándar). Penalty de performance es despreciable — el N de
        // proyectos por user es bajo (<50 típico, plan limits son menores).
        $rows = $db->query(
            "SELECT p.id, p.name, p.url, p.domain, p.notes, p.icon, p.color,
                    p.share_token, p.created_at,
                    (SELECT COUNT(*) FROM audits a WHERE a.project_id = p.id) AS audit_count,
                    (SELECT COUNT(*) FROM project_checklist_items ci WHERE ci.project_id = p.id AND ci.status = 'open') AS open_count,
                    (SELECT a.id           FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_audit_id,
                    (SELECT a.global_score FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_score,
                    (SELECT a.global_level FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_level,
                    (SELECT a.created_at   FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1) AS latest_at
             FROM projects p
             WHERE p.user_id = ?
             ORDER BY COALESCE(
                (SELECT a.created_at FROM audits a WHERE a.project_id = p.id ORDER BY a.created_at DESC LIMIT 1),
                p.created_at
             ) DESC",
            [$userId]
        );

        $plan = $user['plan'] ?? null;
        $total = count($rows);
        $limit = $plan ? (int) $plan['monthlyLimit'] : 0;
        Response::success([
            'projects' => array_map(fn($r) => serializeProjectRow($r), $rows),
            'total' => $total,
            'quota' => [
                'maxProjects' => $plan['maxProjects'] ?? 0,
                'used' => $total,
                'remaining' => ($plan['maxProjects'] ?? 0) === 0 ? null : max(0, (int) $plan['maxProjects'] - $total),
                'unlimited' => ($plan['maxProjects'] ?? 0) === 0,
            ],
        ]);
    } catch (Throwable $e) {
        Logger::error('user/projects GET falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.fetch_error'), 500);
    }
}

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $project = $db->queryOne(
            "SELECT id, user_id, name, url, domain, notes, icon, color, share_token, created_at
             FROM projects WHERE id = ?",
            [$id]
        );
        if (!$project) Response::error(Translator::t('projects.not_found'), 404);
        if ((int) $project['user_id'] !== $userId) {
            Response::error(Translator::t('projects.not_owner'), 403);
        }

        // Últimos 20 audits para timeline
        $audits = $db->query(
            "SELECT id, url, domain, global_score, global_level, is_wordpress, scan_duration_ms, created_at
             FROM audits WHERE project_id = ?
             ORDER BY created_at DESC LIMIT 20",
            [$id]
        );

        $checklist = $db->queryOne(
            "SELECT
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_count,
                SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) AS ignored_count
             FROM project_checklist_items WHERE project_id = ?",
            [$id]
        );

        Response::success([
            'project' => [
                'id' => (int) $project['id'],
                'name' => $project['name'],
                'url' => $project['url'],
                'domain' => $project['domain'],
                'notes' => $project['notes'],
                'icon' => $project['icon'],
                'color' => $project['color'],
                'createdAt' => $project['created_at'],
                'sharing' => [
                    'enabled' => !empty($project['share_token']),
                    'token' => $project['share_token'] ?: null,
                ],
            ],
            'audits' => array_map(fn($a) => [
                'id' => $a['id'],
                'url' => $a['url'],
                'domain' => $a['domain'],
                'globalScore' => (int) $a['global_score'],
                'globalLevel' => $a['global_level'],
                'isWordPress' => (int) $a['is_wordpress'] === 1,
                'scanDurationMs' => (int) $a['scan_duration_ms'],
                'createdAt' => $a['created_at'],
            ], $audits),
            'checklistSummary' => [
                'open' => (int) ($checklist['open_count'] ?? 0),
                'done' => (int) ($checklist['done_count'] ?? 0),
                'ignored' => (int) ($checklist['ignored_count'] ?? 0),
            ],
            'evolution' => Project::lastAuditsDiff($db, $id),
        ]);
    } catch (Throwable $e) {
        Logger::error('user/projects GET detail falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.fetch_error'), 500);
    }
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $name = trim((string) ($body['name'] ?? ''));
    $urlRaw = trim((string) ($body['url'] ?? ''));
    $notes = trim((string) ($body['notes'] ?? ''));
    $icon = trim((string) ($body['icon'] ?? ''));
    $color = trim((string) ($body['color'] ?? ''));

    if ($name === '') Response::error(Translator::t('projects.name_required'), 400);
    if ($urlRaw === '') Response::error(Translator::t('projects.url_required'), 400);

    try {
        $validUrl = UrlValidator::validate($urlRaw);
    } catch (InvalidArgumentException $e) {
        Response::error(Translator::t('projects.url_invalid'), 400);
    }

    $normalizedUrl = Project::normalizeUrl($validUrl);
    $domain = Project::domainFromUrl($validUrl);
    if ($domain === '') Response::error(Translator::t('projects.url_invalid'), 400);

    // Enforcement de max_projects — solo se chequea al crear
    $plan = $user['plan'] ?? null;
    if (!$plan) {
        Response::error(Translator::t('projects.no_plan'), 403);
    }
    $maxProjects = (int) ($plan['maxProjects'] ?? 0);
    $currentCount = Project::countForUser($db, $userId);
    if ($maxProjects > 0 && $currentCount >= $maxProjects) {
        Response::error(Translator::t('projects.quota_projects', [
            'used' => $currentCount,
            'limit' => $maxProjects,
        ]), 429);
    }

    // Evitar duplicado por URL exacta
    $dup = $db->queryOne(
        "SELECT id FROM projects WHERE user_id = ? AND LOWER(url) = ?",
        [$userId, $normalizedUrl]
    );
    if ($dup) {
        Response::error(Translator::t('projects.url_duplicate'), 409);
    }

    try {
        $db->execute(
            "INSERT INTO projects (user_id, name, url, domain, notes, icon, color) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId, $name, $normalizedUrl, $domain,
                $notes !== '' ? $notes : null,
                $icon !== '' ? $icon : null,
                $color !== '' ? $color : null,
            ]
        );
        $newId = (int) $db->lastInsertId();

        // Retroactivo: enganchar audits existentes del user con esta URL.
        // Audits viejos pueden tener URL con case/trailing-slash distinto al
        // normalizado — por eso comparamos ambos lados con normalizeUrl en
        // PHP. Filtramos primero por domain para no traer de más.
        $candidates = $db->query(
            "SELECT id, url FROM audits
             WHERE user_id = ? AND project_id IS NULL AND LOWER(domain) = ?",
            [$userId, $domain]
        );
        $matchIds = [];
        foreach ($candidates as $c) {
            if (Project::normalizeUrl((string) $c['url']) === $normalizedUrl) {
                $matchIds[] = (string) $c['id'];
            }
        }
        if (!empty($matchIds)) {
            $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
            $params = array_merge([$newId], $matchIds);
            $db->execute(
                "UPDATE audits SET project_id = ? WHERE id IN ($placeholders)",
                $params
            );
            // Rearmar el checklist vivo a partir del último audit retroactivo
            // atado — así el user entra al proyecto y ya ve tareas.
            $latest = $db->queryOne(
                "SELECT result_json FROM audits WHERE project_id = ? ORDER BY created_at DESC LIMIT 1",
                [$newId]
            );
            if ($latest) {
                $decoded = JsonStore::decode($latest['result_json']);
                if (is_array($decoded)) {
                    try {
                        Project::reconcileChecklist($db, $newId, Project::flattenMetrics($decoded));
                    } catch (Throwable $e) {
                        Logger::warning('reconcileChecklist retroactivo falló: ' . $e->getMessage());
                    }
                }
            }
        }

        Response::success(['id' => $newId], 201);
    } catch (Throwable $e) {
        Logger::error('user/projects POST falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.create_error'), 500);
    }
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $id = (int) ($body['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('api.common.param_required', ['param' => 'id']), 400);

    try {
        $existing = $db->queryOne("SELECT id, user_id FROM projects WHERE id = ?", [$id]);
        if (!$existing) Response::error(Translator::t('projects.not_found'), 404);
        if ((int) $existing['user_id'] !== $userId) {
            Response::error(Translator::t('projects.not_owner'), 403);
        }

        $fields = [];
        $params = [];
        if (array_key_exists('name', $body)) {
            $n = trim((string) $body['name']);
            if ($n === '') Response::error(Translator::t('projects.name_required'), 400);
            $fields[] = 'name = ?'; $params[] = $n;
        }
        if (array_key_exists('notes', $body)) {
            $n = trim((string) $body['notes']);
            $fields[] = 'notes = ?'; $params[] = $n !== '' ? $n : null;
        }
        if (array_key_exists('icon', $body)) {
            $n = trim((string) $body['icon']);
            $fields[] = 'icon = ?'; $params[] = $n !== '' ? $n : null;
        }
        if (array_key_exists('color', $body)) {
            $n = trim((string) $body['color']);
            $fields[] = 'color = ?'; $params[] = $n !== '' ? $n : null;
        }
        if (empty($fields)) Response::success(['ok' => true]);

        $params[] = $id;
        $db->execute("UPDATE projects SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('user/projects PUT falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.update_error'), 500);
    }
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('api.common.param_required', ['param' => 'id']), 400);

    try {
        $existing = $db->queryOne("SELECT id, user_id FROM projects WHERE id = ?", [$id]);
        if (!$existing) Response::error(Translator::t('projects.not_found'), 404);
        if ((int) $existing['user_id'] !== $userId) {
            Response::error(Translator::t('projects.not_owner'), 403);
        }

        // Desasociar audits explícitamente (SQLite no aplica ON DELETE SET NULL
        // automáticamente si foreign_keys pragma no está on — lo hacemos manual
        // para garantizar que los audits quedan visibles en el historial del user).
        $db->execute("UPDATE audits SET project_id = NULL WHERE project_id = ?", [$id]);
        $db->execute("DELETE FROM projects WHERE id = ?", [$id]);
        // project_checklist_items se limpian por CASCADE sobre projects FK si
        // foreign_keys está on; si no, los hacemos también manualmente por seguridad.
        $db->execute("DELETE FROM project_checklist_items WHERE project_id = ?", [$id]);

        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('user/projects DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);

/**
 * Serializa una fila del GET list (con los campos agregados del LEFT JOIN).
 */
function serializeProjectRow(array $r): array {
    return [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'url' => $r['url'],
        'domain' => $r['domain'],
        'notes' => $r['notes'],
        'icon' => $r['icon'],
        'color' => $r['color'],
        'createdAt' => $r['created_at'],
        'sharingEnabled' => !empty($r['share_token']),
        'auditCount' => (int) ($r['audit_count'] ?? 0),
        'openChecklistCount' => (int) ($r['open_count'] ?? 0),
        'latestAudit' => !empty($r['latest_audit_id']) ? [
            'id' => $r['latest_audit_id'],
            'globalScore' => (int) $r['latest_score'],
            'globalLevel' => $r['latest_level'],
            'createdAt' => $r['latest_at'],
        ] : null,
    ];
}
