<?php
/**
 * Migrator — runner de migraciones SQL versionadas.
 *
 * Diseño:
 *
 *   - Cada migración es un archivo SQL en backend/database/migrations/ con
 *     nombre `NNNN_descripcion.sql` (UP) y opcionalmente
 *     `NNNN_descripcion.down.sql` (DOWN).
 *
 *   - NNNN es un entero monotónico (recomendado 4 dígitos). El orden de
 *     aplicación es estrictamente numérico.
 *
 *   - Las migraciones aplicadas se registran en la tabla
 *     `schema_migrations(version INT PRIMARY KEY, name TEXT, applied_at)`.
 *
 *   - El SQL admite placeholders cross-driver: {{NOW}}, {{AUTO_PK}},
 *     {{BOOL}}, {{JSON}}, {{INT}}, {{BIGINT}}, {{TEXT_LONG}},
 *     {{TABLE_OPTIONS}}. El Migrator los reemplaza según el driver activo
 *     antes de ejecutar.
 *
 *   - El SQL admite bloques condicionales por driver:
 *
 *         --{mysql}
 *         CREATE INDEX ... USING btree(...);
 *         --{/mysql}
 *
 *         --{sqlite}
 *         CREATE INDEX ... (...);
 *         --{/sqlite}
 *
 *     Las líneas dentro de un bloque que no coincide con el driver activo
 *     se eliminan antes de splitear el SQL.
 *
 *   - Cada migración se aplica dentro de transacción cuando el driver lo
 *     permite (SQLite). MySQL hace autocommit en DDL — el migrator lo
 *     respeta y marca la versión aplicada solo si todos los statements
 *     ejecutaron sin error.
 *
 *   - Si una migración falla, el migrator deja la DB intacta (rollback en
 *     SQLite) o parcialmente migrada (MySQL DDL) — en ese caso se loguea
 *     un error claro y el admin debe arreglar a mano. La migración NO se
 *     marca como aplicada, así que el siguiente run la reintenta.
 */
class Migrator
{
    private Database $db;
    private string $driver;
    private string $dir;

    public function __construct(Database $db, ?string $migrationsDir = null)
    {
        $this->db = $db;
        $this->driver = $db->driver();
        $this->dir = $migrationsDir ?? dirname(__DIR__) . '/database/migrations';
    }

    /**
     * Asegura que existe la tabla de tracking. Idempotente — se llama
     * antes de cualquier operación del migrator.
     */
    public function bootstrap(): void
    {
        if ($this->driver === 'mysql') {
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS schema_migrations (
                    version INT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS schema_migrations (
                    version INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
                )"
            );
        }
    }

    /**
     * Lista las migraciones disponibles en el filesystem.
     * Retorna [['version'=>1,'name'=>'initial','file'=>'/path/0001_initial.sql','downFile'=>null|path]]
     */
    public function available(): array
    {
        if (!is_dir($this->dir)) return [];
        $files = glob($this->dir . '/*.sql') ?: [];
        $out = [];
        foreach ($files as $file) {
            $basename = basename($file);
            // Saltar archivos DOWN — los emparejamos con su UP.
            if (str_ends_with($basename, '.down.sql')) continue;
            // Formato esperado: NNNN_nombre.sql
            if (!preg_match('/^(\d+)_(.+)\.sql$/', $basename, $m)) continue;
            $version = (int) $m[1];
            $name = $m[2];
            $downFile = $this->dir . "/{$m[1]}_{$name}.down.sql";
            $out[] = [
                'version' => $version,
                'name' => $name,
                'file' => $file,
                'downFile' => is_file($downFile) ? $downFile : null,
            ];
        }
        usort($out, fn($a, $b) => $a['version'] <=> $b['version']);
        return $out;
    }

