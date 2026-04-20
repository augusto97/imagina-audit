<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * CRON — Compactación semanal de SQLite (VACUUM + ANALYZE)
 * Frecuencia recomendada: domingo 4:00 AM
 * Comando cPanel:
 *   php /home/TUUSUARIO/public_html/audit/cron/vacuum.php
 * ═══════════════════════════════════════════════════════════════
 *
 * VACUUM recupera espacio de filas borradas y reindexa.
 * ANALYZE actualiza estadísticas del query planner.
 *
 * OJO: VACUUM requiere lock exclusivo sobre la BD. Ejecutar en horario
 * de bajo tráfico. Con journal_mode=WAL es rápido (suele <5s para DBs
 * de hasta 500MB).
 */

if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    $expectedToken = getenv('CRON_SECRET_TOKEN') ?: 'cambiar-este-token';
    if ($token !== $expectedToken) {
        http_response_code(403);
        die('Acceso denegado');
    }
}

require_once dirname(__DIR__) . '/config/env.php';
spl_autoload_register(function (string $class) {
    $paths = [dirname(__DIR__) . '/lib/' . $class . '.php', dirname(__DIR__) . '/analyzers/' . $class . '.php'];
    foreach ($paths as $p) { if (file_exists($p)) { require_once $p; return; } }
});

set_time_limit(300);

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    $before = 0;
    $after = 0;

    // Tamaño antes
    $row = $pdo->query("SELECT page_count * page_size AS size FROM pragma_page_count(), pragma_page_size()")->fetch();
    $before = (int) ($row['size'] ?? 0);

    $start = microtime(true);
    $pdo->exec('VACUUM');
    $pdo->exec('ANALYZE');
    $elapsedMs = round((microtime(true) - $start) * 1000);

    // Tamaño después
    $row = $pdo->query("SELECT page_count * page_size AS size FROM pragma_page_count(), pragma_page_size()")->fetch();
    $after = (int) ($row['size'] ?? 0);

    $savedKB = round(($before - $after) / 1024, 1);
    $msg = sprintf(
        '[%s] vacuum: before=%.1fMB after=%.1fMB saved=%.1fKB elapsed=%dms',
        date('Y-m-d H:i:s'),
        $before / 1048576, $after / 1048576, $savedKB, $elapsedMs
    );

    if (php_sapi_name() === 'cli') {
        echo $msg . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'before' => $before, 'after' => $after, 'elapsedMs' => $elapsedMs]);
    }
} catch (Throwable $e) {
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
