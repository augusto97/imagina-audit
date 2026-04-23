<?php
/**
 * GET /api/frontend-locales?lang=xx — Endpoint PÚBLICO que sirve el bundle
 * JSON del frontend para un idioma concreto. Es lo que el frontend carga al
 * cambiar de idioma (o al arrancar con un idioma no-default).
 *
 * Cascada de resolución:
 *   1. backend/locales/en/frontend.json (base)
 *   2. backend/locales/{lang}/frontend.json (si existe, sobreescribe)
 *   3. overrides de la tabla `translations` (namespace='frontend', lang=xx)
 *
 * De esta manera, un idioma nuevo creado por el admin desde el panel
 * — aunque no tenga bundle base — hereda todas las keys del default y
 * puede ser traducido con el editor/IA sin rebuild.
 *
 * Si el idioma no está activo (o no existe), retorna 404 para que el
 * frontend caiga en el default.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$code = strtolower(substr(trim($_GET['lang'] ?? ''), 0, 2));
if ($code === '' || !preg_match('/^[a-z]{2}$/', $code)) {
    Response::error(Translator::t('admin_api.translations.lang_unsupported'), 400);
}

$lang = Languages::find($code);
if (!$lang || !$lang['isActive']) {
    Response::error(Translator::t('admin_api.translations.lang_unsupported'), 404);
}

$bundle = Languages::frontendBundle($code);

// Cache-control: las traducciones pueden cambiar; dejamos un TTL bajo con
// revalidation para que los navegadores pidan actualización sin bombardear
// al server en cada request.
header('Cache-Control: public, max-age=60, must-revalidate');

Response::success([
    'lang' => $code,
    'bundle' => $bundle,
]);
