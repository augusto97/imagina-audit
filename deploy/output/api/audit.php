<?php
/**
 * POST /api/audit — Ejecuta una auditoría completa
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

// Aumentar tiempo de ejecución para el escaneo
set_time_limit(120);
ini_set('memory_limit', '256M');

// Liberar el lock de sesión para no bloquear otras peticiones durante el scan
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

// Rate limiting (no aplica si estás logueado como admin)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
// Leer límite de la DB primero, fallback a .env
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'rate_limit_max_per_hour'");
    if ($row && is_numeric($row['value'])) $maxPerHour = (int) $row['value'];
} catch (Throwable $e) { /* usar valor de .env */ }
$isAdmin = Auth::checkAuth();

try {
    $db = Database::getInstance();

    // Limpiar registros viejos (más de 1 hora)
    $db->execute(
        "DELETE FROM rate_limits WHERE request_time < datetime('now', '-1 hour')"
    );

    if (!$isAdmin) {
        // Contar peticiones de esta IP
        $count = (int) $db->scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = 'audit'",
            [$ip]
        );

        if ($count >= $maxPerHour) {
            Response::error('Has alcanzado el límite de auditorías por hora. Intenta más tarde.', 429);
        }
    }

    // Registrar esta petición
    $db->execute(
        "INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, 'audit')",
        [$ip]
    );
} catch (Throwable $e) {
    Logger::error('Error en rate limiting: ' . $e->getMessage());
    // Continuar sin rate limiting si falla
}

// Cache: verificar si ya se escaneó esta URL recientemente
// Si forceRefresh=true, saltar el cache y hacer un escaneo nuevo
$forceRefresh = !empty($body['forceRefresh']);
$cacheTtl = (int) env('CACHE_TTL_SECONDS', '86400');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'cache_ttl_seconds'");
    if ($row && is_numeric($row['value'])) $cacheTtl = (int) $row['value'];
} catch (Throwable $e) { /* usar valor de .env */ }
try {
    if (!$forceRefresh) {
        $db = Database::getInstance();
        $cached = $db->queryOne(
            "SELECT * FROM audits WHERE url = ? AND created_at > datetime('now', '-' || ? || ' seconds') ORDER BY created_at DESC LIMIT 1",
            [$url, $cacheTtl]
        );

        if ($cached) {
            $result = json_decode($cached['result_json'], true);

            // Si hay nuevos datos de lead, actualizar el registro
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

            Response::success($result);
        }
    }
} catch (Throwable $e) {
    Logger::error('Error consultando cache: ' . $e->getMessage());
}

// Ejecutar auditoría
// Cerrar sesión PHP ANTES del scan para no bloquear otras peticiones del admin
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
try {
    $orchestrator = new AuditOrchestrator($url, [
        'leadName' => $body['leadName'] ?? '',
        'leadEmail' => $body['leadEmail'] ?? '',
        'leadWhatsapp' => $body['leadWhatsapp'] ?? '',
        'leadCompany' => $body['leadCompany'] ?? '',
    ]);

    $result = $orchestrator->run();
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (Throwable $e) {
    Logger::error('Error en auditoría: ' . $e->getMessage(), ['url' => $url]);
    Response::error('Ocurrió un error al analizar el sitio. Intenta nuevamente.', 500);
}

// Guardar en base de datos
try {
    $db = Database::getInstance();

    // Separar waterfall + extended perf del result principal
    $waterfallData = $result['waterfall'] ?? [];
    $extendedPerf = $result['extendedPerf'] ?? [];
    $resultForStorage = $result;
    unset($resultForStorage['waterfall'], $resultForStorage['extendedPerf']);
    $resultJson = json_encode($resultForStorage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $perfData = [
        'waterfall' => $waterfallData,
        'crux' => $extendedPerf['crux'] ?? null,
        'resourceBreakdown' => $extendedPerf['resourceBreakdown'] ?? [],
        'lighthouseAudits' => $extendedPerf['lighthouseAudits'] ?? [],
    ];
    $waterfallJson = json_encode($perfData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Asegurar que la columna waterfall_json existe (auto-migración)
    try {
        $db->execute("ALTER TABLE audits ADD COLUMN waterfall_json TEXT");
    } catch (Throwable $e) {
        // Columna ya existe — ignorar
    }

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
}

// Notificar al admin por email si el lead tiene datos de contacto
try {
    $leadEmail = trim($body['leadEmail'] ?? '');
    $leadWhatsapp = trim($body['leadWhatsapp'] ?? '');

    if ($leadEmail || $leadWhatsapp) {
        $db = Database::getInstance();
        $notifRow = $db->queryOne("SELECT value FROM settings WHERE key = 'lead_notification_email'");
        $notifEmail = $notifRow['value'] ?? '';

        if (!empty($notifEmail) && filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
            $domain = $result['domain'];
            $score = $result['globalScore'];
            $level = $result['globalLevel'];
            $leadName = trim($body['leadName'] ?? '') ?: 'No proporcionado';
            $leadCompany = trim($body['leadCompany'] ?? '') ?: 'No proporcionado';

            $subject = "Nuevo lead: $domain (Score: $score/100)";
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

Response::success($result);
