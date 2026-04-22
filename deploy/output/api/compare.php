<?php
/**
 * POST /api/compare.php — Compara 2 sitios lado a lado
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

set_time_limit(240);
ini_set('memory_limit', '256M');

$body = Response::getJsonBody();

$url1 = trim($body['url1'] ?? '');
$url2 = trim($body['url2'] ?? '');

if (empty($url1) || empty($url2)) {
    Response::error(Translator::t('api.compare.urls_required'));
}

try {
    $url1 = UrlValidator::validate($url1);
    $url2 = UrlValidator::validate($url2);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage());
}

// Rate limiting (no aplica si estás logueado como admin)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'rate_limit_max_per_hour'");
    if ($row && is_numeric($row['value'])) $maxPerHour = (int) $row['value'];
} catch (Throwable $e) { /* usar valor de .env */ }
$isAdmin = Auth::checkAuth();
try {
    $db = Database::getInstance();
    $db->execute("DELETE FROM rate_limits WHERE request_time < datetime('now', '-1 hour')");
    if (!$isAdmin) {
        $count = (int) $db->scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = 'compare'",
            [$ip]
        );
        if ($count >= $maxPerHour) {
            Response::error(Translator::t('api.compare.rate_limit'), 429);
        }
    }
    $db->execute("INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, 'compare')", [$ip]);
} catch (Throwable $e) {
    // Continuar sin rate limiting
}

/**
 * Obtiene o ejecuta una auditoría para una URL
 */
function getOrRunAudit(string $url): array {
    $cacheTtl = (int) env('CACHE_TTL_SECONDS', '86400');
    $db = Database::getInstance();

    // Verificar cache
    $cached = $db->queryOne(
        "SELECT result_json FROM audits WHERE url = ? AND created_at > datetime('now', '-' || ? || ' seconds') ORDER BY created_at DESC LIMIT 1",
        [$url, $cacheTtl]
    );

    if ($cached) {
        return JsonStore::decode($cached['result_json']) ?? [];
    }

    // Ejecutar auditoría nueva
    $orchestrator = new AuditOrchestrator($url);
    $result = $orchestrator->run();

    // Guardar en DB
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $db->execute(
        "INSERT INTO audits (id, url, domain, global_score, global_level, is_wordpress, scan_duration_ms, result_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $result['id'], $result['url'], $result['domain'],
            $result['globalScore'], $result['globalLevel'],
            $result['isWordPress'] ? 1 : 0, $result['scanDurationMs'],
            JsonStore::encode($result), $ip,
        ]
    );

    return $result;
}

try {
    // Ejecutar secuencialmente
    $audit1 = getOrRunAudit($url1);
    $audit2 = getOrRunAudit($url2);
} catch (Throwable $e) {
    Response::error(Translator::t('api.compare.runtime_error', ['details' => $e->getMessage()]), 422);
}

// Generar comparación
$moduleComparison = [];
foreach ($audit1['modules'] as $mod1) {
    $mod2Score = null;
    foreach ($audit2['modules'] as $mod2) {
        if ($mod2['id'] === $mod1['id']) {
            $mod2Score = $mod2['score'];
            break;
        }
    }
    $s1 = $mod1['score'] ?? 0;
    $s2 = $mod2Score ?? 0;
    $moduleComparison[] = [
        'moduleId' => $mod1['id'],
        'moduleName' => $mod1['name'],
        'score1' => $s1,
        'score2' => $s2,
        'winner' => $s1 > $s2 ? 'url1' : ($s2 > $s1 ? 'url2' : 'tie'),
    ];
}

$diff = $audit1['globalScore'] - $audit2['globalScore'];
$winner = $diff > 0 ? 'url1' : ($diff < 0 ? 'url2' : 'tie');

Response::success([
    'audit1' => $audit1,
    'audit2' => $audit2,
    'comparison' => [
        'winner' => $winner,
        'scoreDifference' => abs($diff),
        'moduleComparison' => $moduleComparison,
    ],
]);
