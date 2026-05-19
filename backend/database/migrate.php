<?php
/**
 * CLI runner del migrator. Uso:
 *
 *   php backend/database/migrate.php status         # estado actual
 *   php backend/database/migrate.php up             # aplica todas las pendientes
 *   php backend/database/migrate.php rollback       # rollback de la última
 *   php backend/database/migrate.php rollback 3     # rollback de las últimas 3
 *
 * Requiere CLI php (no acceso web). Lee la configuración de DB del .env
 * normal del backend — el driver y credenciales que ya tienes configurados.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI-only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Migrator.php';

$argv = $_SERVER['argv'] ?? [];
$cmd = $argv[1] ?? 'status';
$arg = $argv[2] ?? null;

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

$migrator = new Migrator($db);

switch ($cmd) {
    case 'status':
        $migrator->bootstrap();
        $status = $migrator->status();
        echo "Driver:           {$status['driver']}\n";
        echo "Total available:  {$status['totalAvailable']}\n";
        echo "Total applied:    {$status['totalApplied']}\n";
        echo "Pending:          " . count($status['pending']) . "\n";
        if (!empty($status['pending'])) {
            echo "\nPending migrations:\n";
            foreach ($status['pending'] as $name) {
                echo "  - $name\n";
            }
        }
        break;

    case 'up':
        echo "Applying pending migrations...\n";
        try {
            $applied = $migrator->up();
            if (empty($applied)) {
                echo "Nothing to apply — DB is up to date.\n";
            } else {
                echo "Applied " . count($applied) . " migration(s):\n";
                foreach ($applied as $name) {
                    echo "  ✓ $name\n";
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
            exit(3);
        }
        break;

    case 'rollback':
        $steps = $arg !== null ? max(1, (int) $arg) : 1;
        echo "Rolling back last $steps migration(s)...\n";
        try {
            $rolled = $migrator->rollback($steps);
            if (empty($rolled)) {
                echo "Nothing to rollback (or no .down.sql available).\n";
            } else {
                echo "Rolled back " . count($rolled) . " migration(s):\n";
                foreach ($rolled as $name) {
                    echo "  ↩ $name\n";
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Rollback failed: " . $e->getMessage() . "\n");
            exit(3);
        }
        break;

    default:
        echo "Usage:\n";
        echo "  php migrate.php status\n";
        echo "  php migrate.php up\n";
        echo "  php migrate.php rollback [steps=1]\n";
        exit(1);
}
