<?php
/**
 * POST /api/setup/test-db — Prueba conexión DB sin guardar.
 *
 * Body JSON: { driver: 'mysql'|'sqlite', host?, port?, name?, user?, password?, sqlitePath? }
 *
 * Crea una conexión PDO ad-hoc con los parámetros recibidos. Si funciona,
 * retorna OK + versión del servidor. Si falla, retorna el error de cara
 * al admin (saneado para no filtrar passwords, etc.).
 *
 * Solo accesible mientras `data/.installed` NO exista. Una vez instalada
 * la app, este endpoint devuelve 403 — el admin debe usar el panel
 * normal para reconfigurar credenciales (P7.6 hardening).
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

if (!in_array($driver, ['mysql', 'sqlite'], true)) {
    Response::error('Invalid driver. Use mysql or sqlite.', 400);
}

try {
    if ($driver === 'sqlite') {
        $path = trim((string) ($body['sqlitePath'] ?? ''));
        if ($path === '') $path = dirname(__DIR__, 3) . '/imagina_audit_data/audit.db';
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            Response::error("Cannot create SQLite directory: $dir", 400);
        }
        $pdo = new PDO("sqlite:$path", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
        Response::success([
            'ok' => true,
            'driver' => 'sqlite',
            'version' => 'SQLite ' . $version,
            'path' => $path,
        ]);
    } else {
        $host = (string) ($body['host'] ?? 'localhost');
        $port = (string) ($body['port'] ?? '3306');
        $name = (string) ($body['name'] ?? '');
        $user = (string) ($body['user'] ?? '');
        $pass = (string) ($body['password'] ?? '');
        $charset = (string) ($body['charset'] ?? 'utf8mb4');
        if ($name === '' || $user === '') {
            Response::error('DB name and user are required for MySQL.', 400);
        }
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        Response::success([
            'ok' => true,
            'driver' => 'mysql',
            'version' => 'MySQL/MariaDB ' . $version,
            'host' => $host,
            'database' => $name,
        ]);
    }
} catch (PDOException $e) {
    // Mensaje user-friendly que no filtra credenciales.
    $msg = $e->getMessage();
    // Quitar el "SQLSTATE[HY000] [1045]" prefix
    $msg = preg_replace('/^SQLSTATE\[\w+\](?:\s*\[\d+\])?\s*/', '', $msg) ?? $msg;
    Response::error("Connection failed: $msg", 400);
}
