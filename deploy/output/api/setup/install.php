<?php
/**
 * POST /api/setup/install — Install completo de la app.
 *
 * Acepta credenciales DB + datos del admin inicial, y en una sola
 * llamada hace lo necesario para dejar la app lista para usar:
 *
 *   1. Valida los inputs.
 *   2. Re-testea la conexión DB (defense-in-depth, ya se debió testear
 *      antes con /setup/test-db.php).
 *   3. Persiste las credenciales en .env (via EnvWriter).
 *   4. Reset del singleton Database — la próxima conexión usará el
 *      driver recién configurado.
 *   5. Corre el Migrator (up) — crea schema_migrations y aplica todas
 *      las migraciones pendientes (incluido 0001_initial.sql).
 *   6. Crea el primer admin: guarda password_hash + email en settings.
 *   7. Crea el flag data/.installed con metadata.
 *
 * Solo se ejecuta si la app NO está instalada. Tras el primer success,
 * devuelve 403 a llamadas subsecuentes para evitar re-instalaciones.
 *
 * Body JSON:
 *   {
 *     driver: 'sqlite'|'mysql',
 *     host?, port?, name?, user?, password?, charset?,  // si driver=mysql
 *     sqlitePath?,                                       // si driver=sqlite
 *     adminEmail: string,
 *     adminPassword: string,                             // min 10 chars
 *     adminName?: string
 *   }
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$installFlag = dirname(__DIR__, 2) . '/data/.installed';
if (is_file($installFlag)) {
    Response::error('Setup already completed', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$body = Response::getJsonBody();
$driver = strtolower((string) ($body['driver'] ?? ''));
$adminEmail = trim((string) ($body['adminEmail'] ?? ''));
$adminPassword = (string) ($body['adminPassword'] ?? '');
$adminName = trim((string) ($body['adminName'] ?? ''));

if (!in_array($driver, ['sqlite', 'mysql'], true)) {
    Response::error('Invalid driver', 400);
}
if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    Response::error('Invalid admin email', 400);
}
if (strlen($adminPassword) < 10) {
    Response::error('Admin password must be at least 10 characters', 400);
}

// ─── 1. Test conexión antes de tocar nada ───────────────────────────
try {
    if ($driver === 'sqlite') {
        $path = trim((string) ($body['sqlitePath'] ?? ''));
        if ($path === '') $path = dirname(__DIR__, 3) . '/imagina_audit_data/audit.db';
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            Response::error("Cannot create SQLite directory: $dir", 400);
        }
        new PDO("sqlite:$path");
    } else {
        $host = (string) ($body['host'] ?? 'localhost');
        $port = (string) ($body['port'] ?? '3306');
        $name = (string) ($body['name'] ?? '');
        $user = (string) ($body['user'] ?? '');
        $pass = (string) ($body['password'] ?? '');
        $charset = (string) ($body['charset'] ?? 'utf8mb4');
        if ($name === '' || $user === '') {
            Response::error('DB name and user are required for MySQL', 400);
        }
        new PDO("mysql:host=$host;port=$port;dbname=$name;charset=$charset", $user, $pass, [
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
} catch (PDOException $e) {
    Response::error('DB connection failed: ' . $e->getMessage(), 400);
}

// ─── 2. Escribir .env ────────────────────────────────────────────────
$envUpdates = ['DB_DRIVER' => $driver];
if ($driver === 'mysql') {
    $envUpdates['DB_HOST'] = (string) ($body['host'] ?? 'localhost');
    $envUpdates['DB_PORT'] = (string) ($body['port'] ?? '3306');
    $envUpdates['DB_NAME'] = (string) ($body['name'] ?? '');
    $envUpdates['DB_USER'] = (string) ($body['user'] ?? '');
    $envUpdates['DB_PASSWORD'] = (string) ($body['password'] ?? '');
    $envUpdates['DB_CHARSET'] = (string) ($body['charset'] ?? 'utf8mb4');
} else {
    $sqlitePath = trim((string) ($body['sqlitePath'] ?? ''));
    if ($sqlitePath !== '') $envUpdates['DB_SQLITE_PATH'] = $sqlitePath;
}

if (!EnvWriter::update($envUpdates)) {
    Response::error('Could not write .env. Check file permissions on backend/.env', 500);
}

// ─── 3. Reset singleton + reload env in-process ─────────────────────
// El singleton Database podría haberse construido ya con el driver previo.
// Hay que matarlo para que la próxima getInstance() lea el .env actualizado.
Database::reset();
// Re-pisar las vars en runtime para que `env()` las vea sin reload del .env.
foreach ($envUpdates as $k => $v) {
    $_ENV[$k] = $v;
    putenv("$k=$v");
}

// ─── 4. Correr migrator ──────────────────────────────────────────────
try {
    $db = Database::getInstance();
    $migrator = new Migrator($db);
    $applied = $migrator->up();
} catch (Throwable $e) {
    Logger::error('Setup install: migrator failed - ' . $e->getMessage());
    Response::error('Migrations failed: ' . $e->getMessage(), 500);
}

// ─── 5. Crear admin (password hash en settings) ──────────────────────
try {
    $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $db = Database::getInstance();
    $db->upsert(
        'settings',
        ['key' => 'admin_password_hash', 'value' => $hash, 'updated_at' => date('Y-m-d H:i:s')],
        ['key'],
        ['value', 'updated_at']
    );
    $db->upsert(
        'settings',
        ['key' => 'admin_email', 'value' => $adminEmail, 'updated_at' => date('Y-m-d H:i:s')],
        ['key'],
        ['value', 'updated_at']
    );
    if ($adminName !== '') {
        $db->upsert(
            'settings',
            ['key' => 'admin_name', 'value' => $adminName, 'updated_at' => date('Y-m-d H:i:s')],
            ['key'],
            ['value', 'updated_at']
        );
    }
} catch (Throwable $e) {
    Logger::error('Setup install: admin save failed - ' . $e->getMessage());
    Response::error('Could not save admin credentials: ' . $e->getMessage(), 500);
}

// ─── 6. Flag installed ──────────────────────────────────────────────
@mkdir(dirname($installFlag), 0755, true);
$payload = json_encode([
    'installedAt' => date('c'),
    'driver' => $driver,
    'migrationsApplied' => $applied,
    'version' => '2.2.2',
], JSON_PRETTY_PRINT);
file_put_contents($installFlag, $payload);
@chmod($installFlag, 0600);

Logger::info('Setup completed — driver=' . $driver . ', migrations applied=' . count($applied));

Response::success([
    'ok' => true,
    'driver' => $driver,
    'migrationsApplied' => count($applied),
    'redirect' => '/admin/login',
]);
