<?php
/**
 * Endpoint unificado de scoring para el panel admin.
 *
 *   GET                              → catálogo + settings actuales + audit
 *                                       reciente para preview
 *   POST { action: 'preview', config } → recalcula un audit con config
 *                                       propuesta sin guardar
 *   POST { action: 'save', config }   → persiste todos los settings de
 *                                       scoring en una sola llamada
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$defaults = require dirname(__DIR__, 2) . '/config/defaults.php';

/** Lista de keys de settings que controla esta página. */
function scoringSettingKeys(): array {
    return [
        'threshold_excellent','threshold_good','threshold_warning','threshold_critical',
        'weight_wordpress','weight_security','weight_performance','weight_seo',
        'weight_mobile','weight_infrastructure','weight_conversion','weight_page_health','weight_wp_internal',
        'scoring_critical_cap_enabled','scoring_critical_cap_per_module',
        'scoring_critical_penalty_enabled','scoring_critical_penalties',
        'scoring_disabled_metrics','scoring_disabled_modules',
        'scoring_metric_weights',
    ];
}

/** Lee los settings actuales (DB > defaults). */
function loadCurrentConfig(Database $db, array $defaults): array {
    $keys = scoringSettingKeys();
    $rows = [];
    try {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ($placeholders)", $keys);
    } catch (Throwable $e) { /* tabla vacía */ }

    $out = [];
    foreach ($rows as $row) $out[$row['key']] = $row['value'];

    $jsonKeys = [
        'scoring_critical_cap_per_module','scoring_critical_penalties',
        'scoring_disabled_metrics','scoring_disabled_modules','scoring_metric_weights',
    ];
    foreach ($jsonKeys as $k) {
        if (isset($out[$k]) && is_string($out[$k])) {
            $decoded = json_decode($out[$k], true);
            if (is_array($decoded)) $out[$k] = $decoded;
        }
        if (!isset($out[$k])) $out[$k] = $defaults[$k] ?? [];
    }
    foreach (['threshold_excellent','threshold_good','threshold_warning','threshold_critical'] as $k) {
        $out[$k] = (int) ($out[$k] ?? $defaults[$k]);
    }
    foreach (['scoring_critical_cap_enabled','scoring_critical_penalty_enabled'] as $k) {
        $out[$k] = isset($out[$k])
            ? in_array((string) $out[$k], ['1','true','yes','on'], true)
            : (bool) ($defaults[$k] ?? true);
    }
    // Pesos de módulos
    foreach (array_keys($defaults) as $k) {
        if (str_starts_with($k, 'weight_')) {
            $out[$k] = isset($out[$k]) ? (float) $out[$k] : (float) $defaults[$k];
        }
    }
    return $out;
}

