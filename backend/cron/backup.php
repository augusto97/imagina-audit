<?php
/**
 * Cron job: backup automático de la base de datos.
 *
 * Configurar en cPanel (o crontab) para correr una vez al día:
 *
 *   0 3 * * * php /path/to/backend/cron/backup.php
 *
 * El script:
 *   1. Crea un backup con `Backup::create()`.
 *   2. Rota: mantiene solo los últimos BACKUP_RETENTION_COUNT archivos.
 *   3. Loguea el resultado en backend/logs/backup.log.
 *
 * Si shell_exec está disponible y el driver es MySQL, usa mysqldump
 * nativo (rápido y consistente). Si no, cae al dump PHP nativo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI-only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Backup.php';
require_once dirname(__DIR__) . '/lib/Logger.php';

try {
    $db = Database::getInstance();
    $path = Backup::create($db);
    $sizeMb = round(filesize($path) / 1024 / 1024, 2);
    $line = sprintf('[%s] OK driver=%s file=%s size=%sMB', date('c'), $db->driver(), basename($path), $sizeMb);
    Logger::info($line);
    echo $line . PHP_EOL;
} catch (Throwable $e) {
    $line = sprintf('[%s] FAILED — %s', date('c'), $e->getMessage());
    if (class_exists('Logger') && method_exists('Logger', 'error')) Logger::error($line);
    fwrite(STDERR, $line . PHP_EOL);
    exit(2);
}
