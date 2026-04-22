<?php
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$id = $_GET['id'] ?? '';
if (empty($id)) {
    Response::error(Translator::t('admin_api.common.id_required'));
}

try {
    $db = Database::getInstance();
    $audit = $db->queryOne("SELECT result_json, lead_name, lead_email, lead_whatsapp, lead_company, is_pinned, created_at FROM audits WHERE id = ?", [$id]);

    if (!$audit) {
        Response::error(Translator::t('admin_api.lead_detail.not_found'), 404);
    }

    $result = JsonStore::decode($audit['result_json']) ?? [];
    // Agregar datos del lead al resultado
    $result['leadName'] = $audit['lead_name'];
    $result['leadEmail'] = $audit['lead_email'];
    $result['leadWhatsapp'] = $audit['lead_whatsapp'];
    $result['leadCompany'] = $audit['lead_company'];
    $result['createdAt'] = $audit['created_at'];
    $result['isPinned'] = (bool) (int) ($audit['is_pinned'] ?? 0);

    Response::success($result);
} catch (Throwable $e) {
    Logger::error('Error en lead-detail: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.lead_detail.fetch_error'), 500);
}
