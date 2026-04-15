<?php
/**
 * GET/POST/PUT/DELETE /api/admin/vulnerabilities
 */
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    try {
        $total = (int) $db->scalar("SELECT COUNT(*) FROM vulnerabilities");
        $rows = $db->query("SELECT * FROM vulnerabilities ORDER BY created_at DESC LIMIT ? OFFSET ?", [$limit, $offset]);
        Response::success(['vulnerabilities' => $rows, 'total' => $total]);
    } catch (Throwable $e) {
        Response::error('Error al obtener vulnerabilidades.', 500);
    }
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    try {
        $db->execute(
            "INSERT INTO vulnerabilities (plugin_slug, plugin_name, affected_versions, severity, cve_id, description, fixed_in_version) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $body['pluginSlug'] ?? '',
                $body['pluginName'] ?? '',
                $body['affectedVersions'] ?? '',
                $body['severity'] ?? 'medium',
                $body['cveId'] ?? '',
                $body['description'] ?? '',
                $body['fixedInVersion'] ?? '',
            ]
        );
        Response::success(['id' => (int) $db->lastInsertId()], 201);
    } catch (Throwable $e) {
        Response::error('Error al crear vulnerabilidad.', 500);
    }
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $id = $body['id'] ?? 0;
    if (!$id) Response::error('El id es obligatorio.');

    try {
        $db->execute(
            "UPDATE vulnerabilities SET plugin_slug=?, plugin_name=?, affected_versions=?, severity=?, cve_id=?, description=?, fixed_in_version=? WHERE id=?",
            [
                $body['pluginSlug'] ?? '',
                $body['pluginName'] ?? '',
                $body['affectedVersions'] ?? '',
                $body['severity'] ?? 'medium',
                $body['cveId'] ?? '',
                $body['description'] ?? '',
                $body['fixedInVersion'] ?? '',
                $id,
            ]
        );
        Response::success();
    } catch (Throwable $e) {
        Response::error('Error al actualizar vulnerabilidad.', 500);
    }
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    if (!$id) Response::error('El parámetro id es obligatorio.');

    try {
        $db->execute("DELETE FROM vulnerabilities WHERE id = ?", [(int) $id]);
        Response::success();
    } catch (Throwable $e) {
        Response::error('Error al eliminar vulnerabilidad.', 500);
    }
}

Response::error('Método no permitido', 405);
