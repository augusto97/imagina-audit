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

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');

try {
    $db = Database::getInstance();

    // Limpiar registros viejos (más de 1 hora)
    $db->execute(
        "DELETE FROM rate_limits WHERE request_time < datetime('now', '-1 hour')"
    );

    // Contar peticiones de esta IP
    $count = (int) $db->scalar(
        "SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = 'audit'",
        [$ip]
    );

    if ($count >= $maxPerHour) {
        Response::error('Has alcanzado el límite de auditorías por hora. Intenta más tarde.', 429);
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
    $db->execute(
        "INSERT INTO audits (id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, is_wordpress, scan_duration_ms, result_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $ip,
        ]
    );
} catch (Throwable $e) {
    Logger::error('Error guardando auditoría: ' . $e->getMessage());
    // No fallar si no se puede guardar — el usuario ya tiene su resultado
}

Response::success($result);
