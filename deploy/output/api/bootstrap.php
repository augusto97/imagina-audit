<?php
/**
 * Bootstrap — carga dependencias si se accede al archivo PHP directamente
 * (sin pasar por index.php)
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

// Inicializar DB si no existe
try {
    $db = Database::getInstance();
    $db->initSchema();
} catch (Throwable $e) {
    // silenciar — se loguea cuando Logger esté disponible
}

// CORS
Response::cors();
