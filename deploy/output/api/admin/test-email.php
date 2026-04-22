<?php
/**
 * POST /api/admin/test-email — Envía email de prueba SMTP
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$body = Response::getJsonBody();
$to = trim($body['to'] ?? '');

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    Response::error(Translator::t('admin_auth.test_email.invalid_address'));
}

$success = Mailer::sendTest($to);

if ($success) {
    Response::success(['message' => Translator::t('admin_auth.test_email.sent_ok')]);
} else {
    Response::error(Translator::t('admin_auth.test_email.send_failed'), 422);
}