if ($method === 'GET') {
    // 1. Catálogo estático
    $catalogFile = dirname(__DIR__, 2) . '/data/metrics-catalog.json';
    $catalog = is_file($catalogFile) ? (json_decode(file_get_contents($catalogFile), true) ?: ['modules' => []]) : ['modules' => []];

    // 2. Augmenta con métricas vistas en audits recientes (10 últimos).
    //    Así si alguien añadió un analyzer nuevo aparece en el panel sin
    //    tener que actualizar el JSON estático.
    try {
        $rows = $db->query("SELECT result_json FROM audits WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 10");
        foreach ($rows as $row) {
            $result = JsonStore::decode($row['result_json']);
            if (!is_array($result) || empty($result['modules'])) continue;
            foreach ($result['modules'] as $module) {
                $moduleId = $module['id'] ?? '';
                if ($moduleId === '') continue;
                if (!isset($catalog['modules'][$moduleId])) {
                    $catalog['modules'][$moduleId] = ['name' => $module['name'] ?? $moduleId, 'metrics' => []];
                }
                $existingIds = array_column($catalog['modules'][$moduleId]['metrics'], 'id');
                foreach ($module['metrics'] ?? [] as $metric) {
                    $mid = $metric['id'] ?? '';
                    if ($mid === '' || in_array($mid, $existingIds, true)) continue;
                    $catalog['modules'][$moduleId]['metrics'][] = ['id' => $mid, 'name' => $metric['name'] ?? $mid];
                    $existingIds[] = $mid;
                }
            }
        }
    } catch (Throwable $e) { /* sin audits, catálogo estático solo */ }

    // 3. Config actual
    $config = loadCurrentConfig($db, $defaults);

    // 4. Audit reciente para preview
    $previewAudit = null;
    try {
        $row = $db->queryOne(
            "SELECT id, url, domain, global_score, global_level FROM audits WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 1"
        );
        if ($row) {
            $previewAudit = [
                'id' => $row['id'], 'url' => $row['url'], 'domain' => $row['domain'],
                'currentScore' => (int) $row['global_score'], 'currentLevel' => $row['global_level'],
            ];
        }
    } catch (Throwable $e) {}

    Response::success([
        'catalog' => $catalog,
        'config' => $config,
        'defaults' => array_intersect_key($defaults, array_flip(scoringSettingKeys())),
        'previewAudit' => $previewAudit,
    ]);
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $action = $body['action'] ?? 'save';

    if ($action === 'preview') {
        // Aplicar config propuesta en memoria + recalcular un audit
        $auditId = (string) ($body['auditId'] ?? '');
        $config = (array) ($body['config'] ?? []);
        if ($auditId === '') Response::error('auditId required', 400);

        try {
            $row = $db->queryOne("SELECT result_json FROM audits WHERE id = ?", [$auditId]);
            if (!$row) Response::error('audit not found', 404);
            $audit = JsonStore::decode($row['result_json']);
            if (!is_array($audit)) Response::error('audit corrupt', 500);

            // Inyectar config propuesta en el cache de Scoring sin guardar.
            // Recargamos la clase pero swap el config en memoria.
            $mergedConfig = array_merge($defaults, $config);
            $reflection = new ReflectionClass(Scoring::class);
            $prop = $reflection->getProperty('configCache');
            $prop->setValue(null, $mergedConfig);

            $recalc = Scoring::recalculate($audit);

            // Reset para que las siguientes requests no usen este config.
            Scoring::resetConfig();

            Response::success([
                'auditId' => $auditId,
                'previousGlobal' => $audit['globalScore'] ?? null,
                'previousLevel' => $audit['globalLevel'] ?? null,
                'newGlobal' => $recalc['globalScore'],
                'newLevel' => $recalc['globalLevel'],
                'moduleScores' => array_map(fn($m) => [
                    'id' => $m['id'], 'name' => $m['name'] ?? $m['id'],
                    'score' => $m['score'], 'level' => $m['level'],
                ], $recalc['modules']),
                'totalIssues' => $recalc['totalIssues'] ?? null,
            ]);
        } catch (Throwable $e) {
            Logger::error('scoring preview falló: ' . $e->getMessage());
            Response::error('Preview failed: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'save') {
        $config = (array) ($body['config'] ?? []);

        try {
            $db->transaction(function ($db) use ($config) {
                $jsonKeys = ['scoring_critical_cap_per_module','scoring_critical_penalties',
                             'scoring_disabled_metrics','scoring_disabled_modules','scoring_metric_weights'];
                foreach ($config as $k => $v) {
                    if (!in_array($k, scoringSettingKeys(), true)) continue;
                    if (in_array($k, $jsonKeys, true)) {
                        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                    } elseif (is_bool($v)) {
                        $v = $v ? '1' : '0';
                    } else {
                        $v = (string) $v;
                    }
                    $db->setting($k, $v);
                }
            });
            Scoring::resetConfig();
            Response::success(['ok' => true]);
        } catch (Throwable $e) {
            Logger::error('scoring save falló: ' . $e->getMessage());
            Response::error('Save failed: ' . $e->getMessage(), 500);
        }
    }

    Response::error('Unknown action', 400);
}

Response::error('Method not allowed', 405);
