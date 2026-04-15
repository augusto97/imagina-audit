<?php
/**
 * POST /api/admin/test-email — Envía email de prueba SMTP
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$to = trim($body['to'] ?? '');

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    Response::error('Email de destino inválido.');
}

$success = Mailer::sendTest($to);

if ($success) {
    Response::success(['message' => 'Email de prueba enviado correctamente.']);
} else {
    Response::error('No se pudo enviar el email. Verifica la configuración SMTP.', 422);
}
