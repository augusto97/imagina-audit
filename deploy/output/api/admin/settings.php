<?php
/**
 * GET/PUT /api/admin/settings
 */
Auth::requireAuth();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rows = $db->query("SELECT key, value FROM settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        Response::success($settings);
    } catch (Throwable $e) {
        Response::error('Error al obtener configuración.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = Response::getJsonBody();

    try {
        foreach ($body as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $db->execute(
                "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                [$key, (string) $value]
            );
        }
        Response::success();
    } catch (Throwable $e) {
        Response::error('Error al guardar configuración.', 500);
    }
}

Response::error('Método no permitido', 405);
