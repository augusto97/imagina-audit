<?php
/**
 * GET /api/admin/diagnostics
 *
 * Ejecuta el pipeline crítico del escaneo paso a paso y reporta qué
 * falla, sin tener que adivinar por correo electrónico. La idea es:
 * el admin abre /admin/diagnostics, copia el JSON resultante, me lo
 * pega y trabajamos sobre datos reales en vez de teorías.
 *
 * Cada paso devuelve `{name, status: ok|warn|fail|skip, ms, detail}`.
 * Nada lanza excepción hacia afuera — todo se captura para que el
 * reporte llegue completo aunque algo crashee a mitad.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$checks = [];

/**
 * Helper que ejecuta un check capturando excepciones y timing.
 */
$run = function (string $name, callable $fn) use (&$checks) {
    $t0 = microtime(true);
    try {
        $result = $fn();
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $checks[] = [
            'name' => $name,
            'status' => $result['status'] ?? 'ok',
            'ms' => $ms,
            'detail' => $result['detail'] ?? null,
        ];
    } catch (Throwable $e) {
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $checks[] = [
            'name' => $name,
            'status' => 'fail',
            'ms' => $ms,
            'detail' => [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ],
        ];
    }
};

// ─── 1. Versiones e extensiones PHP ─────────────────────────────────
$run('PHP versión y extensiones', function () {
    $required = ['curl', 'pdo', 'dom', 'json', 'openssl', 'mbstring'];
    $optional = ['pdo_mysql', 'pdo_sqlite', 'zip', 'fileinfo'];
    $missingRequired = [];
    $missingOptional = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) $missingRequired[] = $ext;
    }
    foreach ($optional as $ext) {
        if (!extension_loaded($ext)) $missingOptional[] = $ext;
    }
    return [
        'status' => empty($missingRequired) ? 'ok' : 'fail',
        'detail' => [
            'phpVersion' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'memoryLimit' => ini_get('memory_limit'),
            'maxExecutionTime' => ini_get('max_execution_time'),
            'missingRequired' => $missingRequired,
            'missingOptional' => $missingOptional,
        ],
    ];
});

