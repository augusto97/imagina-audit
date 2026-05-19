<?php
/**
 * Admin endpoint para migrar datos de SQLite a MySQL.
 *
 *   GET                              → status + preview (table counts)
 *   POST { action: 'test', ... }     → test conexión MySQL recibida
 *   POST { action: 'run', ... }      → ejecuta la migración completa
 *   POST { action: 'switch' }        → actualiza .env a DB_DRIVER=mysql
 *
 * Requisitos:
 *   - El driver actual debe ser SQLite (no tiene sentido si ya estás
 *     en MySQL).
 *   - Las credenciales MySQL llegan en el body o se leen del .env (si
 *     ya fueron configuradas pero el driver activo sigue siendo SQLite,
 *     ej. el admin escribió las creds pero aún no switchea).
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$currentDriver = $db->driver();
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: status + preview ───────────────────────────────────────────
if ($method === 'GET') {
    $payload = [
        'currentDriver' => $currentDriver,
        'canMigrate' => $currentDriver === 'sqlite',
        'envHasMysql' => env('DB_HOST', '') !== '' && env('DB_NAME', '') !== '' && env('DB_USER', '') !== '',
    ];

    if ($currentDriver === 'sqlite') {
        // Preview de counts en source
        $tables = [
            'audits', 'settings', 'translations', 'languages', 'plans', 'users',
            'projects', 'project_checklist_items', 'audits', 'wp_snapshots',
            'checklist_items', 'audit_jobs', 'vulnerabilities',
        ];
        $sourceCounts = [];
        foreach (array_unique($tables) as $t) {
            try {
                $sourceCounts[$t] = (int) $db->scalar("SELECT COUNT(*) FROM `$t`");
            } catch (Throwable $e) { $sourceCounts[$t] = null; }
        }
        $payload['sourceTableCounts'] = $sourceCounts;
        $payload['sourceTotalRows'] = array_sum(array_filter($sourceCounts, 'is_int'));
    }

    Response::success($payload);
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = Response::getJsonBody();
$action = $body['action'] ?? '';

// ─── POST test ───────────────────────────────────────────────────────
if ($action === 'test') {
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $body['host'] ?? 'localhost',
            $body['port'] ?? '3306',
            $body['name'] ?? '',
            $body['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $body['user'] ?? '', $body['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        Response::success(['ok' => true, 'version' => $version]);
    } catch (PDOException $e) {
        $msg = preg_replace('/^SQLSTATE\[\w+\](?:\s*\[\d+\])?\s*/', '', $e->getMessage()) ?? $e->getMessage();
        Response::error("Connection failed: $msg", 400);
    }
}

// ─── POST run: ejecuta la migración ──────────────────────────────────
if ($action === 'run') {
    if ($currentDriver !== 'sqlite') {
        Response::error('You are already on MySQL. Migration is only available from a SQLite install.', 400);
    }

    // SQLite path: lo conseguimos del Database actual (singleton ya
    // resolvió la ruta). Para evitar tocar internals, lo deducimos del
    // env y del fallback.
    $sqlitePath = env('DB_SQLITE_PATH', '');
    if ($sqlitePath === '') {
        $candidate = dirname(__DIR__, 3) . '/imagina_audit_data/audit.db';
        if (is_file($candidate)) $sqlitePath = $candidate;
        else $sqlitePath = dirname(__DIR__, 2) . '/database/audit.db';
    }
    if (!is_file($sqlitePath)) {
        Response::error("SQLite source not found at $sqlitePath", 400);
    }

    $targetMysql = [
        'host' => $body['host'] ?? 'localhost',
        'port' => $body['port'] ?? '3306',
        'name' => $body['name'] ?? '',
        'user' => $body['user'] ?? '',
        'password' => $body['password'] ?? '',
        'charset' => $body['charset'] ?? 'utf8mb4',
    ];
    if ($targetMysql['name'] === '' || $targetMysql['user'] === '') {
        Response::error('MySQL name and user are required', 400);
    }

    set_time_limit(300); // hasta 5 min para la copia

    try {
        // 1. Aplicar el schema en MySQL usando el Migrator
        $tmpTargetPdo = new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $targetMysql['host'], $targetMysql['port'], $targetMysql['name'], $targetMysql['charset']),
            $targetMysql['user'], $targetMysql['password']
        );

        // Para correr el Migrator necesitamos un objeto Database apuntando
        // al MySQL nuevo. Construimos uno ad-hoc por reflexión para no
        // pisar el singleton activo (que sigue siendo SQLite).
        $tmpDb = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Database::class, 'driver'))->setValue($tmpDb, 'mysql');
        (new ReflectionProperty(Database::class, 'dialect'))->setValue($tmpDb, new MysqlDialect());
        (new ReflectionProperty(Database::class, 'pdo'))->setValue($tmpDb, $tmpTargetPdo);

        $tmpTargetPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tmpTargetPdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $tmpTargetPdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

        $schemaMigrator = new Migrator($tmpDb);
        $appliedSchema = $schemaMigrator->up();

        // 2. Copy de datos SQLite → MySQL
        $copier = CrossDbMigrator::fromConfig(['path' => $sqlitePath], $targetMysql);
        $result = $copier->run();

        Logger::info('SQLite → MySQL migration completed: ' . json_encode($result));

        Response::success([
            'ok' => true,
            'schemaMigrationsApplied' => count($appliedSchema),
            'rowsCopied' => $result['totalRowsCopied'],
            'durationSeconds' => $result['durationSeconds'],
            'tables' => $result['tables'],
        ]);
    } catch (Throwable $e) {
        Logger::error('SQLite → MySQL migration failed: ' . $e->getMessage());
        Response::error('Migration failed: ' . $e->getMessage(), 500);
    }
}

// ─── POST switch: cambia el .env a DB_DRIVER=mysql ───────────────────
if ($action === 'switch') {
    if ($currentDriver !== 'sqlite') {
        Response::error('Already on MySQL — nothing to switch', 400);
    }
    $updates = [
        'DB_DRIVER' => 'mysql',
        'DB_HOST' => $body['host'] ?? env('DB_HOST', 'localhost'),
        'DB_PORT' => $body['port'] ?? env('DB_PORT', '3306'),
        'DB_NAME' => $body['name'] ?? env('DB_NAME', ''),
        'DB_USER' => $body['user'] ?? env('DB_USER', ''),
        'DB_PASSWORD' => $body['password'] ?? env('DB_PASSWORD', ''),
        'DB_CHARSET' => $body['charset'] ?? env('DB_CHARSET', 'utf8mb4'),
    ];
    if (!EnvWriter::update($updates)) {
        Response::error('Failed to write .env. Check file permissions.', 500);
    }
    Logger::info('Switched DB driver to MySQL via admin endpoint');
    Response::success(['ok' => true, 'nextSteps' => 'Reload the page; the next request boots on MySQL.']);
}

Response::error('Unknown action', 400);
