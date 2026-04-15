<?php
/**
 * GET /api/history.php?domain=ejemplo.com — Historial de auditorías de un dominio
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', 405);
}

$domain = trim($_GET['domain'] ?? '');
if (empty($domain)) {
    Response::error('El parámetro domain es obligatorio.');
}

try {
    $db = Database::getInstance();
    $rows = $db->query(
        "SELECT id, global_score, global_level, result_json, created_at FROM audits WHERE domain = ? ORDER BY created_at DESC LIMIT 12",
        [$domain]
    );

    $history = [];
    foreach ($rows as $row) {
        $result = json_decode($row['result_json'], true);
        $moduleScores = [];
        foreach ($result['modules'] ?? [] as $mod) {
            $moduleScores[$mod['id']] = $mod['score'] ?? 0;
        }
        $history[] = [
            'id' => $row['id'],
            'globalScore' => (int) $row['global_score'],
            'globalLevel' => $row['global_level'],
            'moduleScores' => $moduleScores,
            'createdAt' => $row['created_at'],
        ];
    }

    // Calcular tendencia
    $trend = 'insufficient_data';
    if (count($history) >= 2) {
        $diff = $history[0]['globalScore'] - $history[1]['globalScore'];
        if ($diff > 5) $trend = 'improving';
        elseif ($diff < -5) $trend = 'declining';
        else $trend = 'stable';
    }

    Response::success([
        'domain' => $domain,
        'totalAudits' => count($history),
        'history' => $history,
        'trend' => $trend,
    ]);
} catch (Throwable $e) {
    Logger::error('Error en history: ' . $e->getMessage());
    Response::error('Error al obtener historial.', 500);
}
