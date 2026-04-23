<?php
/**
 * Checklist vivo de un proyecto. Se alimenta solo desde reconcileChecklist()
 * en audit.php / QueueManager — este endpoint solo permite que el user
 * interactúe con los items (marcar done manual, ignorar, agregar nota).
 *
 *   GET    /user/project-checklist.php?project_id=N
 *          Lista de items. Devuelve un metric_map con name/description
 *          del último audit para que la UI muestre texto legible en vez
 *          del metric_id raw.
 *   PUT    /user/project-checklist.php
 *          Body: { projectId, metricId, status?, note? }
 *          Actualiza el item. Al cambiar status a done/ignored/open a mano,
 *          user_modified=1 (clave para la regla de re-apertura automática).
 */

require_once __DIR__ . '/../bootstrap.php';
UserAuth::requireAuth();

$user = UserAuth::currentUser();
$userId = (int) $user['id'];
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Verifica que el proyecto exista y le pertenezca al user. Termina el
 * request con 404/403 si no corresponde.
 */
function requireOwnedProject(Database $db, int $userId, int $projectId): array {
    $p = $db->queryOne("SELECT id, user_id FROM projects WHERE id = ?", [$projectId]);
    if (!$p) Response::error(Translator::t('projects.not_found'), 404);
    if ((int) $p['user_id'] !== $userId) Response::error(Translator::t('projects.not_owner'), 403);
    return $p;
}

if ($method === 'GET') {
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId === 0) Response::error(Translator::t('api.common.param_required', ['param' => 'project_id']), 400);

    requireOwnedProject($db, $userId, $projectId);

    try {
        $items = $db->query(
            "SELECT metric_id, status, severity, note, user_modified, completed_at, updated_at
             FROM project_checklist_items WHERE project_id = ?
             ORDER BY
                CASE status WHEN 'open' THEN 0 WHEN 'ignored' THEN 1 ELSE 2 END,
                CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END,
                updated_at DESC",
            [$projectId]
        );

        // Para cada metric_id, intentamos sacar name + description + module
        // del último audit del proyecto. El user ve texto humano en vez del
        // metric_id técnico. Mapa lazy: una sola lectura del result_json.
        $latestAudit = $db->queryOne(
            "SELECT result_json FROM audits WHERE project_id = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1",
            [$projectId]
        );
        $metricMap = [];
        if ($latestAudit) {
            $result = JsonStore::decode($latestAudit['result_json']) ?: [];
            foreach ($result['modules'] ?? [] as $mod) {
                foreach ($mod['metrics'] ?? [] as $m) {
                    $mid = (string) ($m['id'] ?? '');
                    if ($mid === '') continue;
                    $metricMap[$mid] = [
                        'name' => $m['name'] ?? $mid,
                        'description' => $m['description'] ?? '',
                        'recommendation' => $m['recommendation'] ?? '',
                        'imaginaSolution' => $m['imaginaSolution'] ?? '',
                        'moduleId' => $mod['id'] ?? '',
                        'moduleName' => $mod['name'] ?? '',
                    ];
                }
            }
        }

        $out = array_map(function ($r) use ($metricMap) {
            $meta = $metricMap[$r['metric_id']] ?? [
                'name' => $r['metric_id'],
                'description' => '',
                'recommendation' => '',
                'imaginaSolution' => '',
                'moduleId' => '',
                'moduleName' => '',
            ];
            return [
                'metricId' => $r['metric_id'],
                'name' => $meta['name'],
                'description' => $meta['description'],
                'recommendation' => $meta['recommendation'],
                'imaginaSolution' => $meta['imaginaSolution'],
                'moduleId' => $meta['moduleId'],
                'moduleName' => $meta['moduleName'],
                'status' => $r['status'],
                'severity' => $r['severity'],
                'note' => $r['note'],
                'userModified' => (int) $r['user_modified'] === 1,
                'completedAt' => $r['completed_at'],
                'updatedAt' => $r['updated_at'],
            ];
        }, $items);

        Response::success(['items' => $out, 'total' => count($out)]);
    } catch (Throwable $e) {
        Logger::error('user/project-checklist GET falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.checklist.fetch_error'), 500);
    }
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $projectId = (int) ($body['projectId'] ?? 0);
    $metricId = trim((string) ($body['metricId'] ?? ''));
    $status = trim((string) ($body['status'] ?? ''));
    $note = array_key_exists('note', $body) ? trim((string) $body['note']) : null;

    if ($projectId === 0 || $metricId === '') {
        Response::error(Translator::t('api.common.param_required', ['param' => 'projectId / metricId']), 400);
    }
    requireOwnedProject($db, $userId, $projectId);

    if ($status !== '' && !in_array($status, ['open', 'done', 'ignored'], true)) {
        Response::error(Translator::t('projects.checklist.status_invalid'), 400);
    }

    try {
        $existing = $db->queryOne(
            "SELECT id FROM project_checklist_items WHERE project_id = ? AND metric_id = ?",
            [$projectId, $metricId]
        );

        if (!$existing) {
            // El user está cerrando una métrica que aún no está en el checklist
            // (ej. quiere marcar ignored una métrica good antes de que regrese).
            // Lo insertamos con user_modified=1.
            $db->execute(
                "INSERT INTO project_checklist_items (project_id, metric_id, status, note, user_modified, completed_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, datetime('now'))",
                [
                    $projectId,
                    $metricId,
                    $status !== '' ? $status : 'open',
                    $note,
                    $status === 'done' ? date('c') : null,
                ]
            );
        } else {
            $fields = ['user_modified = 1', 'updated_at = datetime(\'now\')'];
            $params = [];
            if ($status !== '') {
                $fields[] = 'status = ?';
                $params[] = $status;
                $fields[] = 'completed_at = ' . ($status === 'done' ? 'datetime(\'now\')' : 'NULL');
            }
            if ($note !== null) {
                $fields[] = 'note = ?';
                $params[] = $note !== '' ? $note : null;
            }
            $params[] = $existing['id'];
            $db->execute("UPDATE project_checklist_items SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        }

        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('user/project-checklist PUT falló: ' . $e->getMessage());
        Response::error(Translator::t('projects.checklist.fetch_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
