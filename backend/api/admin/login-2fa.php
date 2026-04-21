<?php
/**
 * POST /admin/login-2fa.php — Segundo paso del login cuando 2FA está habilitado.
 *
 * Requiere una sesión previa con pending_2fa=true (fijada por login.php
 * tras validar la password correctamente).
 *
 * Body: { code: '123456' }   (acepta TOTP o recovery code)
 *
 * En éxito: completa el login (admin_authenticated=true, regenera id de
 * sesión, devuelve csrfToken). Rate limit: máx 5 códigos por sesión
 * pending; en el 5to fallo invalidamos el pending y obligamos volver al
 * paso 1 (password).
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

// Ensurar session está iniciada (debe estarlo desde login.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Validar estado pending ─────────────────────────────────────────
if (empty($_SESSION['pending_2fa']) || $_SESSION['pending_2fa'] !== true) {
    Response::error('No hay login pendiente de 2FA. Ingresa tu contraseña primero.', 401);
}

$pendingAt = (int) ($_SESSION['pending_2fa_at'] ?? 0);
$maxAgeSec = 180; // 3 minutos para introducir el código
if ($pendingAt === 0 || (time() - $pendingAt) > $maxAgeSec) {
    unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_at'], $_SESSION['pending_2fa_attempts']);
    Response::error('La sesión 2FA expiró. Vuelve a ingresar tu contraseña.', 401);
}

// ─── Rate limit por sesión ──────────────────────────────────────────
$attempts = (int) ($_SESSION['pending_2fa_attempts'] ?? 0);
if ($attempts >= 5) {
    unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_at'], $_SESSION['pending_2fa_attempts']);
    Response::error('Demasiados códigos incorrectos. Vuelve a ingresar tu contraseña.', 401);
}

// ─── Verificar código ───────────────────────────────────────────────
$body = Response::getJsonBody();
$code = trim((string) ($body['code'] ?? ''));

if ($code === '') {
    Response::error('Código requerido', 400);
}

$db = Database::getInstance();
$secret = '';
try {
    $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_2fa_secret'");
    $secret = $row ? (string) $row['value'] : '';
} catch (Throwable $e) { /* ignore */ }

$totpValid = !empty($secret) && Totp::verify($secret, $code);
$recoveryValid = false;

if (!$totpValid) {
    // Intentar recovery code
    try {
        $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_2fa_recovery_codes'");
        if ($row) {
            $hashes = json_decode((string) $row['value'], true);
            if (is_array($hashes)) {
                $targetHash = Totp::hashRecoveryCode($code);
                $idx = array_search($targetHash, $hashes, true);
                if ($idx !== false) {
                    // Consumir el recovery code
                    array_splice($hashes, (int) $idx, 1);
                    $db->execute(
                        "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                        ['admin_2fa_recovery_codes', json_encode($hashes)]
                    );
                    $recoveryValid = true;
                    Logger::info('Admin logged in con recovery code (quedan ' . count($hashes) . ')');
                }
            }
        }
    } catch (Throwable $e) { /* ignore */ }
}

if (!$totpValid && !$recoveryValid) {
    $_SESSION['pending_2fa_attempts'] = $attempts + 1;
    Response::error('Código inválido. Revisa la hora de tu dispositivo o usa un recovery code.', 401);
}

// ─── Éxito — completar login ────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_login_time'] = time();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_at'], $_SESSION['pending_2fa_attempts']);

// Limpiar login_attempts para esta IP (password + 2FA correctos)
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->execute("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]);
} catch (Throwable $e) { /* ignore */ }

Response::success([
    'authenticated'  => true,
    'csrfToken'      => $_SESSION['csrf_token'],
    'usedRecovery'   => $recoveryValid,
]);
