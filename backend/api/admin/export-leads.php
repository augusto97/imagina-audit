<?php
/**
 * GET /api/admin/export-leads.php — Exporta leads a CSV
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = '1=1';
$params = [];

switch ($filter) {
    case 'with_contact':
        $where .= " AND ((lead_email IS NOT NULL AND lead_email != '') OR (lead_whatsapp IS NOT NULL AND lead_whatsapp != ''))";
        break;
    case 'critical':
        $where .= " AND global_score < 30";
        break;
    case 'this_week':
        $where .= " AND created_at >= date('now', '-7 days')";
        break;
    case 'this_month':
        $where .= " AND created_at >= date('now', '-30 days')";
        break;
}

if ($search !== '') {
    $where .= " AND (domain LIKE ? OR lead_name LIKE ? OR lead_email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

try {
    $db = Database::getInstance();
    $rows = $db->query(
        "SELECT created_at, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, is_wordpress FROM audits WHERE $where ORDER BY created_at DESC LIMIT 5000",
        $params
    );

    $filename = 'imagina-audit-leads-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");

    // BOM UTF-8 para Excel
    echo chr(0xEF) . chr(0xBB) . chr(0xBF);

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'URL', 'Dominio', 'Nombre', 'Email', 'WhatsApp', 'Empresa', 'Score', 'Nivel', 'WordPress']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'],
            $row['url'],
            $row['domain'],
            $row['lead_name'] ?? '',
            $row['lead_email'] ?? '',
            $row['lead_whatsapp'] ?? '',
            $row['lead_company'] ?? '',
            $row['global_score'],
            $row['global_level'],
            $row['is_wordpress'] ? 'Sí' : 'No',
        ]);
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    Logger::error('Error exportando CSV: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.export_leads.error'), 500);
}
