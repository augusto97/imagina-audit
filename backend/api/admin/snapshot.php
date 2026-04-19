<?php
/**
 * Snapshot de WordPress (plugin wp-snapshot)
 *
 * POST /api/admin/snapshot.php
 *   Body: { auditId, source: 'url'|'upload', shareUrl?, jsonData? }
 *   Guarda el snapshot para una auditoría y genera análisis interno.
 *
 * GET /api/admin/snapshot.php?audit_id=X
 *   Retorna el snapshot + análisis de una auditoría.
 *
 * DELETE /api/admin/snapshot.php?audit_id=X
 *   Elimina el snapshot de una auditoría.
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

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
    if (empty($auditId)) Response::error('audit_id requerido', 400);

    $row = $db->queryOne("SELECT source, source_url, snapshot_json, analysis_json, created_at FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    if (!$row) Response::success(null);

    Response::success([
        'source' => $row['source'],
        'sourceUrl' => $row['source_url'],
        'snapshot' => json_decode($row['snapshot_json'], true),
        'analysis' => $row['analysis_json'] ? json_decode($row['analysis_json'], true) : null,
        'createdAt' => $row['created_at'],
    ]);
}

if ($method === 'DELETE') {
    $auditId = $_GET['audit_id'] ?? '';
    if (empty($auditId)) Response::error('audit_id requerido', 400);
    $db->execute("DELETE FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    Response::success(['ok' => true]);
}

if ($method === 'POST') {
    // Snapshots del plugin wp-snapshot pueden ser grandes (29 métricas con listas
    // de plugins, DB stats, etc.). Tope de 10MB + profundidad 128.
    $body = Response::getJsonBody(10 * 1024 * 1024, 128);
    $auditId = $body['auditId'] ?? '';
    $source = $body['source'] ?? '';

    if (empty($auditId)) Response::error('auditId requerido', 400);
    if (!in_array($source, ['url', 'upload'])) Response::error('source debe ser url o upload', 400);

    $snapshotData = null;
    $sourceUrl = null;

    if ($source === 'url') {
        $shareUrl = trim($body['shareUrl'] ?? '');
        if (empty($shareUrl)) Response::error('shareUrl requerido', 400);

        // Validate URL format
        if (!filter_var($shareUrl, FILTER_VALIDATE_URL)) {
            Response::error('URL inválida', 400);
        }

        // Verify it's a wp-snapshot share URL
        if (!preg_match('#/site-audit-snapshot/share/[a-f0-9]{64}/?$#', rtrim($shareUrl, '/') . '/')) {
            Response::error('No parece una URL válida de wp-snapshot. Debe terminar en /site-audit-snapshot/share/{token}', 400);
        }

        $sourceUrl = $shareUrl;

        // Fetch the share page — the plugin renders it as HTML with embedded JSON
        try {
            $response = Fetcher::get($shareUrl, 15, true, 1);
        } catch (Throwable $e) {
            Response::error('Error al obtener el snapshot: ' . $e->getMessage(), 500);
        }

        if ($response['statusCode'] !== 200) {
            Response::error("Error al obtener el snapshot (HTTP {$response['statusCode']}). El enlace puede haber expirado.", 500);
        }

        $html = $response['body'];

        // Extract the JSON from the HTML. The plugin embeds it in a script tag or data attribute.
        // Try common patterns
        if (preg_match('/<script[^>]*id=["\']wps-snapshot-data["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            $snapshotData = json_decode(trim($m[1]), true);
        } elseif (preg_match('/<script[^>]*type=["\']application\/json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            $snapshotData = json_decode(trim($m[1]), true);
        } elseif (preg_match('/window\.wpsSnapshot\s*=\s*({.*?});/s', $html, $m)) {
            $snapshotData = json_decode($m[1], true);
        }

        if ($snapshotData === null) {
            Response::error('No se pudo extraer el JSON del snapshot de la URL. Verifica que el plugin wp-snapshot esté actualizado, o descarga el JSON y súbelo manualmente.', 422);
        }
    } else {
        // source = 'upload'
        $jsonData = $body['jsonData'] ?? null;
        if (empty($jsonData)) Response::error('jsonData requerido', 400);

        if (is_string($jsonData)) {
            // Verificar tamaño antes de decodificar
            if (strlen($jsonData) > 10 * 1024 * 1024) {
                Response::error('jsonData excede el tope de 10MB', 413);
            }
            $snapshotData = json_decode($jsonData, true, 128);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::error('JSON inválido: ' . json_last_error_msg(), 400);
            }
        } elseif (is_array($jsonData)) {
            $snapshotData = $jsonData;
        }

        if (!is_array($snapshotData)) {
            Response::error('JSON inválido', 400);
        }
    }

    // Validate snapshot structure de wp-snapshot
    if (!isset($snapshotData['sections']) || !is_array($snapshotData['sections'])) {
        Response::error('El JSON no tiene la estructura esperada de wp-snapshot (falta "sections").', 422);
    }
    // Límite defensivo sobre el número de secciones (el plugin tiene ~29 fijas)
    if (count($snapshotData['sections']) > 200) {
        Response::error('El snapshot tiene demasiadas secciones (posible payload malicioso).', 422);
    }

    // Run standalone analyzer for preview
    try {
        $analyzer = new WpSnapshotAnalyzer($snapshotData);
        $analysis = $analyzer->analyze();
    } catch (Throwable $e) {
        Logger::error('WpSnapshotAnalyzer falló: ' . $e->getMessage());
        Response::error('Error al analizar el snapshot: ' . $e->getMessage(), 500);
    }

    // Save snapshot to DB (upsert)
    $snapshotJson = json_encode($snapshotData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $analysisJson = json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $existing = $db->queryOne("SELECT id FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
    if ($existing) {
        $db->execute(
            "UPDATE wp_snapshots SET source = ?, source_url = ?, snapshot_json = ?, analysis_json = ?, created_at = datetime('now') WHERE audit_id = ?",
            [$source, $sourceUrl, $snapshotJson, $analysisJson, $auditId]
        );
    } else {
        $db->execute(
            "INSERT INTO wp_snapshots (audit_id, source, source_url, snapshot_json, analysis_json) VALUES (?, ?, ?, ?, ?)",
            [$auditId, $source, $sourceUrl, $snapshotJson, $analysisJson]
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
            $resultJson = json_encode($resultForStorage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $perfData = [
                'waterfall' => $waterfallData,
                'crux' => $extendedPerf['crux'] ?? null,
                'resourceBreakdown' => $extendedPerf['resourceBreakdown'] ?? [],
                'lighthouseAudits' => $extendedPerf['lighthouseAudits'] ?? [],
                'lcpElement' => $extendedPerf['lcpElement'] ?? null,
                'clsElements' => $extendedPerf['clsElements'] ?? [],
                'mainThreadWork' => $extendedPerf['mainThreadWork'] ?? [],
            ];
            $waterfallJson = json_encode($perfData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
