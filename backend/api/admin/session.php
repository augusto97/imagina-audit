<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Este endpoint NO requiere auth — retorna true/false + csrf token si está autenticado
$authenticated = Auth::checkAuth();
Response::success([
    'authenticated' => $authenticated,
    'csrfToken' => $authenticated ? Auth::getCsrfToken() : null,
]);
