<?php
/**
 * GET /api/admin/plugin-vault.php
 *   Devuelve estado de los plugins gestionados (versión, última verificación,
 *   tamaño, URL pública de descarga).
 *
 * POST /api/admin/plugin-vault.php
 *   Body: { slug: 'wp-snapshot', force?: true }
 *   Refresca el plugin contra GitHub (si force=true descarga aunque sea
 *   la misma versión).
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $catalog = PluginVault::catalog();
    $items = [];
    foreach ($catalog as $slug => $_) {
        $items[] = PluginVault::status($slug);
    }
    Response::success(['plugins' => $items]);
}

if ($method === 'POST') {
    // Refresco bajo demanda. La descarga real puede tardar varios segundos
    // (clone del ZIP de GitHub + recompresión), así que damos margen.
    set_time_limit(120);

    $body = Response::getJsonBody();
    $slug = (string) ($body['slug'] ?? '');
    $force = (bool) ($body['force'] ?? false);

    if (!isset(PluginVault::catalog()[$slug])) {
        Response::error('Plugin desconocido', 400);
    }

    $status = PluginVault::refresh($slug, $force);
    if ($status === null) {
        Response::error('No se pudo descargar la última versión desde GitHub. Revisa los logs.', 500);
    }
    Response::success(['plugin' => $status]);
}

Response::error('Método no permitido', 405);
