<?php
/**
 * Carga variables de entorno desde archivo .env
 * Compatible con hosting compartido (sin vlucas/dotenv)
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        // Ignorar comentarios
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Separar key=value
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Remover comillas
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        // Solo establecer si no existe ya en el entorno
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Obtiene una variable de entorno con valor por defecto.
 *
 * El segundo argumento es `mixed` a propósito — acepta string, null,
 * int, bool, etc. — y se convierte a string solo si efectivamente se usa
 * como fallback. Esto evita TypeErrors si algún caller pasa null.
 */
function env(string $key, mixed $default = ''): string {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return (string) ($default ?? '');
    }
    return (string) $val;
}

// Cargar .env automáticamente
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// Forzar UTC en TODO el backend. La DB ya está en UTC (MySQL via SET time_zone
// en postConnect; SQLite via datetime('now')), pero los helpers de fecha en
// Database.php (nowMinus, startOfMonth, today, reapStaleRunning) usan date()
// que respeta la TZ de php.ini. Sin esta línea, en un hosting con php.ini en
// Europe/Madrid (UTC+2) reapStaleRunning calcula un threshold "2h en el
// futuro" y mata al instante todo job 'running'; las ventanas de rate-limit
// y cuota mensual también quedan corridas. UTC en todos lados, sin
// excepciones — la UI ya formatea con i18n.language en el cliente.
date_default_timezone_set('UTC');
