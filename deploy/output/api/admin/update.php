<?php
/**
 * /api/admin/update.php — Actualizaciones desde GitHub Releases (solo admin).
 *
 *   GET  ?action=status            → versión instalada, último release (consulta
 *                                     GitHub si pasó más de 1 h), estado, historial
 *   POST {action:'check'}          → fuerza la consulta a GitHub
 *   POST {action:'exposure'}       → repite la comprobación de carpetas expuestas
 *   POST {action:'begin'}          → inicia la instalación del último release
 *   POST {action:'step'}           → ejecuta el siguiente paso
 *   POST {action:'cancel'}         → cancela (solo antes del cambio de versión)
 *   POST {action:'rollback'}       → vuelve a la versión anterior guardada
 *   POST {action:'settings', repo, autoInstall}
 *
 * El paso de migraciones lo ejecuta api/update-migrate.php (código nuevo).
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();
// Pasos que tardan (descarga, backup, health-check): no bloquear el admin.
session_write_close();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$um = UpdateManager::forApp();

$exposure = function (bool $force) use ($um) {
    try {
        return ExposureCheck::run(dirname(__DIR__, 2), QueueManager::appBaseUrl(), $um->workDir() . '/exposure.json', $force);
    } catch (Throwable $e) {
        return null;
    }
};

$withStatus = function (array $extra = []) use ($um) {
    $out = $um->status();
    $db = Database::getInstance();
    $out['settings'] = [
        'repo' => UpdateManager::configuredRepo(),
        'autoInstall' => UpdateManager::autoInstallEnabled(),
        'appUrlConfigured' => trim((string) $db->scalar("SELECT value FROM settings WHERE `key` = 'app_url'")) !== '',
    ];
    return $out + $extra;
};

try {
    if ($method === 'GET') {
        // Consulta perezosa: como mucho una vez por hora al abrir el admin.
        $um->checkLatest(false);
        // ?light=1 (aviso de la cabecera del admin): sin comprobación de carpetas.
        $extra = empty($_GET['light']) ? ['exposure' => $exposure(false)] : [];
        Response::success($withStatus($extra));
    }
    if ($method !== 'POST') {
        Response::error(Translator::t('api.common.method_not_allowed'), 405);
    }

    $body = Response::getJsonBody();
    switch ((string) ($body['action'] ?? '')) {
        case 'check':
            $um->checkLatest(true);
            Response::success($withStatus());
            break;

        case 'exposure':
            Response::success($withStatus(['exposure' => $exposure(true)]));
            break;

        case 'begin':
            $state = $um->begin('manual');
            Response::success(['state' => $um->publicState($state)]);
            break;

        case 'step':
            $state = $um->step();
            Response::success(['state' => $um->publicState($state)]);
            break;

        case 'cancel':
            $state = $um->cancel();
            Response::success(['state' => $um->publicState($state)]);
            break;

        case 'rollback':
            $state = $um->rollbackToPrevious();
            Response::success(['state' => $um->publicState($state)]);
            break;

        case 'settings':
            $db = Database::getInstance();
            if (array_key_exists('repo', $body)) {
                $repo = trim((string) $body['repo']);
                if ($repo !== '' && !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
                    Response::error(Translator::t('admin_api.update.invalid_repo'));
                }
                $db->setting('update_repo', $repo);
                @unlink($um->workDir() . '/latest.json'); // otro repo → otra consulta
            }
            if (array_key_exists('autoInstall', $body)) {
                $db->setting('update_auto_install', !empty($body['autoInstall']) ? '1' : '0');
            }
            Response::success($withStatus());
            break;

        default:
            Response::error(Translator::t('admin_api.update.invalid_action'), 400);
    }
} catch (UpdateBusyException $e) {
    Response::error($e->getMessage(), 409);
} catch (Throwable $e) {
    Logger::error('Actualizador: ' . $e->getMessage());
    Response::error($e->getMessage(), 400);
}
