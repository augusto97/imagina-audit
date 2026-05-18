<?php
/**
 * Dialect — interfaz para abstraer las diferencias de sintaxis SQL entre
 * los drivers que soporta la app (MySQL/MariaDB y SQLite).
 *
 * Cada implementación devuelve fragmentos SQL para operaciones que NO son
 * portables entre drivers (upsert, timestamps, autoincrement, JSON, etc.).
 * Vivimos sin ORM, así que el wrapper Database compone estas piezas con
 * los datos del caller y emite el SQL final.
 *
 * Regla de oro: nunca emitir SQL específico de un driver fuera de esta
 * carpeta. Si un nuevo caso aparece (ej. una función nueva de MySQL 8),
 * se agrega como método de Dialect.
 */
interface Dialect
{
    /** Identificador corto del driver: 'mysql' | 'sqlite'. */
    public function name(): string;

    /**
     * SQL para "fecha/hora actual del servidor". MySQL usa CURRENT_TIMESTAMP,
     * SQLite usa datetime('now'). El wrapper Database expone now() como
     * helper portable encima de esto.
     */
    public function now(): string;

    /**
     * Construye un SQL de upsert (INSERT … ON CONFLICT/DUPLICATE KEY UPDATE).
     *
     * @param string $table            Nombre de tabla (sin escape — confiable).
     * @param array<string> $columns   Columnas a insertar, en orden.
     * @param array<string> $uniqueKeys Columnas únicas que dispararán el
     *                                  UPDATE en caso de conflicto.
     * @param array<string> $updateColumns Columnas a actualizar en el caso de
     *                                  conflicto. Si vacío, ON CONFLICT DO NOTHING.
     * @return string SQL listo con placeholders ? en VALUES, y placeholders
     *                separados para los SET (excluded.col en SQLite, VALUES(col)
     *                en MySQL).
     */
    public function upsert(string $table, array $columns, array $uniqueKeys, array $updateColumns): string;

    /**
     * Cómo se declara una columna boolean. MySQL: TINYINT(1). SQLite: INTEGER.
     * Para INSERT/UPDATE pasamos siempre 0/1.
     */
    public function boolType(): string;

    /**
     * Cómo se declara una columna JSON. MySQL 5.7+: JSON nativo. SQLite: TEXT.
     */
    public function jsonType(): string;

    /**
     * Cómo se declara un PK auto-incrementable. MySQL: BIGINT AUTO_INCREMENT
     * PRIMARY KEY. SQLite: INTEGER PRIMARY KEY AUTOINCREMENT.
     */
    public function autoIncrementPk(): string;

    /**
     * SQL para activar la verificación de foreign keys en la sesión actual.
     * MySQL: SET FOREIGN_KEY_CHECKS=1. SQLite: PRAGMA foreign_keys=ON.
     * El wrapper Database lo ejecuta al construir la conexión.
     */
    public function enforceForeignKeys(): string;

    /**
     * Cláusula LIMIT N OFFSET M. Ambos drivers la soportan idéntica pero
     * dejamos el hook por si futuro Dialect (SQL Server) la necesite distinta.
     */
    public function limit(int $limit, int $offset = 0): string;
}
