<?php
/**
 * GET /api/languages — Endpoint PÚBLICO que lista los idiomas disponibles
 * para mostrar en el LanguageSwitcher del frontend. Solo retorna los que
 * están activos Y son públicos.
 *
 * Sin autenticación. El frontend lo llama al arrancar para saber qué
 * idiomas ofrecer y también para resolver los nombres nativos que muestra
 * en el dropdown.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$list = Languages::all(true, true);
// El frontend solo necesita lo mínimo — no exponemos flags internos.
$payload = array_map(fn($l) => [
    'code' => $l['code'],
    'name' => $l['name'],
    'nativeName' => $l['nativeName'],
], $list);

Response::success([
    'languages' => $payload,
    'default' => Translator::DEFAULT_LANG,
]);