    /**
     * Versiones ya aplicadas según la DB. Vacío si la tabla aún no existe
     * (en cuyo caso bootstrap() la crea en el primer run).
     */
    public function applied(): array
    {
        try {
            $rows = $this->db->query("SELECT version, name, applied_at FROM schema_migrations ORDER BY version");
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Lista de pendientes: disponibles que no están en applied(). */
    public function pending(): array
    {
        $appliedVersions = array_column($this->applied(), 'version');
        return array_values(array_filter(
            $this->available(),
            fn($m) => !in_array($m['version'], $appliedVersions, true)
        ));
    }

    /**
     * Resumen para la CLI / endpoint de health: cuántas disponibles vs
     * cuántas aplicadas, lista de pendientes.
     */
    public function status(): array
    {
        return [
            'driver' => $this->driver,
            'totalAvailable' => count($this->available()),
            'totalApplied' => count($this->applied()),
            'pending' => array_map(fn($m) => sprintf('%04d_%s', $m['version'], $m['name']), $this->pending()),
        ];
    }

    /**
     * Aplica todas las pendientes en orden. Retorna lista de versiones
     * aplicadas en este run. Si una falla, se detiene y propaga la
     * excepción (las anteriores ya quedaron aplicadas).
     */
    public function up(): array
    {
        $this->bootstrap();
        $applied = [];
        foreach ($this->pending() as $mig) {
            $this->applyMigration($mig);
            $applied[] = sprintf('%04d_%s', $mig['version'], $mig['name']);
        }
        return $applied;
    }

    /**
     * Rollback de las últimas N migraciones aplicadas. Es estrictamente LIFO —
     * si la última aplicada no tiene archivo `.down.sql`, lanza una
     * excepción en vez de saltar a una más vieja (eso dejaría el schema
     * inconsistente: las migraciones posteriores que dependen de esta
     * quedarían huérfanas).
     */
    public function rollback(int $steps = 1): array
    {
        $this->bootstrap();
        $applied = array_reverse($this->applied());
        $available = [];
        foreach ($this->available() as $m) {
            $available[$m['version']] = $m;
        }
        $rolledBack = [];
        $count = 0;
        foreach ($applied as $row) {
            if ($count >= $steps) break;
            $version = (int) $row['version'];
            $mig = $available[$version] ?? null;
            if (!$mig) {
                throw new RuntimeException(
                    "Cannot rollback migration $version: the .sql file is missing from the migrations folder. Restore it before rolling back."
                );
            }
            if (!$mig['downFile']) {
                throw new RuntimeException(
                    sprintf(
                        "Cannot rollback migration %04d_%s: no .down.sql file. Create one or manually edit schema_migrations to skip it.",
                        $mig['version'], $mig['name']
                    )
                );
            }
            $this->revertMigration($mig);
            $rolledBack[] = sprintf('%04d_%s', $mig['version'], $mig['name']);
            $count++;
        }
        return $rolledBack;
    }

    // ─── Internos ──────────────────────────────────────────────────────

    private function applyMigration(array $mig): void
    {
        $sql = $this->preprocess(file_get_contents($mig['file']));
        $statements = $this->splitStatements($sql);
        // En SQLite el INSERT a schema_migrations queda dentro de la
        // misma transacción que runStatements para evitar la ventana
        // "aplicada pero no registrada" si crashea PHP entre los dos.
        // (En MySQL la DDL es autocommit, no hay nada que podamos hacer.)
        $registerSql = "INSERT INTO schema_migrations (version, name) VALUES (?, ?)";
        $registerParams = [$mig['version'], $mig['name']];
        $this->runStatements($statements, fn() => $this->db->execute($registerSql, $registerParams));
    }

    private function revertMigration(array $mig): void
    {
        $sql = $this->preprocess(file_get_contents($mig['downFile']));
        $statements = $this->splitStatements($sql);
        $this->runStatements($statements);
        $this->db->execute(
            "DELETE FROM schema_migrations WHERE version = ?",
            [$mig['version']]
        );
    }

    /**
     * Ejecuta statements en transacción si el driver lo soporta para DDL.
     * MySQL hace autocommit en DDL así que cada CREATE/ALTER va por su
     * cuenta. SQLite sí soporta DDL transaccional y lo aprovechamos.
     */
    private function runStatements(array $statements, ?callable $extra = null): void
    {
        $pdo = $this->db->getPdo();
        if ($this->driver === 'sqlite') {
            $pdo->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    if (trim($stmt) === '') continue;
                    $pdo->exec($stmt);
                }
                // Hook opcional para que applyMigration registre la
                // migración aplicada dentro de la misma transacción —
                // sin esto había una ventana "aplicada pero no registrada"
                // si crashea PHP justo después del commit del schema.
                if ($extra) $extra();
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } else {
            // MySQL: autocommit en DDL. Sin protección transaccional
            // pero seguimos parando al primer error. Adicional:
            // emulamos `CREATE INDEX IF NOT EXISTS` (sintaxis MariaDB-only,
            // Oracle MySQL 5.7/8.0 lo rechaza con syntax error) consultando
            // information_schema antes de ejecutar.
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                if ($this->shouldSkipMysqlIndex($stmt)) continue;
                // Reescribir el CREATE INDEX IF NOT EXISTS a CREATE INDEX
                // simple — el "IF NOT EXISTS" ya lo simulamos arriba.
                $stmt = preg_replace(
                    '/^CREATE\s+(UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS\s+/i',
                    'CREATE $1INDEX ',
                    $stmt
                ) ?? $stmt;
                $pdo->exec($stmt);
            }
            // MySQL DDL es autocommit — no podemos meter el INSERT a
            // schema_migrations en la misma transacción que el schema.
            // Lo ejecutamos como statement final aparte.
            if ($extra) $extra();
        }
    }

