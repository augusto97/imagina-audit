<?php
/**
 * GET /api/update-migrate.php?token=…[&action=migrate|opcache]
 *
 * Paso "migrate" del actualizador. Corre las migraciones de la versión
 * NUEVA (ya descargada y verificada en releases/<versión>) contra la base
 * actual, ANTES de cambiar de versión.
 *
 * Por qué es un archivo aparte y NO carga bootstrap.php: bootstrap carga
 * Database/Migrator de la versión instalada; PHP no puede tener dos clases
 * con el mismo nombre en un proceso. Aquí solo se cargan del código
 * instalado env.php + UpdateManager (estado), y Database/Migrator/… salen
 * del paquete nuevo mediante un autoloader propio. Así una versión nueva
 * puede traer placeholders o cambios en el Migrator sin romper la
 * actualización.
 *
 * Autorización: token de un solo uso de la actualización en curso
 * (state.json), válido solo mientras el paso pendiente es "migrate".
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$appRoot = dirname(__DIR__);
require_once $appRoot . '/config/env.php';
require_once $appRoot . '/lib/AppVersion.php';
require_once $appRoot . '/lib/UpdateManager.php';

$reply = function (int $code, array $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$token = (string) ($_GET['token'] ?? '');
$action = (string) ($_GET['action'] ?? 'migrate');

try {
    $um = new UpdateManager($appRoot, UpdateManager::workDirFor($appRoot));
    $state = $um->readState();
    if (!$um->validOpsToken($state, $token)) {
        $reply(403, ['success' => false, 'error' => 'Token inválido o actualización no activa.']);
    }

    if ($action === 'opcache') {
        if (function_exists('opcache_reset')) @opcache_reset();
        $reply(200, ['success' => true]);
    }

    if (($state['step'] ?? '') !== 'migrate') {
        $reply(409, ['success' => false, 'error' => 'La actualización no está en el paso de migraciones.', 'data' => $um->publicState($state)]);
    }

    @set_time_limit(600);
    @ignore_user_abort(true);

    $release = $um->workDir() . '/releases/' . $state['toVersion'];
    if (!is_file("$release/lib/Migrator.php")) {
        $state = $um->afterExternalMigrate($token, [], 'no se encontró el Migrator de la versión nueva');
        $reply(200, ['success' => true, 'data' => $um->publicState($state)]);
    }

    // Clases del paquete NUEVO (Database, Migrator, dialectos, Logger, …).
    spl_autoload_register(function (string $class) use ($release) {
        foreach (['lib', 'lib/db', 'lib/providers'] as $d) {
            $f = "$release/$d/$class.php";
            if (is_file($f)) { require_once $f; return; }
        }
    });

    $applied = [];
    $error = null;
    try {
        $db = Database::getInstance();
        $migrator = new Migrator($db); // usa releases/<versión>/database/migrations
        $applied = $migrator->up();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $state = $um->afterExternalMigrate($token, $applied, $error);
    $reply(200, ['success' => true, 'data' => $um->publicState($state)]);
} catch (Throwable $e) {
    $reply(500, ['success' => false, 'error' => $e->getMessage()]);
}
