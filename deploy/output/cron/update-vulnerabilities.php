<?php
/**
 * ═══════════════════════════════════════════
 * CRON JOB — Configurar en cPanel → Cron Jobs
 * Frecuencia recomendada: cada lunes a las 3:00 AM
 * Comando: php /home/TUUSUARIO/public_html/audit/cron/update-vulnerabilities.php
 * ═══════════════════════════════════════════
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
    Database::getInstance()->initSchema();
    $stats = VulnerabilityUpdater::run();
    $msg = "[" . date('Y-m-d H:i:s') . "] {$stats['new']} nuevas, {$stats['updated']} actualizadas, {$stats['checked']} plugins, {$stats['errors']} errores";
    CronHealth::markRun('update-vulnerabilities', null, "new={$stats['new']} updated={$stats['updated']} checked={$stats['checked']}");
    if (php_sapi_name() === 'cli') { echo $msg . PHP_EOL; }
    else { header('Content-Type: application/json'); echo json_encode(['success' => true, 'stats' => $stats]); }
} catch (Throwable $e) {
    if (php_sapi_name() === 'cli') { echo "Error: " . $e->getMessage() . PHP_EOL; exit(1); }
    else { http_response_code(500); echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
}
