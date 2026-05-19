<?php
/**
 * GET /api/admin/dashboard
 *
 * Datos agregados para el dashboard admin. Consolida stats de:
 *   - audits table (totales, distribución, tendencia, WP vs no-WP)
 *   - audit_jobs (cola en vivo)
 *   - wp_snapshots (snapshots conectados)
 *   - vulnerabilities (base local de CVEs)
 *   - settings (2FA, plugin vault, retención)
 *   - CronHealth (estado de tareas automáticas)
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

try {
    $db = Database::getInstance();

    // ─── Audits: totales y segmentación ──────────────────────────────
    $today = $db->today();
    $weekAgo = $db->nowMinus(7 * 86400);
    $monthAgo = $db->nowMinus(30 * 86400);
    $hourAgo = $db->nowMinus(3600);

    $totalAudits    = (int) $db->scalar("SELECT COUNT(*) FROM audits");
    $auditsToday    = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= ?", ["$today 00:00:00"]);
    $auditsThisWeek = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= ?", [$weekAgo]);
    $auditsThisMonth = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE created_at >= ?", [$monthAgo]);
    $averageScore   = round((float) ($db->scalar("SELECT AVG(global_score) FROM audits") ?? 0), 1);
    $averageScore7d = round((float) ($db->scalar("SELECT AVG(global_score) FROM audits WHERE created_at >= ?", [$weekAgo]) ?? 0), 1);

    $totalLeads = (int) $db->scalar(
        "SELECT COUNT(*) FROM audits WHERE (lead_email IS NOT NULL AND lead_email != '') OR (lead_whatsapp IS NOT NULL AND lead_whatsapp != '')"
    );
    $conversionRate = $totalAudits > 0 ? round(($totalLeads / $totalAudits) * 100, 1) : 0;

    $wpCount    = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE is_wordpress = 1");
    $nonWpCount = $totalAudits - $wpCount;
    $wpRate     = $totalAudits > 0 ? round(($wpCount / $totalAudits) * 100, 1) : 0;

    // Pinned (protegidos de la retención)
    $pinnedCount = 0;
    try {
        $pinnedCount = (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE is_pinned = 1");
    } catch (Throwable $e) { /* columna quizá no existe en DBs muy viejas */ }

    // ─── Distribución de scores ──────────────────────────────────────
    $dist = [
        'critical'  => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 0 AND 29"),
        'deficient' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 30 AND 49"),
        'regular'   => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 50 AND 69"),
        'good'      => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 70 AND 89"),
        'excellent' => (int) $db->scalar("SELECT COUNT(*) FROM audits WHERE global_score BETWEEN 90 AND 100"),
    ];

    // ─── Trend: conteos diarios de los últimos 30 días ──────────────
    // Rellena con ceros los días sin datos para graficar una línea continua.
    // DATE(created_at) funciona en ambos drivers (MySQL: DATE() built-in;
    // SQLite: date() built-in case-insensitive).
    $rawTrend = $db->query(
        "SELECT DATE(created_at) AS d, COUNT(*) AS c, ROUND(AVG(global_score), 1) AS avg_score
         FROM audits
         WHERE created_at >= ?
         GROUP BY DATE(created_at)
         ORDER BY d ASC",
        [$monthAgo]
    );
    $trendByDay = [];
    foreach ($rawTrend as $r) {
        $trendByDay[$r['d']] = ['count' => (int) $r['c'], 'avgScore' => (float) $r['avg_score']];
    }
    $trend30d = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $trend30d[] = [
            'date'     => $d,
            'count'    => $trendByDay[$d]['count'] ?? 0,
            'avgScore' => $trendByDay[$d]['avgScore'] ?? null,
        ];
    }

    // ─── Audits recientes ────────────────────────────────────────────
    $recent = $db->query(
        "SELECT id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company,
                global_score, global_level, is_wordpress, is_pinned, created_at
         FROM audits ORDER BY created_at DESC LIMIT 10"
    );
    $recentAudits = array_map(fn($r) => [
        'id'             => $r['id'],
        'url'            => $r['url'],
        'domain'         => $r['domain'],
        'leadName'       => $r['lead_name'],
        'leadEmail'      => $r['lead_email'],
        'leadWhatsapp'   => $r['lead_whatsapp'],
        'leadCompany'    => $r['lead_company'],
        'globalScore'    => (int) $r['global_score'],
        'globalLevel'    => $r['global_level'],
        'isWordPress'    => (bool) (int) ($r['is_wordpress'] ?? 0),
        'isPinned'       => (bool) (int) ($r['is_pinned'] ?? 0),
        'createdAt'      => $r['created_at'],
        'hasContactInfo' => !empty($r['lead_email']) || !empty($r['lead_whatsapp']),
    ], $recent);

    // ─── Dominios recurrentes con tendencia ──────────────────────────
    $recurring = $db->query(
        "SELECT domain, COUNT(*) as total, MAX(global_score) as best_score, MIN(global_score) as worst_score, MAX(id) as last_audit_id
         FROM audits
         GROUP BY domain HAVING COUNT(*) > 1
         ORDER BY COUNT(*) DESC LIMIT 10"
    );

    $trendByDomain = [];
    if (!empty($recurring)) {
        $domains = array_column($recurring, 'domain');
        $placeholders = implode(',', array_fill(0, count($domains), '?'));
        // ROW_NUMBER() OVER es MySQL 8.0+ — no soportado en 5.7. Traemos
        // los audits ordenados por dominio + fecha y agrupamos en PHP
        // quedándonos con los 2 más recientes por dominio. Cantidad
        // razonable (10 dominios × N audits cada uno; en peor caso 200
        // filas, despreciable).
        $allScores = $db->query(
            "SELECT domain, global_score, created_at
             FROM audits WHERE domain IN ($placeholders)
             ORDER BY domain, created_at DESC",
            $domains
        );
        $scoresByDomain = [];
        foreach ($allScores as $r) {
            $d = $r['domain'];
            if (!isset($scoresByDomain[$d])) $scoresByDomain[$d] = [];
            if (count($scoresByDomain[$d]) < 2) {
                $scoresByDomain[$d][] = (int) $r['global_score'];
            }
        }
        foreach ($scoresByDomain as $domain => $scores) {
            if (count($scores) < 2) { $trendByDomain[$domain] = 'stable'; continue; }
            $diff = $scores[0] - $scores[1]; // newest - previous
            $trendByDomain[$domain] = $diff > 5 ? 'improving' : ($diff < -5 ? 'declining' : 'stable');
        }
    }
    $recurringDomains = array_map(fn($r) => [
        'domain'       => $r['domain'],
        'totalAudits'  => (int) $r['total'],
        'bestScore'    => (int) $r['best_score'],
        'worstScore'   => (int) $r['worst_score'],
        'lastAuditId'  => $r['last_audit_id'],
        'trend'        => $trendByDomain[$r['domain']] ?? 'stable',
    ], $recurring);

    // ─── Queue live ──────────────────────────────────────────────────
    $queue = ['running' => 0, 'queued' => 0, 'failedLastHour' => 0, 'completedLastHour' => 0, 'maxConcurrent' => 0];
    try {
        $queue['running']           = QueueManager::runningCount();
        $queue['queued']            = QueueManager::queuedCount();
        $queue['maxConcurrent']     = QueueManager::getMaxConcurrent();
        $queue['failedLastHour']    = (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'failed' AND completed_at > ?", [$hourAgo]);
        $queue['completedLastHour'] = (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'completed' AND completed_at > ?", [$hourAgo]);
    } catch (Throwable $e) { /* tabla quizá no existe aún */ }

    // ─── Integraciones (snapshots conectados, vulnerabilidades) ──────
    $snapshots = ['connected' => 0, 'percentageOfWp' => 0];
    try {
        $snapshots['connected'] = (int) $db->scalar("SELECT COUNT(*) FROM wp_snapshots");
        $snapshots['percentageOfWp'] = $wpCount > 0 ? round(($snapshots['connected'] / $wpCount) * 100, 1) : 0;
    } catch (Throwable $e) { /* sin tabla */ }

    $vulnerabilities = ['total' => 0, 'lastUpdate' => null];
    try {
        $vulnerabilities['total'] = (int) $db->scalar("SELECT COUNT(*) FROM vulnerabilities");
        $vulnerabilities['lastUpdate'] = $db->scalar("SELECT MAX(created_at) FROM vulnerabilities");
    } catch (Throwable $e) { /* sin tabla */ }

    // ─── Estado del sistema (2FA, plugin vault, retención) ──────────
    $security = [
        'twoFaEnabled' => false,
        'recoveryCodesLeft' => 0,
    ];
    try {
        $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'admin_2fa_enabled'");
        $security['twoFaEnabled'] = $row && (string) $row['value'] === '1';
        $codesRow = $db->queryOne("SELECT value FROM settings WHERE `key` = 'admin_2fa_recovery_codes'");
        if ($codesRow) {
            $decoded = json_decode((string) $codesRow['value'], true);
            $security['recoveryCodesLeft'] = is_array($decoded) ? count($decoded) : 0;
        }
    } catch (Throwable $e) { /* ignore */ }

    $pluginVault = ['cached' => false, 'version' => null, 'checkedAt' => null];
    try {
        if (class_exists('PluginVault')) {
            $st = PluginVault::status('wp-snapshot');
            $pluginVault = [
                'cached'    => (bool) ($st['fileExists'] ?? false),
                'version'   => $st['version'] ?? null,
                'checkedAt' => $st['checkedAt'] ?? null,
            ];
        }
    } catch (Throwable $e) { /* ignore */ }

    $retention = ['enabled' => false, 'months' => 6];
    try {
        $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'audits_retention_enabled'");
        $retention['enabled'] = $row && in_array((string) $row['value'], ['1', 'true'], true);
        $monthsRow = $db->queryOne("SELECT value FROM settings WHERE `key` = 'audits_retention_months'");
        if ($monthsRow) $retention['months'] = (int) $monthsRow['value'];
    } catch (Throwable $e) { /* ignore */ }

    // ─── Cron health resumido ────────────────────────────────────────
    $cronHealth = null;
    try {
        $cronSummary = CronHealth::summary();
        $cronHealth = [
            'overallOk' => $cronSummary['overallOk'],
            'counts'    => $cronSummary['counts'],
        ];
    } catch (Throwable $e) { /* clase quizá no cargada aún */ }

    Response::success([
        'audits' => [
            'total'          => $totalAudits,
            'today'          => $auditsToday,
            'thisWeek'       => $auditsThisWeek,
            'thisMonth'      => $auditsThisMonth,
            'averageScore'   => $averageScore,
            'averageScore7d' => $averageScore7d,
            'pinned'         => $pinnedCount,
            'wpCount'        => $wpCount,
            'nonWpCount'     => $nonWpCount,
            'wpRate'         => $wpRate,
        ],
        'leads' => [
            'total'          => $totalLeads,
            'conversionRate' => $conversionRate,
        ],
        'scoreDistribution' => $dist,
        'trend30d'          => $trend30d,
        'recentAudits'      => $recentAudits,
        'recurringDomains'  => $recurringDomains,
        'queue'             => $queue,
        'snapshots'         => $snapshots,
        'vulnerabilities'   => $vulnerabilities,
        'security'          => $security,
        'pluginVault'       => $pluginVault,
        'retention'         => $retention,
        'cronHealth'        => $cronHealth,
    ]);
} catch (Throwable $e) {
    Logger::error('Error en dashboard: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    // El admin está autenticado (Auth::requireAuth() ya pasó). Exponemos el
    // mensaje real para que pueda diagnosticar sin SSH. APP_DEBUG=true
    // además incluye file/line/trace.
    $msg = Translator::t('admin_api.dashboard.stats_error') . ': ' . $e->getMessage();
    if (function_exists('env') && env('APP_DEBUG', 'false') === 'true') {
        $msg .= ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
    Response::error($msg, 500);
}
