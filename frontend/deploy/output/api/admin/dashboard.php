<?php
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

try {
    $db = Database::getInstance();

    $totalAudits = (int) $db->scalar("SELECT COUNT(*) FROM audits");
    $totalLeads = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE (lead_email IS NOT NULL AND lead_email != '') OR (lead_whatsapp IS NOT NULL AND lead_whatsapp != '')");
    $auditsToday = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE date(created_at) = date('now')");
    $auditsThisWeek = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now', '-7 days')");
    $auditsThisMonth = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= date('now', '-30 days')");
    $averageScore = round((float) ($db->scalar("SELECT AVG(global_score) FROM audits") ?? 0), 1);

    $dist = [
        'critical' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 0 AND 29"),
        'deficient' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 30 AND 49"),
        'regular' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 50 AND 69"),
        'good' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 70 AND 89"),
        'excellent' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 90 AND 100"),
    ];

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
            'createdAt' => $row['created_at'],
            'hasContactInfo' => !empty($row['lead_email']) || !empty($row['lead_whatsapp']),
        ];
    }, $recent);

    // Dominios recurrentes
    $recurring = $db->query(
        "SELECT domain, COUNT(*) as total, MAX(global_score) as best_score, MIN(global_score) as worst_score, MAX(id) as last_audit_id FROM audits GROUP BY domain HAVING COUNT(*) > 1 ORDER BY COUNT(*) DESC LIMIT 10"
    );
    $recurringDomains = array_map(function ($row) use ($db) {
        // Calcular tendencia
        $last2 = $db->query("SELECT global_score FROM audits WHERE domain = ? ORDER BY created_at DESC LIMIT 2", [$row['domain']]);
        $trend = 'stable';
        if (count($last2) >= 2) {
            $diff = $last2[0]['global_score'] - $last2[1]['global_score'];
            if ($diff > 5) $trend = 'improving';
            elseif ($diff < -5) $trend = 'declining';
        }
        return [
            'domain' => $row['domain'],
            'totalAudits' => (int) $row['total'],
            'bestScore' => (int) $row['best_score'],
            'worstScore' => (int) $row['worst_score'],
            'lastAuditId' => $row['last_audit_id'],
            'trend' => $trend,
        ];
    }, $recurring);

    Response::success([
        'totalAudits' => $totalAudits,
        'totalLeads' => $totalLeads,
        'auditsToday' => $auditsToday,
        'auditsThisWeek' => $auditsThisWeek,
        'auditsThisMonth' => $auditsThisMonth,
        'averageScore' => $averageScore,
        'scoreDistribution' => $dist,
        'recentAudits' => $recentAudits,
        'recurringDomains' => $recurringDomains,
    ]);
} catch (Throwable $e) {
    Logger::error('Error en dashboard: ' . $e->getMessage());
    Response::error('Error al obtener estadísticas.', 500);
}
