<?php
/**
 * Wrapper de SQLite con PDO
 * Singleton para mantener una sola conexión durante la ejecución
 */

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dbPath = $this->getDatabasePath();
        $dbDir = dirname($dbPath);

        // Crear directorio si no existe
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO("sqlite:$dbPath", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Optimizaciones de SQLite
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
    }

    /**
     * Obtiene la instancia singleton
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna la ruta al archivo de base de datos
     */
    private function getDatabasePath(): string {
        // Intentar poner la DB fuera de public_html
        $outsidePath = dirname(__DIR__, 2) . '/imagina_audit_data/audit.db';
        $outsideDir = dirname($outsidePath);

        if (is_dir($outsideDir) || @mkdir($outsideDir, 0755, true)) {
            return $outsidePath;
        }

        // Fallback: dentro de la carpeta database (protegida por .htaccess)
        return dirname(__DIR__) . '/database/audit.db';
    }

    /**
     * Ejecuta una query preparada y retorna los resultados
     */
    public function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta una query preparada y retorna una sola fila
     */
    public function queryOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Ejecuta una query preparada (INSERT, UPDATE, DELETE) y retorna filas afectadas
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Retorna el último ID insertado
     */
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    /**
     * Obtiene un valor escalar
     */
    public function scalar(string $sql, array $params = []): mixed {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Ejecuta el schema SQL para crear las tablas
     */
    public function initSchema(): void {
        $schemaPath = dirname(__DIR__) . '/database/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $this->pdo->exec($sql);
        }
        $this->runMigrations();
    }

    /**
     * Migraciones defensive para bases ya instaladas. SQLite no soporta
     * ADD COLUMN IF NOT EXISTS, así que cada ALTER va en try/catch.
     * Se ejecuta tras initSchema cada bootstrap — los ALTERs ya aplicados
     * fallan silenciosamente.
     */
    private function runMigrations(): void {
        $migrations = [
            // Columna `is_pinned` añadida para proteger informes del borrado por retención
            "ALTER TABLE audits ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0",
        ];
        foreach ($migrations as $sql) {
            try { $this->pdo->exec($sql); } catch (Throwable $e) { /* columna ya existe */ }
        }
    }

    /**
     * Obtiene el objeto PDO directamente (para operaciones avanzadas)
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }

    // Prevenir clonación y deserialización
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception('No se puede deserializar un singleton');
    }
}
