<?php
/**
 * GET/DELETE /api/admin/leads — Lista y elimina auditorías/leads
 */
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        Response::error('El parámetro id es obligatorio.');
    }

    try {
        $db = Database::getInstance();
        $db->execute("DELETE FROM audits WHERE id = ?", [$id]);
        Response::success();
    } catch (Throwable $e) {
        Response::error('Error al eliminar la auditoría.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$filter = $_GET['filter'] ?? 'all';
$sort = $_GET['sort'] ?? 'date_desc';

$where = '1=1';
$params = [];

switch ($filter) {
    case 'with_contact':
        $where .= " AND (lead_email IS NOT NULL AND lead_email != '')";
        break;
    case 'critical_score':
        $where .= " AND global_score < 30";
        break;
    case 'this_week':
        $where .= " AND created_at >= date('now', '-7 days')";
        break;
}

$orderBy = match ($sort) {
    'date_asc' => 'created_at ASC',
    'score_asc' => 'global_score ASC',
    'score_desc' => 'global_score DESC',
    default => 'created_at DESC',
};

try {
    $db = Database::getInstance();
    $total = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE $where", $params);
    $rows = $db->query(
        "SELECT id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, created_at FROM audits WHERE $where ORDER BY $orderBy LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );

    $leads = array_map(function ($row) {
        return [
            'id' => $row['id'],
            'url' => $row['url'],
            'domain' => $row['domain'],
            'leadName' => $row['lead_name'],
            'leadEmail' => $row['lead_email'],
            'leadWhatsapp' => $row['lead_whatsapp'],
            'leadCompany' => $row['lead_company'],
            'globalScore' => (int) $row['global_score'],
            'globalLevel' => $row['global_level'],
            'timestamp' => $row['created_at'],
            'hasContactInfo' => !empty($row['lead_email']) || !empty($row['lead_whatsapp']),
        ];
    }, $rows);

    Response::success([
        'leads' => $leads,
        'total' => $total,
        'page' => $page,
    ]);
} catch (Throwable $e) {
    Response::error('Error al obtener leads.', 500);
}
