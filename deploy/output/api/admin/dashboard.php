<?php
require_once dirname(__DIR__) . "/bootstrap.php";
/**
 * GET /api/admin/dashboard — Estadísticas generales
 */
Auth::requireAuth();

try {
    $db = Database::getInstance();

    $totalAudits = (int) $db->scalar("SELECT COUNT(*) FROM audits");
    $totalLeads = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE lead_email IS NOT NULL AND lead_email != ''");
    $auditsToday = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now')");
    $auditsThisWeek = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now', '-7 days')");
    $auditsThisMonth = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now', '-30 days')");
    $averageScore = (float) ($db->scalar("SELECT AVG(global_score) FROM audits") ?? 0);

    // Distribución de scores
    $dist = [
        'critical' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score < 30"),
        'deficient' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score >= 30 AND global_score < 50"),
        'regular' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score >= 50 AND global_score < 70"),
        'good' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score >= 70 AND global_score < 90"),
        'excellent' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score >= 90"),
    ];

    // Últimas 10 auditorías
    $recent = $db->query("SELECT id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, created_at FROM audits ORDER BY created_at DESC LIMIT 10");

    $recentAudits = array_map(function ($row) {
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
    }, $recent);

    Response::success([
        'totalAudits' => $totalAudits,
        'totalLeads' => $totalLeads,
        'auditsToday' => $auditsToday,
        'auditsThisWeek' => $auditsThisWeek,
        'auditsThisMonth' => $auditsThisMonth,
        'averageScore' => round($averageScore, 1),
        'scoreDistribution' => $dist,
        'recentAudits' => $recentAudits,
    ]);

} catch (Throwable $e) {
    Logger::error('Error en dashboard: ' . $e->getMessage());
    Response::error('Error al obtener estadísticas.', 500);
}
