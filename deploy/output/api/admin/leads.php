<?php
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();

// ─── DELETE ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) Response::error(Translator::t('admin_api.common.id_required'));

    $row = $db->queryOne("SELECT is_pinned FROM audits WHERE id = ?", [$id]);
    if ($row && (int) $row['is_pinned'] === 1) {
        Response::error(Translator::t('admin_api.leads.protected_report'), 409);
    }

    $db->execute("DELETE FROM audits WHERE id = ?", [$id]);
    try { $db->execute("DELETE FROM wp_snapshots WHERE audit_id = ?", [$id]); } catch (Throwable $e) {}
    Response::success();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

// ─── GET — filtros, paginación, búsqueda, summary ────────────────────

$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort'] ?? 'date_desc';
$search = trim($_GET['search'] ?? '');

// Nuevos filtros "dimensionales" (pueden combinarse con filter)
$filterWp     = $_GET['wp'] ?? 'any';           // any|yes|no
$filterSnap   = $_GET['snapshot'] ?? 'any';     // any|yes|no
$filterPinned = $_GET['pinned'] ?? 'any';       // any|yes|no

// LEFT JOIN con wp_snapshots para saber cuáles leads tienen snapshot cargado
$fromClause = "FROM audits a LEFT JOIN wp_snapshots s ON s.audit_id = a.id";

$where = '1=1';
$params = [];

switch ($filter) {
    case 'with_contact':
        $where .= " AND ((a.lead_email IS NOT NULL AND a.lead_email != '') OR (a.lead_whatsapp IS NOT NULL AND a.lead_whatsapp != ''))";
        break;
    case 'critical':
        $where .= " AND a.global_score < 30";
        break;
    case 'warning':
        $where .= " AND a.global_score >= 30 AND a.global_score < 50";
        break;
    case 'this_week':
        $where .= " AND a.created_at >= date('now', '-7 days')";
        break;
    case 'this_month':
        $where .= " AND a.created_at >= date('now', '-30 days')";
        break;
}

if ($filterWp === 'yes') {
    $where .= " AND a.is_wordpress = 1";
} elseif ($filterWp === 'no') {
    $where .= " AND a.is_wordpress = 0";
}

if ($filterSnap === 'yes') {
    $where .= " AND s.id IS NOT NULL";
} elseif ($filterSnap === 'no') {
    $where .= " AND s.id IS NULL";
}

if ($filterPinned === 'yes') {
    $where .= " AND a.is_pinned = 1";
} elseif ($filterPinned === 'no') {
    $where .= " AND (a.is_pinned = 0 OR a.is_pinned IS NULL)";
}

if ($search !== '') {
    $where .= " AND (a.domain LIKE ? OR a.lead_name LIKE ? OR a.lead_email LIKE ? OR a.lead_company LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$orderBy = match ($sort) {
    'date_asc'   => 'a.created_at ASC',
    'score_asc'  => 'a.global_score ASC',
    'score_desc' => 'a.global_score DESC',
    'domain_asc' => 'a.domain ASC',
    default      => 'a.created_at DESC',
};

try {
    // Total filtrado
    $total = (int) $db->scalar("SELECT COUNT(*) $fromClause WHERE $where", $params);
    $totalPages = (int) ceil($total / $limit);

    // Filas con JOIN — has_snapshot derivado de s.id IS NOT NULL
    $rows = $db->query(
        "SELECT a.id, a.url, a.domain, a.lead_name, a.lead_email, a.lead_whatsapp,
                a.lead_company, a.global_score, a.global_level, a.is_wordpress,
                a.is_pinned, a.created_at,
                CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END AS has_snapshot
         $fromClause
         WHERE $where
         ORDER BY $orderBy
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );

    $leads = array_map(fn($row) => [
        'id'             => $row['id'],
        'url'            => $row['url'],
        'domain'         => $row['domain'],
        'leadName'       => $row['lead_name'],
        'leadEmail'      => $row['lead_email'],
        'leadWhatsapp'   => $row['lead_whatsapp'],
        'leadCompany'    => $row['lead_company'],
        'globalScore'    => (int) $row['global_score'],
        'globalLevel'    => $row['global_level'],
        'isWordPress'    => (bool) (int) ($row['is_wordpress'] ?? 0),
        'isPinned'       => (bool) (int) ($row['is_pinned'] ?? 0),
        'hasSnapshot'    => (bool) (int) ($row['has_snapshot'] ?? 0),
        'createdAt'      => $row['created_at'],
        'hasContactInfo' => !empty($row['lead_email']) || !empty($row['lead_whatsapp']),
    ], $rows);

    // Summary global (no filtrado por filter/search — es el panorama completo)
    $summary = [
        'total'         => (int) $db->scalar("SELECT COUNT(*) FROM audits"),
        'withContact'   => (int) $db->scalar(
            "SELECT COUNT(*) FROM audits
             WHERE (lead_email IS NOT NULL AND lead_email != '')
                OR (lead_whatsapp IS NOT NULL AND lead_whatsapp != '')"
        ),
        'critical'      => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score < 30"),
        'wordpress'     => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE is_wordpress = 1"),
        'pinned'        => 0,
        'withSnapshot'  => 0,
        'thisWeek'      => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now', '-7 days')"),
    ];
    try { $summary['pinned'] = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE is_pinned = 1"); } catch (Throwable $e) {}
    try { $summary['withSnapshot'] = (int) $db->scalar("SELECT COUNT(DISTINCT audit_id) FROM wp_snapshots"); } catch (Throwable $e) {}

    Response::success([
        'leads'      => $leads,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => $totalPages,
        'summary'    => $summary,
    ]);
} catch (Throwable $e) {
    Logger::error('Error en leads: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.leads.fetch_error'), 500);
}
