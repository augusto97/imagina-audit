<?php
/**
 * Bootstrap — carga dependencias si se accede al archivo PHP directamente
 * (sin pasar por index.php).
 *
 * Además asegura que las carpetas críticas existen y son escribibles por
 * el proceso PHP. Si el usuario subió los archivos por SFTP sin ajustar
 * permisos, esto los corrige automáticamente (el proceso PHP es el dueño
 * de los archivos subidos por SFTP en la mayoría de hostings).
 */

if (!function_exists('env')) {
    require_once dirname(__DIR__) . '/config/env.php';
}

if (!class_exists('Database')) {
    spl_autoload_register(function (string $class) {
        $paths = [
            dirname(__DIR__) . '/lib/' . $class . '.php',
            dirname(__DIR__) . '/analyzers/' . $class . '.php',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    });
}

// Auto-fix: asegurar que las carpetas escribibles existen con permisos correctos.
// @ suprime warnings si el proceso PHP no es dueño de los archivos —
// el check de /api/diag.php detectará el problema y lo reportará.
(function () {
    $base = dirname(__DIR__);
    foreach (['cache', 'logs', 'database', 'uploads', 'storage', 'storage/plugins'] as $dir) {
        $path = "$base/$dir";
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        if (is_dir($path) && !is_writable($path)) {
            @chmod($path, 0755);
        }
    }
    // .env debe ser 0600 (solo lectura dueño) si existe
    $envFile = "$base/.env";
    if (file_exists($envFile) && (fileperms($envFile) & 0777) !== 0600) {
        @chmod($envFile, 0600);
    }
})();

// Inicializar DB + migraciones
try {
    $db = Database::getInstance();
    $db->initSchema();
} catch (Throwable $e) {
    // silenciar — se loguea cuando Logger esté disponible
}

// CORS
Response::cors();