// ─── 2. Funciones críticas habilitadas ───────────────────────────────
$run('Funciones críticas habilitadas', function () {
    $disabled = explode(',', (string) ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    $check = ['shell_exec', 'exec', 'popen', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'stream_socket_client'];
    $blocked = [];
    foreach ($check as $f) {
        if (in_array($f, $disabled, true) || !function_exists($f)) $blocked[] = $f;
    }
    $criticalBlocked = array_intersect($blocked, ['curl_exec', 'curl_multi_exec']);
    return [
        'status' => empty($criticalBlocked) ? (empty($blocked) ? 'ok' : 'warn') : 'fail',
        'detail' => [
            'blocked' => $blocked,
            'openBasedir' => ini_get('open_basedir') ?: 'no restriction',
            'note' => in_array('shell_exec', $blocked, true)
                ? 'shell_exec deshabilitado: el escaneo no puede arrancar via shell; depende del cron HTTP o del cron del sistema.'
                : null,
        ],
    ];
});

// ─── 3. Permisos de escritura ────────────────────────────────────────
$run('Permisos de escritura en data/cache/logs', function () {
    $paths = [
        'cache' => dirname(__DIR__, 2) . '/cache',
        'logs' => dirname(__DIR__, 2) . '/logs',
        'data' => dirname(__DIR__, 2) . '/data',
        'uploads' => dirname(__DIR__, 2) . '/uploads',
    ];
    $report = [];
    $anyFail = false;
    foreach ($paths as $k => $p) {
        if (!is_dir($p)) { $report[$k] = 'missing'; $anyFail = true; continue; }
        if (!is_writable($p)) { $report[$k] = 'not_writable'; $anyFail = true; continue; }
        // Intentar write+read+delete real
        $testFile = "$p/.diag_" . bin2hex(random_bytes(4));
        if (@file_put_contents($testFile, 'x') === false) {
            $report[$k] = 'write_failed'; $anyFail = true; continue;
        }
        $read = @file_get_contents($testFile);
        @unlink($testFile);
        $report[$k] = $read === 'x' ? 'ok' : 'read_back_mismatch';
        if ($read !== 'x') $anyFail = true;
    }
    return [
        'status' => $anyFail ? 'fail' : 'ok',
        'detail' => $report,
    ];
});

// ─── 4. Base de datos ────────────────────────────────────────────────
$run('Base de datos (conexión + driver)', function () {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    return [
        'status' => 'ok',
        'detail' => [
            'driver' => $driver,
            'serverVersion' => $serverVersion,
            'envDriver' => function_exists('env') ? env('DB_DRIVER', '') : '',
        ],
    ];
});

// ─── 5. Migraciones ──────────────────────────────────────────────────
$run('Migraciones', function () {
    $db = Database::getInstance();
    $migrator = new Migrator($db);
    $migrator->bootstrap();
    $status = $migrator->status();
    return [
        'status' => count($status['pending']) === 0 ? 'ok' : 'warn',
        'detail' => [
            'applied' => $status['totalApplied'],
            'pending' => count($status['pending']),
            'pendingList' => array_map(fn($m) => sprintf('%04d_%s', $m['version'], $m['name']), $status['pending']),
        ],
    ];
});

// ─── 6. AuditProgress write/read cycle ───────────────────────────────
$run('AuditProgress write → read', function () {
    $id = 'diag-' . bin2hex(random_bytes(4));
    AuditProgress::update($id, [
        'status' => 'running',
        'currentStep' => 'wordpress',
        'completedSteps' => 3,
        'totalSteps' => 12,
        'startedAt' => time(),
    ]);
    $back = AuditProgress::get($id);
    // Borrar el cache file de prueba
    try {
        $cache = new Cache();
        $cache->delete('progress_' . $id);
    } catch (Throwable $e) {}
    if (!$back) {
        return ['status' => 'fail', 'detail' => 'No se pudo leer el progreso recién escrito (¿cache dir no escribible o atomic write fallando?).'];
    }
    if (($back['progress'] ?? null) !== 25) {
        return ['status' => 'fail', 'detail' => "Progreso escrito mal: esperaba 25, leí " . json_encode($back['progress'])];
    }
    return [
        'status' => 'ok',
        'detail' => [
            'wroteProgress' => 25,
            'readBack' => $back['progress'] ?? null,
            'cacheDir' => dirname(__DIR__, 2) . '/cache',
        ],
    ];
});

// ─── 7. Cola: contadores actuales ────────────────────────────────────
$run('Estado actual de la cola', function () {
    return [
        'status' => 'ok',
        'detail' => [
            'queued' => QueueManager::queuedCount(),
            'running' => QueueManager::runningCount(),
            'maxConcurrent' => QueueManager::getMaxConcurrent(),
        ],
    ];
});

// ─── 8. Fetcher externo (probe a un host predecible) ─────────────────
$run('Fetcher → HTTP externo (example.com)', function () {
    $t0 = microtime(true);
    $r = Fetcher::get('https://example.com', 8, true, 0);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $status = ($r['statusCode'] ?? 0) > 0 ? 'ok' : 'fail';
    return [
        'status' => $status,
        'detail' => [
            'statusCode' => $r['statusCode'] ?? 0,
            'bodyLength' => strlen($r['body'] ?? ''),
            'httpVersion' => $r['httpVersion'] ?? '',
            'elapsedMs' => $ms,
        ],
    ];
});

// ─── 9. Self-kick HTTP (lo que falló en v2.3.x) ─────────────────────
$run('Self-kick HTTP a /cron/drain-queue.php', function () {
    $token = QueueManager::ensureCronToken();
    if ($token === '') {
        return ['status' => 'fail', 'detail' => 'ensureCronToken devolvió vacío'];
    }
    // Resolver URL base igual que kickDrain
    $base = '';
    try {
        $base = (string) Database::getInstance()->scalar("SELECT value FROM settings WHERE `key` = 'app_url'");
        $base = trim($base);
        if ($base !== '' && !preg_match('#^https?://#i', $base)) $base = '';
    } catch (Throwable $e) {}
    $usedSource = 'app_url setting';
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $base = rtrim("$scheme://$host", '/');
        $usedSource = isset($_SERVER['HTTP_HOST']) ? 'HTTP_HOST' : 'SERVER_NAME';
    }
    $url = "$base/cron/drain-queue.php?token=" . urlencode($token) . '&dryrun=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $detail = [
        'urlTried' => preg_replace('/token=[^&]+/', 'token=***', $url),
        'baseSource' => $usedSource,
        'statusCode' => $code,
        'curlError' => $err ?: null,
        'bodySnippet' => substr((string) $body, 0, 200),
    ];
    $status = ($code === 200 || $code === 403) ? 'ok' : 'fail';
    // 403 = el server respondió, solo que el token no validó.
    // En este check el token sí debería validar (ensureCronToken da el bueno).
    if ($code === 403) {
        $detail['note'] = 'Server respondió 403 — el HOST se alcanza, pero el token no validó. Verifica CRON_SECRET_TOKEN.';
        $status = 'warn';
    }
    if ($code === 0) {
        $detail['note'] = 'No se pudo alcanzar el host. Algunos hosts compartidos bloquean self-calls — configura el cron del sistema en /admin/queue.';
    }
    return ['status' => $status, 'detail' => $detail];
});

