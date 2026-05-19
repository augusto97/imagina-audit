<?php
/**
 * CLI: migra la DB SQLite actual hacia un MySQL nuevo.
 *
 * Uso:
 *   php backend/database/migrate-from-sqlite.php \
 *     --sqlite=/path/audit.db \
 *     --mysql-host=localhost --mysql-port=3306 \
 *     --mysql-db=imagina_audit --mysql-user=root --mysql-pass=secret
 *
 * El script:
 *   1. Aplica el schema en MySQL (corre el Migrator).
 *   2. Copia datos SQLite → MySQL en orden topológico.
 *   3. Reporta counts post-migración.
 *
 * NO toca el .env del backend — el admin debe cambiar DB_DRIVER a mano
 * cuando esté listo. Esto separa "tener los datos copiados" de "switch
 * en producción".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI-only script.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/lib/Database.php';
require_once dirname(__DIR__) . '/lib/Migrator.php';
require_once dirname(__DIR__) . '/lib/CrossDbMigrator.php';

// Parsear flags (--key=value)
$args = [];
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $a) {
    if (preg_match('/^--([^=]+)(?:=(.+))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? '';
    }
}

$sqlitePath = $args['sqlite'] ?? env('DB_SQLITE_PATH', '');
if ($sqlitePath === '') {
    // Fallback al path por defecto
    $candidate = dirname(__DIR__, 2) . '/imagina_audit_data/audit.db';
    if (is_file($candidate)) $sqlitePath = $candidate;
    else $sqlitePath = dirname(__DIR__) . '/database/audit.db';
}
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite source not found: $sqlitePath\n");
    exit(2);
}

$mysql = [
    'host' => $args['mysql-host'] ?? 'localhost',
    'port' => $args['mysql-port'] ?? '3306',
    'name' => $args['mysql-db'] ?? '',
    'user' => $args['mysql-user'] ?? '',
    'password' => $args['mysql-pass'] ?? '',
    'charset' => $args['mysql-charset'] ?? 'utf8mb4',
];
if ($mysql['name'] === '' || $mysql['user'] === '') {
    fwrite(STDERR, "Missing --mysql-db or --mysql-user\n");
    exit(2);
}

echo "Source: $sqlitePath\n";
echo "Target: {$mysql['user']}@{$mysql['host']}:{$mysql['port']}/{$mysql['name']}\n\n";

try {
    // 1. Aplicar schema en MySQL
    $targetPdo = new PDO(
        sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s", $mysql['host'], $mysql['port'], $mysql['name'], $mysql['charset']),
        $mysql['user'], $mysql['password']
    );
    $targetPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $targetPdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    $targetPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $targetPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $tmpDb = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(Database::class, 'driver'))->setValue($tmpDb, 'mysql');
    (new ReflectionProperty(Database::class, 'dialect'))->setValue($tmpDb, new MysqlDialect());
    (new ReflectionProperty(Database::class, 'pdo'))->setValue($tmpDb, $targetPdo);

    echo "[1/2] Applying schema on MySQL...\n";
    $migrator = new Migrator($tmpDb);
    $applied = $migrator->up();
    echo "  applied " . count($applied) . " migration(s)\n";

    // 2. Copy data
    echo "\n[2/2] Copying data...\n";
    $copier = CrossDbMigrator::fromConfig(['path' => $sqlitePath], $mysql);
    $result = $copier->run(function ($table, $copied, $total) {
        printf("\r  %-32s %6d / %6d", $table, $copied, $total);
    });
    echo "\n\nDone in {$result['durationSeconds']}s. {$result['totalRowsCopied']} rows copied.\n";
    echo "\nPer-table:\n";
    foreach ($result['tables'] as $table => $info) {
        $mark = $info['skipped'] ? '-' : '+';
        printf("  %s %-32s source=%-6d target=%-6d\n", $mark, $table, $info['source'], $info['target']);
    }

    echo "\nNext step:\n";
    echo "  1. Edit backend/.env and set DB_DRIVER=mysql + the credentials above.\n";
    echo "  2. Reload the app.\n";
    echo "  3. Keep the SQLite file as backup until you're confident MySQL is healthy.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nMigration failed: " . $e->getMessage() . "\n");
    exit(3);
}
