<?php
/**
 * POST /api/audit — Arranca una auditoría (con control de concurrencia).
 *
 * Flujo:
 *   1. Valida URL, rate limit, consulta cache.
 *   2. Si hay cache <24h → 200 con el AuditResult (camino rápido, sin cola).
 *   3. Reserva un auditId.
 *   4. QueueManager::enqueueOrStart():
 *      - Si hay slot libre (jobs 'running' < audit_max_concurrent):
 *        marca 'running' y responde 202 con { queued: false, auditId }.
 *      - Si no: encola como 'queued' y responde 202 con
 *        { queued: true, auditId, position, totalInQueue }.
 *   5. Cierra HTTP con fastcgi_finish_request().
 *   6. Solo si obtuvo slot 'running': ejecuta el audit. Al terminar,
 *      llama a QueueManager::drain() para procesar los siguientes
 *      de la cola hasta que no queden slots o se acabe el tiempo PHP.
 *      Los requests 'queued' simplemente salen — serán procesados por
 *      el request que libere el slot.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

set_time_limit(180); // 3 min: suficiente para 1-3 audits consecutivos
ini_set('memory_limit', '256M');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$body = Response::getJsonBody();
$url = trim($body['url'] ?? '');
if (empty($url)) {
    Response::error('La URL es obligatoria.');
}
try {
    $url = UrlValidator::validate($url);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage());
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Rate limiting
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'rate_limit_max_per_hour'");
    if ($row && is_numeric($row['value'])) $maxPerHour = (int) $row['value'];
} catch (Throwable $e) {}
$isAdmin = Auth::checkAuth();

try {
    $db = Database::getInstance();
    $db->execute("DELETE FROM rate_limits WHERE request_time < datetime('now', '-1 hour')");

    if (!$isAdmin) {
        $count = (int) $db->scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = 'audit'",
            [$ip]
        );
        if ($count >= $maxPerHour) {
            Response::error('Has alcanzado el límite de auditorías por hora. Intenta más tarde.', 429);
        }
    }

    $db->execute("INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, 'audit')", [$ip]);
} catch (Throwable $e) {
    Logger::error('Error en rate limiting: ' . $e->getMessage());
}

// Cache lookup
$forceRefresh = !empty($body['forceRefresh']);
$cacheTtl = (int) env('CACHE_TTL_SECONDS', '86400');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'cache_ttl_seconds'");
    if ($row && is_numeric($row['value'])) $cacheTtl = (int) $row['value'];
} catch (Throwable $e) {}

try {
    if (!$forceRefresh) {
        $db = Database::getInstance();
        $cached = $db->queryOne(
            "SELECT * FROM audits WHERE url = ? AND created_at > datetime('now', '-' || ? || ' seconds') ORDER BY created_at DESC LIMIT 1",
            [$url, $cacheTtl]
        );

        if ($cached) {
            $result = JsonStore::decode($cached['result_json']);

            $leadName = trim($body['leadName'] ?? '');
            $leadEmail = trim($body['leadEmail'] ?? '');
            $leadWhatsapp = trim($body['leadWhatsapp'] ?? '');
            $leadCompany = trim($body['leadCompany'] ?? '');
            if ($leadName || $leadEmail || $leadWhatsapp || $leadCompany) {
                $db->execute(
                    "UPDATE audits SET lead_name = COALESCE(NULLIF(?, ''), lead_name), lead_email = COALESCE(NULLIF(?, ''), lead_email), lead_whatsapp = COALESCE(NULLIF(?, ''), lead_whatsapp), lead_company = COALESCE(NULLIF(?, ''), lead_company) WHERE id = ?",
                    [$leadName, $leadEmail, $leadWhatsapp, $leadCompany, $cached['id']]
                );
            }

            Response::success([
                'cached' => true,
                'auditId' => $cached['id'],
                'result' => $result,
            ]);
        }
    }
} catch (Throwable $e) {
    Logger::error('Error consultando cache: ' . $e->getMessage());
}

// Encolar o ejecutar directo
$auditId = AuditOrchestrator::generateUuid();
$leadData = [
    'leadName' => $body['leadName'] ?? '',
    'leadEmail' => $body['leadEmail'] ?? '',
    'leadWhatsapp' => $body['leadWhatsapp'] ?? '',
    'leadCompany' => $body['leadCompany'] ?? '',
];

$slot = QueueManager::enqueueOrStart($auditId, $url, $leadData, $ip);

if ($slot['status'] === 'running') {
    AuditProgress::update($auditId, [
        'status' => 'running',
        'currentStep' => 'init',
        'completedSteps' => 0,
        'totalSteps' => 12,
        'startedAt' => time(),
    ]);
} else {
    AuditProgress::queued($auditId, $slot['position'], QueueManager::queuedCount());
}

// Responder inmediato con auditId + posición en cola (si aplica)
$responseBody = json_encode([
    'success' => true,
    'data' => [
        'cached' => false,
        'auditId' => $auditId,
        'queued' => $slot['status'] === 'queued',
        'position' => $slot['position'],
        'totalInQueue' => QueueManager::queuedCount(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

http_response_code(202);
header('Content-Type: application/json; charset=utf-8');
header('Content-Length: ' . strlen($responseBody));
header('Connection: close');
echo $responseBody;

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    @ob_end_flush();
    @flush();
}

// ------------------------------------------------------------------
// A partir de aquí el cliente ya tiene la respuesta.
// Solo el request que obtuvo 'running' ejecuta el audit + drena la cola.
// Los requests 'queued' simplemente salen — serán procesados por otro worker.
// ------------------------------------------------------------------
if ($slot['status'] !== 'running') {
    exit;
}

try {
    $orchestrator = new AuditOrchestrator($url, $leadData, null, $auditId);
    $result = $orchestrator->run();
} catch (RuntimeException $e) {
    Logger::warning('Audit background falló (validación): ' . $e->getMessage());
    QueueManager::markFailed($auditId, $e->getMessage());
    AuditProgress::failed($auditId, $e->getMessage());
    exit;
} catch (Throwable $e) {
    Logger::error('Audit background error: ' . $e->getMessage(), ['url' => $url]);
    QueueManager::markFailed($auditId, 'Error interno');
    AuditProgress::failed($auditId, 'Ocurrió un error al analizar el sitio. Intenta nuevamente.');
    exit;
}

// Guardar el resultado de NUESTRO audit en la tabla audits
try {
    $db = Database::getInstance();
    $waterfallData = $result['waterfall'] ?? [];
    $extendedPerf = $result['extendedPerf'] ?? [];
    $resultForStorage = $result;
    unset($resultForStorage['waterfall'], $resultForStorage['extendedPerf']);
    $resultJson = JsonStore::encode($resultForStorage);
    $perfData = [
        'waterfall' => $waterfallData,
        'crux' => $extendedPerf['crux'] ?? null,
        'resourceBreakdown' => $extendedPerf['resourceBreakdown'] ?? [],
        'lighthouseAudits' => $extendedPerf['lighthouseAudits'] ?? [],
        'lcpElement' => $extendedPerf['lcpElement'] ?? null,
        'clsElements' => $extendedPerf['clsElements'] ?? [],
        'mainThreadWork' => $extendedPerf['mainThreadWork'] ?? [],
    ];
    $waterfallJson = JsonStore::encode($perfData);

    $db->execute(
        "INSERT INTO audits (id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, is_wordpress, scan_duration_ms, result_json, waterfall_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $result['id'], $result['url'], $result['domain'],
            $leadData['leadName'] ?: null, $leadData['leadEmail'] ?: null,
            $leadData['leadWhatsapp'] ?: null, $leadData['leadCompany'] ?: null,
            $result['globalScore'], $result['globalLevel'],
            $result['isWordPress'] ? 1 : 0, $result['scanDurationMs'],
            $resultJson, $waterfallJson, $ip,
        ]
    );
} catch (Throwable $e) {
    Logger::error('Error guardando auditoría: ' . $e->getMessage());
    QueueManager::markFailed($auditId, 'Error guardando');
    AuditProgress::failed($auditId, 'Error guardando el resultado. Intenta nuevamente.');
    exit;
}

// Notificar al admin por email si hay lead
try {
    $leadEmail = trim($leadData['leadEmail']);
    $leadWhatsapp = trim($leadData['leadWhatsapp']);
    if ($leadEmail || $leadWhatsapp) {
        $db = Database::getInstance();
        $notifRow = $db->queryOne("SELECT value FROM settings WHERE key = 'lead_notification_email'");
        $notifEmail = $notifRow['value'] ?? '';
        if (!empty($notifEmail) && filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
            $score = $result['globalScore'];
            $leadName = trim($leadData['leadName']) ?: 'No proporcionado';
            $leadCompany = trim($leadData['leadCompany']) ?: 'No proporcionado';
            $subject = "Nuevo lead: {$result['domain']} (Score: $score/100)";
            $emailBody = "Nuevo lead capturado en Imagina Audit\n\n"
                . "Sitio: {$result['url']}\nScore: $score/100 ({$result['globalLevel']})\n\n"
                . "Nombre: $leadName\nEmail: " . ($leadEmail ?: 'No proporcionado') . "\n"
                . "WhatsApp: " . ($leadWhatsapp ?: 'No proporcionado') . "\n"
                . "Empresa: $leadCompany\nFecha: " . date('d/m/Y H:i') . "\n";
            Mailer::send($notifEmail, $subject, $emailBody);
        }
    }
} catch (Throwable $e) {
    Logger::warning('Error enviando notificación de lead: ' . $e->getMessage());
}

QueueManager::markCompleted($auditId);
AuditProgress::completed($auditId, 12);

// Drenar cola: mientras haya jobs 'queued' y slots libres y tiempo PHP,
// procesamos los siguientes. Con esto la cola fluye sin necesidad de daemon.
// Dejamos 30s de margen para el cron del servidor.
$remainingTime = max(10, (int) ini_get('max_execution_time') - (time() - $_SERVER['REQUEST_TIME']) - 30);
QueueManager::drain($remainingTime);
