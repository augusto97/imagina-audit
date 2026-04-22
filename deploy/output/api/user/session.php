<?php
/**
 * GET /api/user/session — estado de sesión del user actual.
 *
 * Respuesta:
 *   - Si hay sesión: { authenticated: true, user, quota, csrfToken }
 *   - Si no: { authenticated: false }
 *
 * El frontend llama a este endpoint al montar para decidir si mostrar
 * el formulario de audit público o el dashboard del user logged-in.
 */

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

if (!UserAuth::checkAuth()) {
    Response::success(['authenticated' => false]);
}

$user = UserAuth::currentUser();
if (!$user) {
    // checkAuth pasó pero currentUser devolvió null — la cuenta fue
    // deshabilitada entre requests. UserAuth ya cerró la sesión.
    Response::success(['authenticated' => false]);
}

$quota = $user['plan']
    ? UserAuth::quota($user['id'], (int) $user['plan']['monthlyLimit'])
    : null;

Response::success([
    'authenticated' => true,
    'user' => $user,
    'quota' => $quota,
    'csrfToken' => UserAuth::getCsrfToken(),
]);
