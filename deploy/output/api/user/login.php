<?php
/**
 * POST /api/user/login — login del usuario (cuenta creada por el admin).
 *
 * Body: { email, password }
 * Responde: { authenticated, csrfToken, user: { id, email, name, plan } }.
 *
 * Backoff progresivo por IP (misma estrategia que admin/login pero sobre
 * user_login_attempts). Tras 15 intentos fallidos en 15 min se bloquea la
 * IP durante ese mismo window.
 */

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$body = Response::getJsonBody();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

// Honeypot — los bots llenan campos invisibles para el humano
$honeypot = ($body['website'] ?? '') . ($body['phone_number'] ?? '');
if ($honeypot !== '') {
    usleep(random_int(500_000, 1_500_000));
    Response::error(Translator::t('user_api.login.invalid_credentials'), 401);
}

if ($email === '' || $password === '') {
    Response::error(Translator::t('user_api.login.credentials_required'), 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$windowMinutes = 15;
$backoffSchedule = [0, 0, 2, 5, 15, 60, 300];

$db = Database::getInstance();
try {
    $db->execute("DELETE FROM user_login_attempts WHERE attempted_at < datetime('now', '-' || ? || ' minutes')", [$windowMinutes]);
    $attempts = (int) $db->scalar("SELECT COUNT(*) FROM user_login_attempts WHERE ip_address = ?", [$ip]);
    $lastIso = $db->scalar("SELECT MAX(attempted_at) FROM user_login_attempts WHERE ip_address = ?", [$ip]);

    $requiredDelay = $backoffSchedule[min($attempts, count($backoffSchedule) - 1)] ?? 300;
    if ($requiredDelay > 0 && $lastIso) {
        $secSinceLast = time() - strtotime((string) $lastIso);
        if ($secSinceLast < $requiredDelay) {
            $waitSec = $requiredDelay - $secSinceLast;
            header("Retry-After: $waitSec");
            Response::error(Translator::t('user_api.login.backoff', ['seconds' => $waitSec]), 429);
        }
    }
    if ($attempts >= 15) {
        header('Retry-After: ' . ($windowMinutes * 60));
        Response::error(Translator::t('user_api.login.ip_blocked', ['minutes' => $windowMinutes]), 429);
    }
} catch (Throwable $e) {
    Logger::error('user/login rate limit check falló: ' . $e->getMessage());
}

// Verificar cuenta activa ANTES de la password — para distinguir entre
// credenciales malas y cuenta deshabilitada.
try {
    $row = $db->queryOne("SELECT id, is_active FROM users WHERE email = ?", [$email]);
    if ($row && (int) $row['is_active'] !== 1) {
        // Misma latencia que un fallo de password real para no leakear info
        usleep(random_int(200_000, 600_000));
        Response::error(Translator::t('user_api.login.account_disabled'), 403);
    }
} catch (Throwable $e) { /* continuar al login normal */ }

$user = UserAuth::login($email, $password);
if (!$user) {
    try {
        $db->execute("INSERT INTO user_login_attempts (ip_address, email) VALUES (?, ?)", [$ip, $email]);
    } catch (Throwable $e) { /* no crítico */ }
    Response::error(Translator::t('user_api.login.invalid_credentials'), 401);
}

// Login OK — limpiar intentos de esa IP
try {
    $db->execute("DELETE FROM user_login_attempts WHERE ip_address = ?", [$ip]);
} catch (Throwable $e) { /* no crítico */ }

// Devolver user + plan completo
$current = UserAuth::currentUser();
Response::success([
    'authenticated' => true,
    'csrfToken' => UserAuth::getCsrfToken(),
    'user' => $current,
    'quota' => $current && $current['plan']
        ? UserAuth::quota($current['id'], (int) $current['plan']['monthlyLimit'])
        : null,
]);
