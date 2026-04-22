<?php
/**
 * POST /api/user/logout — cierra la sesión del usuario.
 * No afecta la sesión admin si el mismo browser tiene ambas activas.
 */

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

UserAuth::logout();
Response::success(['ok' => true]);
