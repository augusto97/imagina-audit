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
 * `upsert()`, `setting()`, `settingIfMissing()`, `dialect()`) van por
 * encima de la abstracción Dialect para que el código de negocio no
 * tenga que conocer el driver activo.
 *
 * Schema bootstrap: initSchema() delega al Migrator versionado (que
 * crea schema_migrations + aplica todas las pendientes). El schema.sql
 * legacy y runMigrations() ad-hoc fueron retirados en P7-cleanup.
 *
 * Retry: los métodos query/queryOne/execute/scalar pasan por traced()
 * que reintenta automáticamente errores transientes (deadlock, lock
 * timeout, connection lost) con backoff exponencial 100-200-400ms.
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
        return $this->traced($sql, $params, function () use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        });
    }

    public function queryOne(string $sql, array $params = []): ?array {
        return $this->traced($sql, $params, function () use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result !== false ? $result : null;
        });
    }

    public function execute(string $sql, array $params = []): int {
        return $this->traced($sql, $params, function () use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        });
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    public function scalar(string $sql, array $params = []): mixed {
        return $this->traced($sql, $params, function () use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        });
    }

    /**
     * Wrapper que (1) mide tiempo y loguea las queries lentas, y (2)
     * reintenta automáticamente errores transientes con backoff
     * exponencial.
     *
     * Slow log:
     *   - Umbral SLOW_QUERY_THRESHOLD_MS (env, default 200). 0 = desactivado.
     *   - Log va a Logger::warning con SQL + params (sanitizados).
     *
     * Retry:
     *   - MySQL: 2006 (gone away), 2013 (lost connection), 1205 (lock
     *     wait timeout), 1213 (deadlock), HY000 (general).
     *   - SQLite: SQLITE_BUSY (5), SQLITE_LOCKED (6).
     *   - Backoff: 100ms, 200ms, 400ms. Max 3 intentos.
     *   - Errores no transientes (syntax, FK, UNIQUE) propagan inmediatamente.
     */
    private function traced(string $sql, array $params, callable $fn): mixed
    {
        $start = microtime(true);
        $attempt = 0;
        $maxAttempts = 3;
        // Sentinel para distinguir "success con resultado null" (legitimate:
        // queryOne sin filas) de "loop terminó sin éxito" — isset() no sirve
        // porque isset(null) === false.
        $sentinel = new stdClass();
        $result = $sentinel;
        while ($attempt < $maxAttempts) {
            try {
                $result = $fn();
                break;
            } catch (PDOException $e) {
                if (!$this->isTransientError($e) || $attempt + 1 >= $maxAttempts) {
                    throw $e;
                }
                // Dentro de una transacción no podemos reintentar statements
                // sueltos: un deadlock (MySQL 1213) ya hizo rollback implícito
                // de TODA la transacción. Re-ejecutar este statement en
                // autocommit produce escritura parcial fuera de la
                // transacción y el commit() posterior lanza "no active
                // transaction". El caller (el bloque transaction()) debe
                // reintentar la transacción completa, no nosotros.
                if ($this->pdo->inTransaction()) {
                    throw $e;
                }
                // Backoff: 100ms, 200ms, 400ms
                $backoffMs = 100 * (1 << $attempt);
                usleep($backoffMs * 1000);
                $attempt++;
            }
        }
        if ($result === $sentinel) {
            // No debería ocurrir — el bucle siempre retorna o lanza. Defensa.
            throw new RuntimeException('Query failed without exception');
        }

        $elapsedMs = (microtime(true) - $start) * 1000;
        $threshold = function_exists('env') ? (int) env('SLOW_QUERY_THRESHOLD_MS', '200') : 200;
        if ($threshold > 0 && $elapsedMs >= $threshold && class_exists('Logger')) {
            $sanitized = $this->sanitizeParams($params);
            Logger::warning(sprintf(
                'SLOW QUERY %dms — %s | params: %s',
                (int) $elapsedMs,
                trim(preg_replace('/\s+/', ' ', $sql) ?? $sql),
                json_encode($sanitized, JSON_UNESCAPED_UNICODE)
            ));
        }
        if ($attempt > 0 && class_exists('Logger')) {
            Logger::warning(sprintf(
                'DB query retried %d time(s) before success — %s',
                $attempt,
                trim(preg_replace('/\s+/', ' ', $sql) ?? $sql)
            ));
        }
        return $result;
    }

    /**
     * Decide si un PDOException representa un error transitorio que vale
     * la pena reintentar. Está limitado a códigos específicos para no
     * meterse en loops con errores reales (sintaxis, constraints, etc.).
     */
    private function isTransientError(PDOException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;
        if ($this->driver === 'mysql') {
            return in_array($code, [2006, 2013, 1205, 1213], true);
        }
        if ($this->driver === 'sqlite') {
            // SQLITE_BUSY=5, SQLITE_LOCKED=6
            return in_array($code, [5, 6], true);
        }
        return false;
    }

    /**
     * Sanitiza params para el log: trunca valores largos y enmascara
     * claves que parezcan sensibles (password, token, hash). Esto
     * evita filtrar credenciales en los logs de slow queries.
     */
    private function sanitizeParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            $key = is_string($k) ? strtolower($k) : (string) $k;
            if (is_string($v) && preg_match('/pass|token|hash|secret|key/i', $key)) {
                $out[$k] = '***';
            } elseif (is_string($v) && strlen($v) > 200) {
                $out[$k] = substr($v, 0, 200) . '…';
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
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
     * Timestamp (formato MySQL/SQLite) de "ahora - N segundos". Reemplaza
     * el patrón legacy `datetime('now', '-X seconds')` que era SQLite-only.
     * El caller bindea el resultado como parámetro normal.
     *
     *   "WHERE created_at > ?", [$db->nowMinus(3600)]
     */
    public function nowMinus(int $seconds): string {
        return date('Y-m-d H:i:s', time() - $seconds);
    }

    /** Timestamp del inicio del mes calendario actual (UTC). */
    public function startOfMonth(): string {
        return date('Y-m-01 00:00:00');
    }

    /** Fecha de hoy en formato YYYY-MM-DD. Útil para comparar columnas DATE. */
    public function today(): string {
        return date('Y-m-d');
    }

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

    /**
     * Helper para la tabla `settings` (key-value). Reemplaza el patrón
     * legacy `INSERT OR REPLACE INTO settings (key, value, updated_at)
     * VALUES (?, ?, datetime('now'))` que vivía en ~12 sitios.
     * Cross-driver: usa upsert() internamente.
     */
    public function setting(string $key, string $value): int {
        return $this->upsert(
            'settings',
            ['key' => $key, 'value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
            ['key'],
            ['value', 'updated_at']
        );
    }

    /**
     * Helper "set only if not exists" para settings. Reemplaza
     * `INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)`.
     */
    public function settingIfMissing(string $key, string $value): int {
        return $this->upsert(
            'settings',
            ['key' => $key, 'value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
            ['key'],
            []
        );
    }

    /**
     * Initializa el schema vía el Migrator versionado. Idempotente:
     * crea schema_migrations si no existe y aplica las migraciones
     * pendientes. Sustituye al antiguo initSchema() que ejecutaba
     * schema.sql crudo.
     *
     * Lo llaman bootstrap.php y los CLIs para garantizar que la DB
     * está al día antes de servir requests.
     */
    public function initSchema(): void {
        if (!class_exists('Migrator')) {
            // Migrator vive en lib/Migrator.php — el autoloader debería
            // encontrarlo. Si no está disponible (test minimal), abortamos
            // silenciosamente.
            return;
        }
        $migrator = new Migrator($this);
        $migrator->bootstrap();
        $migrator->up();
    }

    // Prevenir clonación y deserialización
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception('No se puede deserializar un singleton');
    }
}
