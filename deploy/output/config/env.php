<?php
/**
 * Carga variables de entorno desde archivo .env
 * Compatible con hosting compartido (sin vlucas/dotenv)
 */

require_once dirname(__DIR__) . '/lib/EnvFile.php';

/**
 * Carga el .env con el parser dotenv de EnvFile (nunca como script de
 * shell). Las variables ya presentes en el entorno real tienen prioridad.
 */
function loadEnv(string $path): void {
    foreach (EnvFile::parseFile($path) as $key => $value) {
        // Solo establecer si no existe ya en el entorno
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            // putenv no admite saltos de línea de forma fiable; $_ENV basta.
            if (!str_contains($value, "\n")) @putenv("$key=$value");
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
