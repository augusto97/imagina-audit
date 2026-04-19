<?php
/**
 * Bootstrap para PHPUnit.
 *
 * Replica el autoload del backend (lib/ y analyzers/) para que los tests
 * puedan instanciar las clases sin Composer en producción.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('env')) {
    // Stub de env() para tests — lee de getenv con fallback al default
    function env(string $key, mixed $default = null): mixed {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

spl_autoload_register(function (string $class) {
    $paths = [
        __DIR__ . '/../lib/' . $class . '.php',
        __DIR__ . '/../analyzers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
