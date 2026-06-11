<?php
/**
 * POST /api/capture-lead — Captura los datos de contacto de un lead para
 * un audit ya existente y desbloquea el informe completo (modo 'gated').
 *
 * Body JSON: { auditId, leadEmail?, leadName?, leadWhatsapp?, leadCompany? }
 *
 * Flujo:
 *   1. Valida que el audit exista.
 *   2. Valida los campos obligatorios según la config de leadCapture.
 *   3. Actualiza la fila audits con los datos (sin sobrescribir con vacíos).
 *   4. Dispara la notificación de lead al email del admin.
 *   5. Responde { unlocked: true } — el frontend desbloquea el informe.
 *
 * Endpoint PÚBLICO (sin auth): el prospecto anónimo es justamente quien
 * entrega sus datos. Rate-limit ligero por IP para evitar abuso.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$body = Response::getJsonBody();
$auditId = trim((string) ($body['auditId'] ?? ''));
$leadEmail = trim((string) ($body['leadEmail'] ?? ''));
$leadName = trim((string) ($body['leadName'] ?? ''));
$leadWhatsapp = trim((string) ($body['leadWhatsapp'] ?? ''));
$leadCompany = trim((string) ($body['leadCompany'] ?? ''));

if ($auditId === '') {
    Response::error(Translator::t('api.audit.id_required'), 400);
}

$defaults = require dirname(__DIR__) . '/config/defaults.php';

// Resolver requisitos desde settings (fallback a defaults).
$reqEmail = $defaults['lead_gate_require_email'];
$reqName = $defaults['lead_gate_require_name'];
$reqWhatsapp = $defaults['lead_gate_require_whatsapp'];
try {
    $db = Database::getInstance();
    $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('lead_gate_require_email','lead_gate_require_name','lead_gate_require_whatsapp')");
    foreach ($rows as $r) {
        $on = $r['value'] === '1' || $r['value'] === 'true';
        if ($r['key'] === 'lead_gate_require_email') $reqEmail = $on;
        if ($r['key'] === 'lead_gate_require_name') $reqName = $on;
        if ($r['key'] === 'lead_gate_require_whatsapp') $reqWhatsapp = $on;
    }
} catch (Throwable $e) { /* usar defaults */ }

// Validar campos obligatorios.
if ($reqEmail && $leadEmail === '') {
    Response::error(Translator::t('api.lead.email_required'), 400);
}
if ($leadEmail !== '' && !filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
    Response::error(Translator::t('api.lead.email_invalid'), 400);
}
if ($reqName && $leadName === '') {
    Response::error(Translator::t('api.lead.name_required'), 400);
}
if ($reqWhatsapp && $leadWhatsapp === '') {
    Response::error(Translator::t('api.lead.whatsapp_required'), 400);
}

try {
    $db = Database::getInstance();
    $audit = $db->queryOne("SELECT id, url, domain, global_score, global_level FROM audits WHERE id = ?", [$auditId]);
    if (!$audit) {
        Response::error(Translator::t('api.audit.not_found'), 404);
    }

    // Actualizar sin pisar con vacíos (COALESCE + NULLIF como en audit.php).
    $db->execute(
        "UPDATE audits SET
            lead_name = COALESCE(NULLIF(?, ''), lead_name),
            lead_email = COALESCE(NULLIF(?, ''), lead_email),
            lead_whatsapp = COALESCE(NULLIF(?, ''), lead_whatsapp),
            lead_company = COALESCE(NULLIF(?, ''), lead_company)
         WHERE id = ?",
        [$leadName, $leadEmail, $leadWhatsapp, $leadCompany, $auditId]
    );

    // Notificar al admin (mismo formato que el modo upfront).
    QueueManager::notifyLead([
        'domain' => $audit['domain'],
        'url' => $audit['url'],
        'globalScore' => $audit['global_score'],
        'globalLevel' => $audit['global_level'],
    ], [
        'leadName' => $leadName,
        'leadEmail' => $leadEmail,
        'leadWhatsapp' => $leadWhatsapp,
        'leadCompany' => $leadCompany,
    ]);

    Response::success(['unlocked' => true]);
} catch (Throwable $e) {
    Logger::error('Error capturando lead: ' . $e->getMessage());
    Response::error(Translator::t('api.lead.capture_error'), 500);
}
