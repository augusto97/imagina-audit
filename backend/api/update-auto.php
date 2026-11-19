<?php
/**
 * GET /api/update-auto.php?token=CRON_TOKEN
 *
 * Instalación automática de la última versión. La dispara
 * UpdateManager::cronTick() (desde el cron de la cola) solo si el admin
 * activó "Instalar actualizaciones automáticamente" y hay una versión
 * nueva que permite auto-instalación. Corre en PHP-FPM (no en CLI) para
 * que el cambio de versión y el reinicio de OPcache afecten al servidor
 * web. Se intenta una sola vez por versión.
 */
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$token = (string) ($_GET['token'] ?? '');
$expected = '';
try { $expected = QueueManager::ensureCronToken(); } catch (Throwable $e) {}
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}
if (!UpdateManager::autoInstallEnabled()) {
    echo json_encode(['success' => false, 'error' => 'Instalación automática desactivada']);
    exit;
}

@set_time_limit(900);
@ignore_user_abort(true);
// El cron cierra la conexión enseguida: responder ya y seguir trabajando.
if (function_exists('fastcgi_finish_request')) {
    echo json_encode(['success' => true, 'started' => true]);
    fastcgi_finish_request();
}

$base = QueueManager::appBaseUrl();
$http = function (string $url, int $timeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
};

try {
    $um = UpdateManager::forApp();
    $latest = $um->checkLatest(true);
    $rel = $latest['release'] ?? null;
    if (!$rel || !AppVersion::isNewer($rel['version'], AppVersion::current()) || $um->isBusy() || $um->autoAttempted($rel['version'])
        || (($rel['manifest']['autoInstall'] ?? true) === false)) {
        if (!function_exists('fastcgi_finish_request')) echo json_encode(['success' => true, 'skipped' => true]);
        exit;
    }
    $state = $um->runAutomatic(function (string $opsToken) use ($http, $base) {
        $http("$base/api/update-migrate.php?token=" . urlencode($opsToken), 600);
    });
    // La versión nueva finaliza sola en su primera petición.
    if (($state['status'] ?? '') === 'switched') $http("$base/api/health.php", 30);
    Logger::info('Actualización automática: ' . ($state['status'] ?? '?') . ' ' . ($state['fromVersion'] ?? '') . ' → ' . ($state['toVersion'] ?? '') . (empty($state['error']) ? '' : ' — ' . $state['error']));
    if (!function_exists('fastcgi_finish_request')) echo json_encode(['success' => true, 'status' => $state['status'] ?? null]);
} catch (Throwable $e) {
    Logger::error('Actualización automática falló: ' . $e->getMessage());
    if (!function_exists('fastcgi_finish_request')) echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
