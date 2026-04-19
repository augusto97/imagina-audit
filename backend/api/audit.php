<?php
/**
 * POST /api/audit — Arranca una auditoría.
 *
 * Modelo de ejecución (Pingdom-style sin cola):
 *
 * 1. Se valida URL, rate limit y se consulta cache.
 * 2. Si hay cache <24h y no se pidió forceRefresh → respuesta 200 con el
 *    `AuditResult` completo (camino rápido, compatible con flujo legacy).
 * 3. Si no hay cache → se reserva un `auditId`, se responde 202 con
 *    `{ auditId, queued: false }` y se cierra la conexión HTTP con
 *    `fastcgi_finish_request()` (PHP-FPM) o `ignore_user_abort(true)` +
 *    flush (Apache mod_php).
 * 4. El script sigue ejecutando el audit en background, reportando progreso
 *    vía `AuditProgress`. El frontend hace polling a
 *    `GET /api/scan-progress.php?id=<auditId>`.
 * 5. Al terminar, guarda el resultado en la tabla `audits` y marca el
 *    progreso como `completed`. El frontend detecta el estado y navega a
 *    `/results/:auditId` (que consulta `audit-status.php`).
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

set_time_limit(120);
ini_set('memory_limit', '256M');

// Cerrar el lock de sesión ANTES del scan para no bloquear otras peticiones
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$body = Response::getJsonBody();

// Validar URL
$url = trim($body['url'] ?? '');
if (empty($url)) {
    Response::error('La URL es obligatoria.');
}
try {
    $url = UrlValidator::validate($url);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage());
}

$domain = UrlValidator::extractDomain($url);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Rate limiting (no aplica si estás logueado como admin)
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'rate_limit_max_per_hour'");
    if ($row && is_numeric($row['value'])) $maxPerHour = (int) $row['value'];
} catch (Throwable $e) { /* usar valor de .env */ }
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

// Cache: verificar si ya se escaneó esta URL recientemente
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

            // Si hay nuevos datos de lead, actualizar el registro existente
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

            // Camino rápido: resultado cacheado listo. El cliente detecta `cached: true`.
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

// Sin cache: arrancamos audit en background
$auditId = AuditOrchestrator::generateUuid();

// Inicializar progreso para que el polling del frontend tenga algo que leer
AuditProgress::update($auditId, [
    'status' => 'running',
    'currentStep' => 'init',
    'completedSteps' => 0,
    'totalSteps' => 12,
    'startedAt' => time(),
]);

// Responder al cliente AHORA (202 Accepted) antes de arrancar el scan
$responseBody = json_encode([
    'success' => true,
    'data' => [
        'cached' => false,
        'auditId' => $auditId,
        'queued' => false,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

http_response_code(202);
header('Content-Type: application/json; charset=utf-8');
header('Content-Length: ' . strlen($responseBody));
header('Connection: close');
echo $responseBody;

// Cerrar la conexión HTTP pero seguir ejecutando
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // Fallback para Apache mod_php
    ignore_user_abort(true);
    @ob_end_flush();
    @flush();
}

// A partir de aquí el cliente ya recibió la respuesta y está haciendo polling.
// Ejecutamos el audit en "background".
try {
    $orchestrator = new AuditOrchestrator($url, [
        'leadName' => $body['leadName'] ?? '',
        'leadEmail' => $body['leadEmail'] ?? '',
        'leadWhatsapp' => $body['leadWhatsapp'] ?? '',
        'leadCompany' => $body['leadCompany'] ?? '',
    ], null, $auditId);

    $result = $orchestrator->run();
} catch (RuntimeException $e) {
    Logger::warning('Audit background falló (validación): ' . $e->getMessage());
    AuditProgress::failed($auditId, $e->getMessage());
    exit;
} catch (Throwable $e) {
    Logger::error('Audit background error: ' . $e->getMessage(), ['url' => $url]);
    AuditProgress::failed($auditId, 'Ocurrió un error al analizar el sitio. Intenta nuevamente.');
    exit;
}

// Guardar en base de datos
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
            $result['id'],
            $result['url'],
            $result['domain'],
            $body['leadName'] ?? null,
            $body['leadEmail'] ?? null,
            $body['leadWhatsapp'] ?? null,
            $body['leadCompany'] ?? null,
            $result['globalScore'],
            $result['globalLevel'],
            $result['isWordPress'] ? 1 : 0,
            $result['scanDurationMs'],
            $resultJson,
            $waterfallJson,
            $ip,
        ]
    );
} catch (Throwable $e) {
    Logger::error('Error guardando auditoría: ' . $e->getMessage());
    AuditProgress::failed($auditId, 'Error guardando el resultado. Intenta nuevamente.');
    exit;
}

// Notificar al admin si el lead tiene datos de contacto
try {
    $leadEmail = trim($body['leadEmail'] ?? '');
    $leadWhatsapp = trim($body['leadWhatsapp'] ?? '');

    if ($leadEmail || $leadWhatsapp) {
        $db = Database::getInstance();
        $notifRow = $db->queryOne("SELECT value FROM settings WHERE key = 'lead_notification_email'");
        $notifEmail = $notifRow['value'] ?? '';

        if (!empty($notifEmail) && filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
            $score = $result['globalScore'];
            $level = $result['globalLevel'];
            $leadName = trim($body['leadName'] ?? '') ?: 'No proporcionado';
            $leadCompany = trim($body['leadCompany'] ?? '') ?: 'No proporcionado';
            $subject = "Nuevo lead: {$result['domain']} (Score: $score/100)";
            $emailBody = "Nuevo lead capturado en Imagina Audit\n\n"
                . "Sitio: {$result['url']}\n"
                . "Score: $score/100 ($level)\n\n"
                . "Datos de contacto:\n"
                . "Nombre: $leadName\n"
                . "Email: " . ($leadEmail ?: 'No proporcionado') . "\n"
                . "WhatsApp: " . ($leadWhatsapp ?: 'No proporcionado') . "\n"
                . "Empresa: $leadCompany\n\n"
                . "Fecha: " . date('d/m/Y H:i') . "\n";
            Mailer::send($notifEmail, $subject, $emailBody);
        }
    }
} catch (Throwable $e) {
    Logger::warning('Error enviando notificación de lead: ' . $e->getMessage());
}

// Marcar progreso como completado — el frontend lo detectará en el próximo poll
AuditProgress::completed($auditId, 12);
