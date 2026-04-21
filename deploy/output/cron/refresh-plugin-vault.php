<?php
/**
 * Cron: refresca el plugin vault contra GitHub.
 *
 * Llamarlo con el cron del sistema o un wp-cron-style hit:
 *   30 3 1 * *  /usr/bin/php /var/www/audit/cron/refresh-plugin-vault.php
 *   (1ro de cada mes a las 03:30)
 *
 * También se puede invocar vía web con un token:
 *   curl https://audit.tu-dominio.com/cron/refresh-plugin-vault.php?token=$CRON_TOKEN
 *   (en ese caso se valida CRON_TOKEN del .env contra ?token)
 */

// Si está corriendo por web, validar token
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/api/bootstrap.php';
    $expected = env('CRON_TOKEN', '');
    $given = $_GET['token'] ?? '';
    if ($expected === '' || !hash_equals($expected, (string) $given)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain');
} else {
    // CLI: cargar entorno manualmente
    require_once dirname(__DIR__) . '/config/env.php';
    // Autoload mínimo
    spl_autoload_register(function ($class) {
        foreach (['lib', 'analyzers'] as $dir) {
            $f = dirname(__DIR__) . '/' . $dir . '/' . $class . '.php';
            if (file_exists($f)) { require_once $f; return; }
        }
    });
}

set_time_limit(300);

$results = [];
foreach (PluginVault::catalog() as $slug => $info) {
    echo "[" . date('c') . "] Refrescando $slug...\n";
    $status = PluginVault::refresh($slug);
    if ($status === null) {
        echo "  FAIL\n";
        $results[$slug] = 'fail';
    } else {
        echo "  OK · v{$status['version']} · " . round(($status['sizeBytes'] ?? 0) / 1024) . " KB\n";
        $results[$slug] = 'ok';
    }
}

CronHealth::markRun('refresh-plugin-vault', null, implode(',', array_map(fn($s, $r) => "$s=$r", array_keys($results), $results)));

echo "\nDone.\n";
exit(in_array('fail', $results, true) ? 1 : 0);