// ─── 10. Logs recientes (últimas N líneas) ──────────────────────────
$run('Logs recientes (errores)', function () {
    $logDir = dirname(__DIR__, 2) . '/logs';
    if (!is_dir($logDir)) {
        return ['status' => 'warn', 'detail' => 'Carpeta logs no existe'];
    }
    $files = glob($logDir . '/*.log');
    if (!$files) return ['status' => 'ok', 'detail' => 'No hay archivos de log todavía'];
    rsort($files);
    $latest = $files[0];
    $lines = file($latest, FILE_IGNORE_NEW_LINES);
    if (!$lines) return ['status' => 'ok', 'detail' => 'Log vacío'];
    $tail = array_slice($lines, -50);
    $errors = array_values(array_filter($tail, fn($l) => stripos($l, '[ERROR]') !== false || stripos($l, '[WARNING]') !== false));
    return [
        'status' => count($errors) > 0 ? 'warn' : 'ok',
        'detail' => [
            'file' => basename($latest),
            'tailCount' => count($tail),
            'errorsAndWarnings' => array_slice($errors, -20),
        ],
    ];
});

// ─── 11. Último audit_job (para ver dónde quedó si quedó) ───────────
$run('Último audit_job de la tabla', function () {
    $db = Database::getInstance();
    $row = $db->queryOne(
        "SELECT audit_id, url, status, attempts, started_at, completed_at, error_message, created_at
         FROM audit_jobs ORDER BY id DESC LIMIT 1"
    );
    if (!$row) return ['status' => 'ok', 'detail' => 'Sin audit_jobs en la tabla'];
    // Cuanto tiempo lleva en su estado actual
    $stuckSec = null;
    if ($row['status'] === 'running' && !empty($row['started_at'])) {
        $stuckSec = max(0, time() - strtotime($row['started_at']));
    } elseif ($row['status'] === 'queued' && !empty($row['created_at'])) {
        $stuckSec = max(0, time() - strtotime($row['created_at']));
    }
    return [
        'status' => 'ok',
        'detail' => [
            'auditId' => substr($row['audit_id'], 0, 8) . '…',
            'urlHost' => parse_url($row['url'], PHP_URL_HOST),
            'status' => $row['status'],
            'attempts' => (int) $row['attempts'],
            'createdAt' => $row['created_at'],
            'startedAt' => $row['started_at'],
            'completedAt' => $row['completed_at'],
            'stuckSec' => $stuckSec,
            'errorMessage' => $row['error_message'],
        ],
    ];
});

// ─── Compilar resultado ──────────────────────────────────────────────
$summary = [
    'ok' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0,
];
foreach ($checks as $c) {
    $summary[$c['status']] = ($summary[$c['status']] ?? 0) + 1;
}

Response::success([
    'timestamp' => date('c'),
    'app' => [
        'version' => '2.3.3',
    ],
    'summary' => $summary,
    'checks' => $checks,
]);
