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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxAttempts = 5;
$windowMinutes = 15;

try {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address TEXT NOT NULL, attempted_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $db->execute("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-' || ? || ' minutes')", [$windowMinutes]);
    $attempts = (int) $db->scalar("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?", [$ip]);
    if ($attempts >= $maxAttempts) {
        Response::error("Demasiados intentos. Espera $windowMinutes minutos.", 429);
    }
} catch (Throwable $e) {
    Logger::error('Login rate limit check falló: ' . $e->getMessage());
}

if (Auth::login($password)) {
    try { $db->execute("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]); } catch (Throwable $e) {}
    Response::success(['authenticated' => true]);
} else {
    try { $db->execute("INSERT INTO login_attempts (ip_address) VALUES (?)", [$ip]); } catch (Throwable $e) {}
    Response::error('Contraseña incorrecta.', 401);
}
