<?php
/**
 * GET /api/audit-status?id=X — Obtiene resultado de auditoría por ID
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    Response::error(Translator::t('api.audit.id_required'));
}

try {
    $db = Database::getInstance();
    $audit = $db->queryOne("SELECT result_json, lead_email, lead_whatsapp, lead_name FROM audits WHERE id = ?", [$id]);

    if (!$audit) {
        Response::error(Translator::t('api.audit.not_found'), 404);
    }

    $result = JsonStore::decode($audit['result_json']);
    // Re-evaluar scores con la config ACTUAL — sin esto, audits viejos
    // muestran su score original aunque el admin haya ajustado pesos /
    // thresholds / disabled metrics. Coherencia para el cliente.
    if (is_array($result)) {
        $result = Scoring::recalculate($result);
        // Meta para el gating del frontend (modo 'gated'): ¿ya hay un lead
        // asociado a este audit? Si sí, no volvemos a bloquear el informe.
        // No exponemos los datos del lead — solo el booleano.
        $result['_leadCaptured'] = !empty($audit['lead_email']) || !empty($audit['lead_whatsapp']);
    }
    Response::success($result);
} catch (Throwable $e) {
    Logger::error('Error obteniendo auditoría: ' . $e->getMessage());
    Response::error(Translator::t('api.audit.fetch_error'), 500);
}
