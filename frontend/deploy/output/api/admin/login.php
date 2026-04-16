<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$password = $body['password'] ?? '';

if (empty($password)) {
    Response::error('La contraseña es obligatoria.');
}

if (Auth::login($password)) {
    Response::success(['authenticated' => true]);
} else {
    Response::error('Contraseña incorrecta.', 401);
}
