<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

$body = Response::getJsonBody();
$password = $body['password'] ?? '';

// ─── Honeypot — campo invisible para humanos, bots lo llenan ────────
// Si el campo 'website' o 'phone_number' viene con algo, es un bot.
// Devolvemos la respuesta de un login fallido genérico (sin lockear la
// IP — no tiene sentido contar bots como intentos).
$honeypot = ($body['website'] ?? '') . ($body['phone_number'] ?? '');
if ($honeypot !== '') {
    // Delay artificial para que los bots no iteren rápido
    usleep(random_int(500_000, 1_500_000));
    Response::error('Contraseña incorrecta.', 401);
}

if (empty($password)) {
    Response::error('La contraseña es obligatoria.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$windowMinutes = 15;

// ─── Rate limit + backoff progresivo ─────────────────────────────────
// Delays por nº de intento fallido en los últimos $windowMinutes:
//   1–2:   sin delay            (typos normales)
//   3:     2s de espera
//   4:     5s
//   5:     15s
//   6:     60s (1 min)
//   7+:    300s (5 min)  → lockout funcional
$backoffSchedule = [0, 0, 2, 5, 15, 60, 300];

try {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, attempted_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $db->execute("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-' || ? || ' minutes')", [$windowMinutes]);

    $attempts = (int) $db->scalar("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?", [$ip]);
    $lastAttemptIso = $db->scalar(
        "SELECT MAX(attempted_at) FROM login_attempts WHERE ip_address = ?",
        [$ip]
    );

    // Calcular delay requerido según el nº de fallos
    $requiredDelay = $backoffSchedule[min($attempts, count($backoffSchedule) - 1)] ?? 300;

    if ($requiredDelay > 0 && $lastAttemptIso) {
        $lastTs = strtotime((string) $lastAttemptIso);
        $secSinceLast = time() - $lastTs;
        if ($secSinceLast < $requiredDelay) {
            $waitSec = $requiredDelay - $secSinceLast;
            header("Retry-After: $waitSec");
            Response::error(
                "Demasiados intentos fallidos. Espera {$waitSec}s antes de volver a intentar.",
                429
            );
        }
    }

    // Hard stop: tras ~15 intentos en la ventana, ni el backoff te salva
    if ($attempts >= 15) {
        header('Retry-After: ' . ($windowMinutes * 60));
        Response::error("Demasiados intentos. IP bloqueada por $windowMinutes minutos.", 429);
    }
} catch (Throwable $e) {
    Logger::error('Login rate limit check falló: ' . $e->getMessage());
}

if (Auth::login($password)) {
    try { $db->execute("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]); } catch (Throwable $e) {}
    Response::success([
        'authenticated' => true,
        'csrfToken' => Auth::getCsrfToken(),
    ]);
} else {
    try { $db->execute("INSERT INTO login_attempts (ip_address) VALUES (?)", [$ip]); } catch (Throwable $e) {}
    Response::error('Contraseña incorrecta.', 401);
}
