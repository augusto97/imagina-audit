<?php
/**
 * GET /api/admin/snapshot-report.php?audit_id=X
 *
 * Devuelve el snapshot crudo + un reporte estructurado por secciones,
 * cada una con: resumen agregado, lista detallada, y (para plugins)
 * cruce con vulnerabilidades conocidas vía WPVulnerability API.
 *
 * Está pensado para alimentar la pestaña "Análisis interno" del admin:
 * el frontend solo tiene que renderizar cards pre-procesadas, sin lógica
 * de derivación sobre el JSON crudo.
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$auditId = $_GET['audit_id'] ?? '';
if (empty($auditId)) Response::error('audit_id requerido', 400);

$db = Database::getInstance();
$row = $db->queryOne("SELECT source, source_url, snapshot_json, created_at FROM wp_snapshots WHERE audit_id = ?", [$auditId]);
if (!$row) Response::success(null);

$snapshot = JsonStore::decode($row['snapshot_json']);
if (!is_array($snapshot) || empty($snapshot['sections'])) {
    Response::error('Snapshot corrupto en DB.', 500);
}

try {
    $report = (new SnapshotReportBuilder($snapshot))->build();
} catch (Throwable $e) {
    Logger::error('SnapshotReportBuilder falló: ' . $e->getMessage());
    Response::error('Error construyendo el reporte: ' . $e->getMessage(), 500);
}

Response::success([
    'meta' => [
        'siteName' => $snapshot['site_name'] ?? '',
        'siteUrl' => $snapshot['site_url'] ?? '',
        'generatedAt' => $snapshot['generated_at'] ?? '',
        'generatorVersion' => $snapshot['generator_version'] ?? '',
        'uploadedAt' => $row['created_at'],
    ],
    'report' => $report,
]);
