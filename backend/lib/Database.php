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
     * Inicializa el schema y aplica migraciones.
     *
     * Dos pasos clave:
     *
     * 1. `runMigrations()` PRIMERO — aplica ALTERs sobre tablas existentes
     *    (p.ej. `ADD COLUMN is_pinned`). Si la tabla no existe aún, el ALTER
     *    falla silenciosamente (try/catch) y se creará completa en el paso 2.
     *
     * 2. Ejecutamos schema.sql **statement por statement**, no en bloque. Si un
     *    CREATE INDEX referencia una columna que no existe (instalación vieja
     *    sin migrar), ese INDEX falla pero los siguientes siguen corriendo.
     *    Antes, un solo `pdo->exec($sql)` abortaba todo al primer error.
     */
    public function initSchema(): void {
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
        //    (p.ej. ALTER sobre tabla que solo existe tras el schema)
        $this->runMigrations();
    }

    /**
     * Parte un dump SQL en statements individuales, respetando strings.
     * Tolerante a strings con ';' internos (no los hay en nuestro schema
     * pero por si acaso).
     */
    private function splitSqlStatements(string $sql): array {
        // Quitar comentarios de línea completa
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        // Split por ';' + newline (preserva strings simples multi-línea)
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim(rtrim(trim($p), ';'));
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    /**
     * Migraciones defensive para bases ya instaladas. SQLite no soporta
     * ADD COLUMN IF NOT EXISTS, así que cada ALTER va en try/catch.
     * Se ejecuta en initSchema — los ALTERs ya aplicados fallan en silencio.
     */
    private function runMigrations(): void {
        $migrations = [
            // Columna `is_pinned` añadida para proteger informes del borrado por retención
            "ALTER TABLE audits ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0",
            // Columna `lang` añadida para cachear audits por idioma (P2 i18n)
            "ALTER TABLE audits ADD COLUMN lang TEXT NOT NULL DEFAULT 'en'",
            // Columna `user_id` añadida para asociar audits a cuentas (P4 users)
            "ALTER TABLE audits ADD COLUMN user_id INTEGER",
            // Columna `project_id` añadida para atar audits a proyectos (P5)
            "ALTER TABLE audits ADD COLUMN project_id INTEGER",
            // Columna `max_projects` en plans — cupo de proyectos por plan (0=ilimitado)
            "ALTER TABLE plans ADD COLUMN max_projects INTEGER NOT NULL DEFAULT 0",
            // Columna `is_deleted` para soft-delete desde el panel del user.
            // La cuota mensual cuenta filas soft-deleted también — borrar un
            // audit no libera un slot (el scan ya se ejecutó, consumió recursos).
            "ALTER TABLE audits ADD COLUMN is_deleted INTEGER NOT NULL DEFAULT 0",
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