    /**
     * Detecta `CREATE INDEX IF NOT EXISTS X ON Y(...)` y consulta
     * information_schema para saber si ya existe. Retorna true si hay que
     * skipearlo. MariaDB soporta la sintaxis nativa, pero Oracle MySQL no:
     * sin esta emulación, todo re-run de una migración tras fallo parcial
     * de DDL (MySQL no es transaccional) muere con "Duplicate key name".
     */
    private function shouldSkipMysqlIndex(string $stmt): bool
    {
        if (!preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS\s+(\S+)\s+ON\s+(\S+)\s*\(/i', $stmt, $m)) {
            return false;
        }
        $indexName = trim($m[1], '`"\'');
        $tableName = trim($m[2], '`"\'');
        try {
            $exists = $this->db->scalar(
                "SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                [$tableName, $indexName]
            );
            return ((int) $exists) > 0;
        } catch (Throwable $e) {
            // Si la consulta falla (perm raro), dejamos que el CREATE
            // intente — peor caso re-lanza duplicado y la migración para,
            // pero al menos no enmascaramos un error real.
            return false;
        }
    }

    /**
     * Pre-procesa el SQL del archivo:
     *   1. Quita bloques condicionales del driver no activo.
     *   2. Reemplaza placeholders cross-driver.
     */
    public function preprocess(string $sql): string
    {
        // 1. Bloques condicionales --{mysql} ... --{/mysql}
        foreach (['mysql', 'sqlite'] as $tag) {
            $pattern = '/--\{' . $tag . '\}(.*?)--\{\/' . $tag . '\}/s';
            $sql = preg_replace_callback($pattern, function ($m) use ($tag) {
                return $this->driver === $tag ? $m[1] : '';
            }, $sql) ?? $sql;
        }

        // 2. Placeholders. Para evitar dependencias circulares con Dialect
        //    los mantenemos como tabla local; las reglas son simples y
        //    raramente cambian.
        $replacements = $this->driver === 'mysql'
            ? [
                '{{NOW}}'           => 'CURRENT_TIMESTAMP',
                // BIGINT (signed) para que coincida con las columnas FK que
                // referencian este PK; UNSIGNED rompía MySQL FK constraint 1005.
                '{{AUTO_PK}}'       => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
                '{{BOOL}}'          => 'TINYINT(1)',
                '{{JSON}}'          => 'JSON',
                '{{INT}}'           => 'INT',
                '{{BIGINT}}'        => 'BIGINT',
                '{{TEXT_LONG}}'     => 'MEDIUMTEXT',
                '{{TABLE_OPTIONS}}' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ]
            : [
                '{{NOW}}'           => "(datetime('now'))",
                '{{AUTO_PK}}'       => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                '{{BOOL}}'          => 'INTEGER',
                '{{JSON}}'          => 'TEXT',
                '{{INT}}'           => 'INTEGER',
                '{{BIGINT}}'        => 'INTEGER',
                '{{TEXT_LONG}}'     => 'TEXT',
                '{{TABLE_OPTIONS}}' => '',
            ];
        return strtr($sql, $replacements);
    }

    /**
     * Parte un SQL en statements por ';'. Quita comentarios de línea
     * completa para no confundir el splitter.
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim(rtrim(trim($p), ';'));
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }
}
