<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$password = $body['password'] ?? '';

// ─── Honeypot — campo invisible para humanos, bots lo llenan ────────
$honeypot = ($body['website'] ?? '') . ($body['phone_number'] ?? '');
if ($honeypot !== '') {
    usleep(random_int(500_000, 1_500_000));
    Response::error('Contraseña incorrecta.', 401);
}

if (empty($password)) {
    Response::error('La contraseña es obligatoria.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$windowMinutes = 15;

// ─── Backoff progresivo ─────────────────────────────────────────────
$backoffSchedule = [0, 0, 2, 5, 15, 60, 300];

try {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, attempted_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $db->execute("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-' || ? || ' minutes')", [$windowMinutes]);

    $attempts = (int) $db->scalar("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?", [$ip]);
    $lastAttemptIso = $db->scalar("SELECT MAX(attempted_at) FROM login_attempts WHERE ip_address = ?", [$ip]);

    $requiredDelay = $backoffSchedule[min($attempts, count($backoffSchedule) - 1)] ?? 300;

    if ($requiredDelay > 0 && $lastAttemptIso) {
        $secSinceLast = time() - strtotime((string) $lastAttemptIso);
        if ($secSinceLast < $requiredDelay) {
            $waitSec = $requiredDelay - $secSinceLast;
            header("Retry-After: $waitSec");
            Response::error("Demasiados intentos fallidos. Espera {$waitSec}s antes de volver a intentar.", 429);
        }
    }

    if ($attempts >= 15) {
        header('Retry-After: ' . ($windowMinutes * 60));
        Response::error("Demasiados intentos. IP bloqueada por $windowMinutes minutos.", 429);
    }
} catch (Throwable $e) {
    Logger::error('Login rate limit check falló: ' . $e->getMessage());
}

// ─── Verificación de password ────────────────────────────────────────
if (!Auth::login($password)) {
    try { $db->execute("INSERT INTO login_attempts (ip_address) VALUES (?)", [$ip]); } catch (Throwable $e) {}
    Response::error('Contraseña incorrecta.', 401);
}

// Password OK. Revisar si 2FA está habilitado — si lo está, NO
// completamos el login aquí. Dejamos la sesión en "pending 2fa" y
// exigimos un segundo request a login-2fa.php.
$twoFaEnabled = false;
try {
    $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_2fa_enabled'");
    $twoFaEnabled = $row && (string) $row['value'] === '1';
} catch (Throwable $e) { /* sin tabla settings = sin 2FA */ }

if ($twoFaEnabled) {
    // Bajar flag de admin_authenticated (Auth::login() lo había subido)
    // y marcar pending. La sesión queda iniciada pero en estado previo.
    $_SESSION['admin_authenticated'] = false;
    $_SESSION['pending_2fa'] = true;
    $_SESSION['pending_2fa_at'] = time();
    $_SESSION['pending_2fa_attempts'] = 0;

    // No limpiar login_attempts todavía — si el atacante robó la password
    // pero no tiene 2FA, queremos que siga bajo backoff en el paso 1.
    Response::success([
        'authenticated' => false,
        'needs2fa'      => true,
    ]);
}

// Sin 2FA — login completado en un solo paso (comportamiento original)
try { $db->execute("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]); } catch (Throwable $e) {}
Response::success([
    'authenticated' => true,
    'csrfToken'     => Auth::getCsrfToken(),
]);
