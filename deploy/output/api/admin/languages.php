<?php
/**
 * CRUD de idiomas (admin-only). Permite al admin gestionar qué idiomas
 * están activos y cuáles aparecen en el switcher público.
 *
 *   GET    /admin/languages.php             → lista completa (activos + inactivos)
 *   POST   /admin/languages.php             → crea idioma nuevo
 *   PUT    /admin/languages.php             → actualiza metadata / flags
 *   DELETE /admin/languages.php?code=xx     → elimina idioma + overrides
 *
 * El código ISO 639-1 es obligatorio (2 letras, ej. 'pt', 'fr', 'de').
 * Crear un idioma solo lo registra en la tabla — los strings se traducen
 * luego desde el editor de traducciones o con el pipeline IA.
 *
 * El idioma default ('en') no se puede eliminar ni desactivar.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $list = Languages::all(false, false);
        Response::success(['languages' => $list, 'default' => Translator::DEFAULT_LANG]);
    } catch (Throwable $e) {
        Logger::error('admin/languages GET falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.languages.fetch_error'), 500);
    }
}

if ($method === 'POST' || $method === 'PUT') {
    $body = Response::getJsonBody();
    $code = strtolower(trim((string) ($body['code'] ?? '')));
    if ($code === '' || !preg_match('/^[a-z]{2}$/', $code)) {
        Response::error(Translator::t('admin_api.languages.code_invalid'), 400);
    }

    // En PUT exigimos que exista; en POST lo evitamos duplicar.
    $existing = Languages::find($code);
    if ($method === 'POST' && $existing) {
        Response::error(Translator::t('admin_api.languages.already_exists'), 409);
    }
    if ($method === 'PUT' && !$existing) {
        Response::error(Translator::t('admin_api.languages.not_found'), 404);
    }

    // Proteger flags críticos del idioma default.
    if ($code === Translator::DEFAULT_LANG) {
        $body['isActive'] = true;
        $body['isPublic'] = true;
    }

    try {
        $saved = Languages::upsert([
            'code' => $code,
            'name' => $body['name'] ?? null,
            'nativeName' => $body['nativeName'] ?? null,
            'isActive' => array_key_exists('isActive', $body) ? (bool) $body['isActive'] : true,
            'isPublic' => array_key_exists('isPublic', $body) ? (bool) $body['isPublic'] : true,
            'sortOrder' => $body['sortOrder'] ?? ($existing['sortOrder'] ?? 100),
        ]);
        Response::success(['language' => $saved]);
    } catch (Throwable $e) {
        Logger::error("admin/languages $method falló: " . $e->getMessage());
        Response::error(Translator::t('admin_api.languages.save_error'), 500);
    }
}

if ($method === 'DELETE') {
    $code = strtolower(trim((string) ($_GET['code'] ?? '')));
    if ($code === '' || !preg_match('/^[a-z]{2}$/', $code)) {
        Response::error(Translator::t('admin_api.languages.code_invalid'), 400);
    }
    if ($code === Translator::DEFAULT_LANG) {
        Response::error(Translator::t('admin_api.languages.cannot_delete_default'), 400);
    }
    if (!Languages::find($code)) {
        Response::error(Translator::t('admin_api.languages.not_found'), 404);
    }
    try {
        Languages::delete($code);
        Response::success(['success' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/languages DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.languages.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
