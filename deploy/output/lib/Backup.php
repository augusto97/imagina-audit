<?php
/**
 * Backup — produce/aplica dumps de la base de datos.
 *
 * Estrategia por driver:
 *
 *   SQLite:
 *     - Backup = copy del archivo .db (single-file, atomic-ish con WAL).
 *     - Restore = sobrescribir el archivo (apaga conexiones primero).
 *
 *   MySQL:
 *     - Backup: si shell_exec está habilitado, usa `mysqldump
 *       --single-transaction` (consistente, sin lock). Fallback: dump
 *       PHP nativo (SELECT * por tabla → INSERT statements).
 *     - Restore: si shell_exec, usa `mysql ... < file.sql`. Fallback:
 *       parsea statements y los ejecuta por PDO.
 *
 * Los archivos viven en backend/storage/backups/ con nombre
 * YYYYMMDD-HHMMSS-<driver>.sql.gz. Retención: las últimas N (env
 * BACKUP_RETENTION_COUNT, default 10) se conservan, las más viejas se
 * borran al crear una nueva.
 */
class Backup
{
    public static function dir(): string
    {
        $dir = dirname(__DIR__) . '/storage/backups';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    /** Listado de backups existentes, ordenados del más nuevo al más viejo. */
    public static function list(): array
    {
        $files = glob(self::dir() . '/*.sql*') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn($f) => [
            'filename' => basename($f),
            'sizeBytes' => filesize($f),
            'createdAt' => date('c', filemtime($f)),
        ], $files);
    }

    /**
     * Crea un backup del estado actual. Retorna el path absoluto del
     * archivo generado.
     */
    public static function create(Database $db): string
    {
        $driver = $db->driver();
        $stamp = date('Ymd-His');
        $filename = "$stamp-$driver.sql";
        $path = self::dir() . "/$filename";

        if ($driver === 'sqlite') {
            self::dumpSqlite($db, $path);
        } else {
            self::dumpMysql($db, $path);
        }

        // Comprimir con gzip si está disponible — reduce el archivo
        // mucho y los backups grandes no se llevan tanto disco.
        if (function_exists('gzopen')) {
            $gzPath = $path . '.gz';
            $in = fopen($path, 'rb');
            $out = gzopen($gzPath, 'wb6');
            if ($in && $out) {
                while (!feof($in)) gzwrite($out, fread($in, 8192));
                fclose($in); gzclose($out);
                @unlink($path);
                $path = $gzPath;
            }
        }

        self::rotate();
        return $path;
    }

    /**
     * Aplica un dump al estado actual. El archivo puede estar comprimido
     * (.gz) o no. Atención: opera sobre la DB activa, los datos previos
     * pueden perderse — el caller decide si confirma antes.
     */
    public static function restore(Database $db, string $path): array
    {
        if (!is_file($path)) throw new RuntimeException("Backup not found: $path");
        $driver = $db->driver();

        $sqlPath = $path;
        $decompressed = null;
        if (str_ends_with($path, '.gz')) {
            $decompressed = tempnam(sys_get_temp_dir(), 'imagina_restore_');
            $in = gzopen($path, 'rb');
            $out = fopen($decompressed, 'wb');
            if (!$in || !$out) throw new RuntimeException('Failed to decompress backup');
            while (!gzeof($in)) fwrite($out, gzread($in, 8192));
            fclose($out); gzclose($in);
            $sqlPath = $decompressed;
        }

        try {
            if ($driver === 'sqlite') {
                return self::restoreSqlite($db, $sqlPath);
            }
            return self::restoreMysql($db, $sqlPath);
        } finally {
            if ($decompressed && is_file($decompressed)) @unlink($decompressed);
        }
    }

    public static function delete(string $filename): bool
    {
        $path = self::dir() . '/' . basename($filename);
        if (!is_file($path)) return false;
        return @unlink($path);
    }

