<?php
/**
 * CrossDbMigrator — copia datos de una DB SQLite a una DB MySQL.
 *
 * Diseño:
 *   - Construye una conexión fuente (SQLite) y una destino (MySQL).
 *   - Ejecuta el Migrator versionado en el destino para crear el schema.
 *   - Recorre las tablas en orden topológico (respetando FKs) y mueve
 *     las filas en batches.
 *   - Verifica counts post-migración.
 *
 * No reescribe el .env — eso es responsabilidad del caller (endpoint o
 * CLI), porque la decisión de "switchear" implica downtime y se hace
 * en una operación separada y consciente.
 */
class CrossDbMigrator
{
    /**
     * Orden topológico: las dependencias se migran antes de las tablas
     * que las referencian. El migrator del schema (0001_initial) garantiza
     * que todas existan en el destino antes de empezar el copy.
     */
    private const TABLE_ORDER = [
        'schema_migrations',
        'settings',
        'languages',
        'translations',
        'plans',
        'users',
        'user_login_attempts',
        'projects',
        'project_checklist_items',
        'audits',
        'wp_snapshots',
        'checklist_items',
        'audit_jobs',
        'vulnerabilities',
        'rate_limits',
    ];

    private const BATCH_SIZE = 200;

    private PDO $source;   // SQLite
    private PDO $target;   // MySQL

    public function __construct(PDO $source, PDO $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * Crea conexiones fuente (SQLite) y destino (MySQL) a partir de
     * configuración explícita. Útil cuando se invoca desde el wizard
     * con credenciales pasadas en el body (sin esperar a que el .env
     * cambie).
     */
    public static function fromConfig(array $sourceSqlitePath, array $targetMysql): self
    {
        $sourcePath = $sourceSqlitePath['path'] ?? '';
        if ($sourcePath === '' || !is_file($sourcePath)) {
            throw new RuntimeException("Source SQLite file not found: $sourcePath");
        }
        $source = new PDO("sqlite:$sourcePath", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $host = $targetMysql['host'] ?? 'localhost';
        $port = $targetMysql['port'] ?? '3306';
        $name = $targetMysql['name'] ?? '';
        $user = $targetMysql['user'] ?? '';
        $pass = $targetMysql['password'] ?? '';
        $charset = $targetMysql['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
        $target = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Configuración mínima de sesión MySQL para el copy
        $target->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $target->exec("SET time_zone = '+00:00'");
        $target->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

        return new self($source, $target);
    }

    /**
     * Compara counts entre source y target para cada tabla. Útil para
     * mostrar un preview antes de ejecutar la migración.
     */
    public function preview(): array
    {
        $rows = [];
        foreach (self::TABLE_ORDER as $table) {
            $rows[] = [
                'table' => $table,
                'sourceRows' => $this->countTable($this->source, $table),
                'targetRows' => $this->countTable($this->target, $table),
            ];
        }
        return $rows;
    }

    /**
     * Ejecuta la migración completa. Por cada tabla:
     *   1. TRUNCATE en target (FKs disabled para esto).
     *   2. Bulk copy desde source en batches.
     *   3. Re-check counts post-copy.
     *
     * Si una tabla falla, el método lanza una RuntimeException con el
     * estado parcial — el caller decide si reintenta o aborta.
     *
     * @param callable|null $onProgress Callback con (table, copied, total).
     * @return array Resultado con counts y duración total.
     */
    public function run(?callable $onProgress = null): array
    {
        $startedAt = microtime(true);
        $perTable = [];

        // FKs OFF durante el copy — el orden topológico no garantiza
        // que MySQL acepte cada insert (ej. timestamps fuera de orden).
        // El statement es MySQL-only; en otros drivers (SQLite en tests)
        // lo ignoramos silenciosamente.
        $targetDriver = $this->target->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($targetDriver === 'mysql') {
            $this->target->exec('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($targetDriver === 'sqlite') {
            $this->target->exec('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach (self::TABLE_ORDER as $table) {
                $totalSource = $this->countTable($this->source, $table);
                if ($totalSource === null) {
                    // Tabla no existe en source — skip
                    $perTable[$table] = ['source' => 0, 'copied' => 0, 'target' => 0, 'skipped' => true];
                    continue;
                }
                // Limpiar destino
                try { $this->target->exec("TRUNCATE TABLE `$table`"); }
                catch (Throwable $e) { $this->target->exec("DELETE FROM `$table`"); }

                $copied = $this->copyTable($table, $totalSource, $onProgress);
                $totalTarget = $this->countTable($this->target, $table) ?? 0;
                $perTable[$table] = [
                    'source' => $totalSource,
                    'copied' => $copied,
                    'target' => $totalTarget,
                    'skipped' => false,
                ];
                if ($totalTarget !== $totalSource) {
                    throw new RuntimeException(
                        "Count mismatch on `$table`: expected $totalSource, got $totalTarget"
                    );
                }
            }
        } finally {
            if ($targetDriver === 'mysql') {
                $this->target->exec('SET FOREIGN_KEY_CHECKS = 1');
            } elseif ($targetDriver === 'sqlite') {
                $this->target->exec('PRAGMA foreign_keys = ON');
            }
        }

        return [
            'durationSeconds' => round(microtime(true) - $startedAt, 2),
            'tables' => $perTable,
            'totalRowsCopied' => array_sum(array_column($perTable, 'copied')),
        ];
    }

    // ─── Internos ──────────────────────────────────────────────────────

    private function countTable(PDO $pdo, string $table): ?int
    {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            return $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Copia una tabla en batches. Detecta columnas desde el resultset
     * del primer batch.
     */
    private function copyTable(string $table, int $total, ?callable $onProgress): int
    {
        if ($total === 0) {
            if ($onProgress) $onProgress($table, 0, 0);
            return 0;
        }

        $offset = 0;
        $copied = 0;
        while ($offset < $total) {
            $batch = $this->source->query("SELECT * FROM `$table` LIMIT " . self::BATCH_SIZE . " OFFSET $offset")
                ->fetchAll();
            if (empty($batch)) break;

            $columns = array_keys($batch[0]);
            $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));
            $placeholderTuple = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $valueTuples = implode(', ', array_fill(0, count($batch), $placeholderTuple));

            $sql = "INSERT INTO `$table` ($colList) VALUES $valueTuples";
            $stmt = $this->target->prepare($sql);

            $params = [];
            foreach ($batch as $row) {
                foreach ($columns as $col) {
                    $params[] = $row[$col];
                }
            }
            $stmt->execute($params);
            $copied += count($batch);
            $offset += self::BATCH_SIZE;
            if ($onProgress) $onProgress($table, $copied, $total);
        }
        return $copied;
    }
}
