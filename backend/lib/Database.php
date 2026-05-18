<?php
require_once __DIR__ . '/db/Dialect.php';
require_once __DIR__ . '/db/SqliteDialect.php';
require_once __DIR__ . '/db/MysqlDialect.php';

/**
 * Wrapper de DB con PDO. Soporta dos drivers:
 *
 *   - sqlite (default / fallback) — single-file, ideal para dev e installs
 *     pequeñas. Activo si DB_DRIVER no está seteado o vale 'sqlite'.
 *   - mysql (producción) — MySQL 5.7+ / MariaDB 10.3+. Activo si
 *     DB_DRIVER=mysql.
 *
 * Singleton durante la ejecución para reutilizar la conexión. Los métodos
 * que terminan en "Sql" (query, queryOne, execute, scalar) no han cambiado
 * — siguen aceptando SQL crudo con placeholders ? y bindings positional.
 *
 * Los helpers nuevos (`now()`, `transaction()`, `bool()`, `json()`,
 * `upsert()`, `dialect()`) van por encima de la abstracción Dialect para
 * que el código de negocio no tenga que conocer el driver activo.
 *
 * NOTA P7: initSchema() / runMigrations() siguen aquí por compatibilidad
 * pero solo operan en SQLite. Para MySQL hay que correr el migrator
 * versionado de la Fase 2 (P7.2). En SQLite el comportamiento es
 * idéntico al pre-P7.
 */
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    private Dialect $dialect;
    private string $driver;  // 'sqlite' | 'mysql'

    private function __construct() {
        $this->driver = self::resolveDriver();
        $this->dialect = $this->driver === 'mysql' ? new MysqlDialect() : new SqliteDialect();

        $this->pdo = $this->driver === 'mysql'
            ? $this->connectMysql()
            : $this->connectSqlite();

        $this->postConnect();
    }

    /**
     * Lee DB_DRIVER del entorno. Cualquier valor distinto de 'mysql' cae a
     * 'sqlite' — preservamos el modo zero-config por default.
     */
    private static function resolveDriver(): string {
        $val = function_exists('env') ? strtolower(env('DB_DRIVER', 'sqlite')) : 'sqlite';
        return $val === 'mysql' ? 'mysql' : 'sqlite';
    }

    /** Conexión MySQL/MariaDB. Lee credenciales del .env. */
    private function connectMysql(): PDO {
        $host = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
        $port = function_exists('env') ? env('DB_PORT', '3306') : '3306';
        $name = function_exists('env') ? env('DB_NAME', '') : '';
        $user = function_exists('env') ? env('DB_USER', '') : '';
        $pass = function_exists('env') ? env('DB_PASSWORD', '') : '';
        $charset = function_exists('env') ? env('DB_CHARSET', 'utf8mb4') : 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException(
                'DB_DRIVER=mysql requires DB_NAME and DB_USER in .env'
            );
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Importante: MYSQL_ATTR_INIT_COMMAND solo sirve si la
            // extensión pdo_mysql está cargada con MYSQLI. Lo replicamos
            // en postConnect() para máxima compatibilidad.
        ]);
    }

    /** Conexión SQLite single-file (camino histórico). */
    private function connectSqlite(): PDO {
        $dbPath = $this->getDatabasePath();
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0755, true);
        }
        return new PDO("sqlite:$dbPath", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Setup post-conexión que cambia según el driver. Tunings de
     * performance y enforcement de constraints.
     */
    private function postConnect(): void {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
            $this->pdo->exec($this->dialect->enforceForeignKeys());
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            // MySQL: charset por si el driver lo ignora, timezone UTC,
            // FK checks ON, strict mode para que los inserts inválidos
            // fallen en vez de truncar silenciosamente.
            $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("SET time_zone = '+00:00'");
            $this->pdo->exec($this->dialect->enforceForeignKeys());
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Para tests: forzar reconexión con un driver distinto. */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Ruta al archivo SQLite. Igual que pre-P7 — intenta vivir fuera de
     * public_html y fallback a backend/database/ si no se puede.
     */
    private function getDatabasePath(): string {
        $envPath = function_exists('env') ? env('DB_SQLITE_PATH', '') : '';
        if ($envPath !== '') {
            // Si el .env trae una ruta explícita, gana.
            return $envPath;
        }
        $outsidePath = dirname(__DIR__, 2) . '/imagina_audit_data/audit.db';
        $outsideDir = dirname($outsidePath);
        if (is_dir($outsideDir) || @mkdir($outsideDir, 0755, true)) {
            return $outsidePath;
        }
        return dirname(__DIR__) . '/database/audit.db';
    }

    // ─── Public API (sin cambios funcionales vs pre-P7) ───────────────

    public function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    public function scalar(string $sql, array $params = []): mixed {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getPdo(): PDO { return $this->pdo; }

    // ─── Helpers cross-driver (nuevos en P7.1) ────────────────────────

    /** Driver activo: 'sqlite' | 'mysql'. */
    public function driver(): string { return $this->driver; }

    /** Objeto Dialect del driver activo. Para casos avanzados. */
    public function dialect(): Dialect { return $this->dialect; }

    /**
     * Devuelve la expresión SQL para "ahora". Usar en DEFAULT de columnas
     * o en UPDATEs literales (NO interpolar valores de usuario). Para
     * timestamps pasados como parámetro, simplemente bindear el ISO string.
     */
    public function now(): string { return $this->dialect->now(); }

    /**
     * Normaliza un valor a booleano binario que ambos drivers entienden
     * (0/1). Útil para columnas declaradas como TINYINT/INTEGER booleanas.
     */
    public function bool(mixed $v): int { return $v ? 1 : 0; }

    /**
     * Encodea un valor a JSON string apto para columna JSON/TEXT. Si el
     * valor ya es string, asume que ya viene serializado.
     */
    public function json(mixed $v): string {
        if (is_string($v)) return $v;
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
    }

    /**
     * Ejecuta una callable dentro de transacción. Si lanza, hace rollback;
     * si retorna, commit. Devuelve lo que retornó la callable.
     *
     * Uso típico para mutaciones multi-tabla (regla 7 de P7):
     *
     *   $db->transaction(function ($db) {
     *       $db->execute('INSERT INTO a ...');
     *       $db->execute('INSERT INTO b ...');
     *   });
     */
    public function transaction(callable $fn): mixed {
        // Si ya hay una transacción activa (nested), corremos en la misma.
        // PDO no soporta nested nativos en MySQL; las anidaciones
        // funcionan vía savepoints — para P7.1 mantenemos simple.
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Upsert portable. Construye el SQL via Dialect y lo ejecuta.
     *
     * @param string $table         Nombre de tabla.
     * @param array $row            Mapa columna => valor a insertar.
     * @param array $uniqueKeys     Columnas únicas que disparan el UPDATE.
     * @param array|null $update    Columnas a actualizar en conflicto.
     *                              null = todas las que estén en $row
     *                              (excluyendo $uniqueKeys).
     *                              [] = no actualizar (DO NOTHING).
     * @return int Filas afectadas.
     */
    public function upsert(string $table, array $row, array $uniqueKeys, ?array $update = null): int {
        $columns = array_keys($row);
        if (empty($columns)) return 0;
        if ($update === null) {
            $update = array_values(array_diff($columns, $uniqueKeys));
        }
        $sql = $this->dialect->upsert($table, $columns, $uniqueKeys, $update);
        return $this->execute($sql, array_values($row));
    }

    // ─── Schema bootstrap legacy (solo SQLite — Fase 2 reemplaza esto) ─

    /**
     * Inicializa el schema legacy de SQLite. Para MySQL es un no-op:
     * Phase 2 (P7.2) introduce el migrator versionado y este método será
     * eliminado.
     */
    public function initSchema(): void {
        if ($this->driver !== 'sqlite') {
            // En MySQL la inicialización corre vía Migrator (P7.2). El
            // setup wizard (P7.4) llama al migrator explícitamente; el
            // boot normal ya no necesita tocar el schema.
            return;
        }

        // 1. Migraciones sobre tablas existentes (si las hay)
        $this->runMigrations();

        // 2. Schema completo — tolerante a fallos por statement
        $schemaPath = dirname(__DIR__) . '/database/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $statements = $this->splitSqlStatements($sql);
            foreach ($statements as $stmt) {
                try {
                    $this->pdo->exec($stmt);
                } catch (Throwable $e) {
                    // Ignorar fallos por statement (IF NOT EXISTS que no aplica,
                    // índices sobre columnas aún no migradas, etc.)
                }
            }
        }

        // 3. Repetir migraciones por si alguna no aplicó por el orden
        $this->runMigrations();
    }

    /**
     * Parte un dump SQL en statements individuales, respetando strings.
     * Tolerante a strings con ';' internos.
     */
    private function splitSqlStatements(string $sql): array {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim(rtrim(trim($p), ';'));
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    /**
     * Migraciones defensive ad-hoc — pre-P7. Solo se aplican en SQLite.
     * Phase 2 (P7.2) reemplaza este método por el Migrator versionado.
     */
    private function runMigrations(): void {
        if ($this->driver !== 'sqlite') return;

        $migrations = [
            "ALTER TABLE audits ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE audits ADD COLUMN lang TEXT NOT NULL DEFAULT 'en'",
            "ALTER TABLE audits ADD COLUMN user_id INTEGER",
            "ALTER TABLE audits ADD COLUMN project_id INTEGER",
            "ALTER TABLE plans ADD COLUMN max_projects INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE audits ADD COLUMN is_deleted INTEGER NOT NULL DEFAULT 0",
        ];
        $seedLanguages = [
            ['en', 'English', 'English', 0],
            ['es', 'Spanish', 'Español', 1],
        ];
        foreach ($migrations as $sql) {
            try { $this->pdo->exec($sql); } catch (Throwable $e) { /* columna ya existe */ }
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT OR IGNORE INTO languages (code, name, native_name, is_active, is_public, sort_order) VALUES (?, ?, ?, 1, 1, ?)"
            );
            foreach ($seedLanguages as [$code, $name, $native, $order]) {
                $stmt->execute([$code, $name, $native, $order]);
            }
        } catch (Throwable $e) { /* tabla aún no creada */ }
    }

    // Prevenir clonación y deserialización
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception('No se puede deserializar un singleton');
    }
}
