<?php
/**
 * Snapshot de WordPress (plugin wp-snapshot)
 *
 * POST /api/admin/snapshot.php
 *   Body: { auditId, jsonData }
 *   Guarda el snapshot para una auditoría, ejecuta el analyzer y re-ejecuta
 *   la auditoría completa inyectando los datos del snapshot.
 *
 * GET /api/admin/snapshot.php?audit_id=X
 *   Retorna el snapshot + análisis de una auditoría.
 *
 * DELETE /api/admin/snapshot.php?audit_id=X
 *   Elimina el snapshot de una auditoría.
 *
 * NOTA: La obtención por URL share se quitó — muchos hostings bloquean
 * fetches externos a la página share (WAF, UA blocking, auth checks), así
 * que obliga al operador a subir el JSON manualmente. Es más confiable.
 */

require_once __DIR__ . '/../bootstrap.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// POST / DELETE son operaciones admin (subir o quitar snapshot). GET es
// de lectura — lo abrimos al dueño del audit también (P5.10).
if ($method === 'GET') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) Response::error(Translator::t('admin_api.common.audit_id_required'), 400);
    AuditAccess::require((string) $auditId);
} else {
    Auth::requireAuth();
}

// Auto-migration
try {
    $db->execute("CREATE TABLE IF NOT EXISTS wp_snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        audit_id TEXT NOT NULL,
        source TEXT NOT NULL DEFAULT 'upload',
        source_url TEXT,
        snapshot_json TEXT NOT NULL,
        analysis_json TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(audit_id)
    )");
} catch (Throwable $e) {}

if ($method === 'GET') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) Response::error(Translator::t('admin_api.common.audit_id_required'), 400);

    $row = $db->queryOne("SELECT source, source_url, snapshot_json, analysis_json, created_at FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    if (!$row) Response::success(null);

    Response::success([
        'source' => $row['source'],
        'sourceUrl' => $row['source_url'],
        'snapshot' => JsonStore::decode($row['snapshot_json']),
        'analysis' => $row['analysis_json'] ? JsonStore::decode($row['analysis_json']) : null,
        'createdAt' => $row['created_at'],
    ]);
}

if ($method === 'DELETE') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) Response::error(Translator::t('admin_api.common.audit_id_required'), 400);
    $db->execute("DELETE FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    Response::success(['ok' => true]);
}

if ($method === 'POST') {
    // Snapshots del plugin wp-snapshot pueden ser grandes (29 métricas con listas
    // de plugins, DB stats, etc.). Tope de 10MB + profundidad 128.
    $body = Response::getJsonBody(10 * 1024 * 1024, 128);
    $auditId = $body['auditId'] ?? '';

    if (empty($auditId)) Response::error(Translator::t('admin_api.snapshot.audit_id_required'), 400);

    $jsonData = $body['jsonData'] ?? null;
    if (empty($jsonData)) Response::error(Translator::t('admin_api.snapshot.json_data_required'), 400);

    $snapshotData = null;
    if (is_string($jsonData)) {
        if (strlen($jsonData) > 10 * 1024 * 1024) {
            Response::error(Translator::t('admin_api.snapshot.json_data_too_big'), 413);
        }
        $snapshotData = json_decode($jsonData, true, 128);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error(Translator::t('admin_api.snapshot.json_invalid_reason', ['reason' => json_last_error_msg()]), 400);
        }
    } elseif (is_array($jsonData)) {
        $snapshotData = $jsonData;
    }

    if (!is_array($snapshotData)) {
        Response::error(Translator::t('admin_api.snapshot.json_invalid'), 400);
    }

    // Validar estructura esperada de wp-snapshot
    if (!isset($snapshotData['sections']) || !is_array($snapshotData['sections'])) {
        Response::error(Translator::t('admin_api.snapshot.missing_sections'), 422);
    }
    if (count($snapshotData['sections']) > 200) {
        Response::error(Translator::t('admin_api.snapshot.too_many_sections'), 422);
    }

    // Run standalone analyzer for preview
    try {
        $analyzer = new WpSnapshotAnalyzer($snapshotData);
        $analysis = $analyzer->analyze();
    } catch (Throwable $e) {
        Logger::error('WpSnapshotAnalyzer falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.snapshot.analyze_error', ['details' => $e->getMessage()]), 500);
    }

    // Save snapshot to DB (upsert) — comprimido con gzip
    $snapshotJson = JsonStore::encode($snapshotData);
    $analysisJson = JsonStore::encode($analysis);

    $existing = $db->queryOne("SELECT id FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    if ($existing) {
        $db->execute(
            "UPDATE wp_snapshots SET source = ?, source_url = ?, snapshot_json = ?, analysis_json = ?, created_at = datetime('now') WHERE audit_id = ?",
            ['upload', null, $snapshotJson, $analysisJson, $auditId]
        );
    } else {
        $db->execute(
            "INSERT INTO wp_snapshots (audit_id, source, source_url, snapshot_json, analysis_json) VALUES (?, ?, ?, ?, ?)",
            [$auditId, 'upload', null, $snapshotJson, $analysisJson]
        );
    }

    // Re-run full audit with snapshot data injected
    $reauditResult = null;
    try {
        $auditRow = $db->queryOne("SELECT url FROM audits WHERE id = ?", [$auditId]);
        if ($auditRow && !empty($auditRow['url'])) {
            set_time_limit(120);
            ini_set('memory_limit', '256M');
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $orchestrator = new AuditOrchestrator($auditRow['url'], [], $snapshotData);
            $reauditResult = $orchestrator->run();

            // Keep original audit ID and timestamp
            $reauditResult['id'] = $auditId;

            $resultForStorage = $reauditResult;
            $waterfallData = $reauditResult['waterfall'] ?? [];
            $extendedPerf = $reauditResult['extendedPerf'] ?? [];
            unset($resultForStorage['waterfall'], $resultForStorage['extendedPerf']);
            $resultJson = JsonStore::encode($resultForStorage);
            $perfData = [
                'waterfall' => $waterfallData,
                'crux' => $extendedPerf['crux'] ?? null,
                'resourceBreakdown' => $extendedPerf['resourceBreakdown'] ?? [],
                'lighthouseAudits' => $extendedPerf['lighthouseAudits'] ?? [],
                'lcpElement' => $extendedPerf['lcpElement'] ?? null,
                'clsElements' => $extendedPerf['clsElements'] ?? [],
                'mainThreadWork' => $extendedPerf['mainThreadWork'] ?? [],
            ];
            $waterfallJson = JsonStore::encode($perfData);

            try { $db->execute("ALTER TABLE audits ADD COLUMN waterfall_json TEXT"); } catch (Throwable $e) {}

            $db->execute(
                "UPDATE audits SET global_score = ?, global_level = ?, scan_duration_ms = ?, result_json = ?, waterfall_json = ? WHERE id = ?",
                [$reauditResult['globalScore'], $reauditResult['globalLevel'], $reauditResult['scanDurationMs'], $resultJson, $waterfallJson, $auditId]
            );

            Logger::info("Re-audit con snapshot completada para audit $auditId: score {$reauditResult['globalScore']}");
        }
    } catch (Throwable $e) {
        Logger::error("Re-audit con snapshot falló para audit $auditId: " . $e->getMessage());
    }

    Response::success([
        'ok' => true,
        'analysis' => $analysis,
        'reaudit' => $reauditResult !== null,
        'newScore' => $reauditResult['globalScore'] ?? null,
        'generatedAt' => $snapshotData['generated_at'] ?? null,
        'siteName' => $snapshotData['site_name'] ?? null,
    ]);
}
