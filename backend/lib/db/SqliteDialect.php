<?php
require_once __DIR__ . '/Dialect.php';

/**
 * Dialect SQLite. Mantiene la compatibilidad con la app actual — la mayoría
 * de la lógica histórica de Database.php asumía SQLite, así que las
 * formulaciones aquí coinciden con lo que ya estaba funcionando.
 */
class SqliteDialect implements Dialect
{
    public function name(): string { return 'sqlite'; }

    public function now(): string { return "datetime('now')"; }

    public function upsert(string $table, array $columns, array $uniqueKeys, array $updateColumns): string
    {
        $cols = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO $table ($cols) VALUES ($placeholders)";

        if (empty($uniqueKeys)) return $sql;

        $conflict = implode(', ', $uniqueKeys);
        if (empty($updateColumns)) {
            return $sql . " ON CONFLICT($conflict) DO NOTHING";
        }

        $sets = array_map(fn($c) => "$c = excluded.$c", $updateColumns);
        return $sql . " ON CONFLICT($conflict) DO UPDATE SET " . implode(', ', $sets);
    }

    public function boolType(): string { return 'INTEGER NOT NULL DEFAULT 0'; }

    public function jsonType(): string { return 'TEXT'; }

    public function autoIncrementPk(): string { return 'INTEGER PRIMARY KEY AUTOINCREMENT'; }

    public function enforceForeignKeys(): string { return 'PRAGMA foreign_keys = ON'; }

    public function limit(int $limit, int $offset = 0): string
    {
        return $offset > 0 ? "LIMIT $limit OFFSET $offset" : "LIMIT $limit";
    }
}
