<?php
require_once __DIR__ . '/Dialect.php';

/**
 * Dialect MySQL / MariaDB (5.7+ / 10.3+). Asumimos:
 *
 *   - InnoDB como engine por default (necesario para FKs reales).
 *   - utf8mb4 / utf8mb4_unicode_ci en charset/collation.
 *   - JSON nativo disponible (MySQL 5.7+ / MariaDB 10.2.7+).
 *
 * Para upsert preferimos `ON DUPLICATE KEY UPDATE` clásico. MySQL 8 soporta
 * `INSERT … AS new ON DUPLICATE KEY UPDATE col = new.col` pero rompe en
 * 5.7. Usamos `VALUES(col)` que funciona en ambos (deprecated en 8.0.20+
 * pero sigue operativo).
 */
class MysqlDialect implements Dialect
{
    public function name(): string { return 'mysql'; }

    public function now(): string { return 'CURRENT_TIMESTAMP'; }

    public function upsert(string $table, array $columns, array $uniqueKeys, array $updateColumns): string
    {
        $cols = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO `$table` ($cols) VALUES ($placeholders)";

        // MySQL no permite ON CONFLICT(...) — ignora qué columna disparó. Si
        // no hay updateColumns asumimos que el caller quiere "no hacer nada
        // en conflicto" y usamos INSERT IGNORE como atajo equivalente.
        if (empty($updateColumns)) {
            // El INSERT IGNORE evade duplicados sobre cualquier UNIQUE,
            // exactamente como ON CONFLICT DO NOTHING en SQLite.
            return "INSERT IGNORE INTO `$table` ($cols) VALUES ($placeholders)";
        }

        $sets = array_map(fn($c) => "`$c` = VALUES(`$c`)", $updateColumns);
        // Las claves únicas no se usan explícitamente — MySQL dispara el UPDATE
        // sobre cualquier UNIQUE/PK violado. El parámetro $uniqueKeys queda
        // documental, alineado con SQLite.
        unset($uniqueKeys);
        return $sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
    }

    public function boolType(): string { return 'TINYINT(1) NOT NULL DEFAULT 0'; }

    public function jsonType(): string { return 'JSON'; }

    public function autoIncrementPk(): string { return 'BIGINT AUTO_INCREMENT PRIMARY KEY'; }

    public function enforceForeignKeys(): string { return 'SET FOREIGN_KEY_CHECKS = 1'; }

    public function limit(int $limit, int $offset = 0): string
    {
        return $offset > 0 ? "LIMIT $limit OFFSET $offset" : "LIMIT $limit";
    }
}