    /** Rotación: mantener solo los N más recientes. */
    public static function rotate(): void
    {
        $keep = function_exists('env') ? (int) env('BACKUP_RETENTION_COUNT', '10') : 10;
        if ($keep <= 0) return;
        $files = glob(self::dir() . '/*.sql*') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    // ─── SQLite ───────────────────────────────────────────────────────

    /**
     * Dump SQLite a SQL portable. Usa `.dump` style: cada tabla con su
     * CREATE + INSERTs. Así el restore funciona en cualquier driver,
     * no solo SQLite-a-SQLite.
     */
    private static function dumpSqlite(Database $db, string $path): void
    {
        $f = fopen($path, 'w');
        if (!$f) throw new RuntimeException("Cannot write to $path");
        fwrite($f, "-- Imagina Audit backup\n");
        fwrite($f, "-- driver: sqlite\n");
        fwrite($f, "-- created: " . date('c') . "\n\n");

        $tables = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $tbl) {
            $name = $tbl['name'];
            fwrite($f, "-- ─── $name ───\n");
            fwrite($f, "DROP TABLE IF EXISTS `$name`;\n");
            fwrite($f, $tbl['sql'] . ";\n");
            // Rows
            $rows = $db->query("SELECT * FROM `$name`");
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . str_replace("'", "''", (string) $v) . "'", array_values($row));
                fwrite($f, "INSERT INTO `$name` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($f, "\n");
        }
        fclose($f);
    }

    private static function restoreSqlite(Database $db, string $sqlPath): array
    {
        $pdo = $db->getPdo();
        $sql = file_get_contents($sqlPath);
        if ($sql === false) throw new RuntimeException("Cannot read $sqlPath");
        // Quitar comentarios de línea completa
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*\n/', $sql) ?: [];
        $executed = 0;
        foreach ($statements as $stmt) {
            $stmt = trim(rtrim(trim($stmt), ';'));
            if ($stmt === '') continue;
            $pdo->exec($stmt);
            $executed++;
        }
        return ['statementsExecuted' => $executed];
    }

    // ─── MySQL ────────────────────────────────────────────────────────

    private static function dumpMysql(Database $db, string $path): void
    {
        if (self::canShellExec()) {
            $host = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
            $port = function_exists('env') ? env('DB_PORT', '3306') : '3306';
            $name = function_exists('env') ? env('DB_NAME', '') : '';
            $user = function_exists('env') ? env('DB_USER', '') : '';
            $pass = function_exists('env') ? env('DB_PASSWORD', '') : '';
            $cmd = sprintf(
                "mysqldump --single-transaction --quick --no-tablespaces -h%s -P%s -u%s %s %s > %s 2>&1",
                escapeshellarg($host), escapeshellarg($port), escapeshellarg($user),
                $pass !== '' ? '-p' . escapeshellarg($pass) : '',
                escapeshellarg($name), escapeshellarg($path)
            );
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);
            if ($code === 0 && is_file($path) && filesize($path) > 0) {
                return; // dump nativo OK
            }
            // Caer al fallback PHP
        }
        self::dumpMysqlPhp($db, $path);
    }

    /**
     * Fallback nativo PHP — más lento pero funciona en cualquier hosting,
     * incluso sin permisos para shell_exec.
     */
    private static function dumpMysqlPhp(Database $db, string $path): void
    {
        $f = fopen($path, 'w');
        if (!$f) throw new RuntimeException("Cannot write to $path");
        fwrite($f, "-- Imagina Audit backup\n-- driver: mysql\n-- created: " . date('c') . "\n");
        fwrite($f, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $db->query("SHOW TABLES");
        foreach ($tables as $row) {
            $name = reset($row);
            $createRow = $db->queryOne("SHOW CREATE TABLE `$name`");
            $create = $createRow['Create Table'] ?? '';
            fwrite($f, "DROP TABLE IF EXISTS `$name`;\n");
            fwrite($f, "$create;\n");
            $rows = $db->query("SELECT * FROM `$name`");
            foreach ($rows as $r) {
                $cols = array_keys($r);
                $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'", array_values($r));
                fwrite($f, "INSERT INTO `$name` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($f, "\n");
        }
        fwrite($f, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($f);
    }

    private static function restoreMysql(Database $db, string $sqlPath): array
    {
        $pdo = $db->getPdo();
        $sql = file_get_contents($sqlPath);
        if ($sql === false) throw new RuntimeException("Cannot read $sqlPath");
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*\n/', $sql) ?: [];

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $executed = 0;
            foreach ($statements as $stmt) {
                $stmt = trim(rtrim(trim($stmt), ';'));
                if ($stmt === '') continue;
                $pdo->exec($stmt);
                $executed++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        return ['statementsExecuted' => $executed];
    }

    private static function canShellExec(): bool
    {
        if (!function_exists('exec')) return false;
        $disabled = explode(',', (string) ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        return !in_array('exec', $disabled, true);
    }
}
