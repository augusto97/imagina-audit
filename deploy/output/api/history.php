<?php
/**
 * GET /api/history.php?domain=ejemplo.com — Historial de auditorías de un dominio
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$domain = trim($_GET['domain'] ?? '');
if (empty($domain)) {
    Response::error(Translator::t('api.history.domain_required'));
}

try {
    $db = Database::getInstance();
    // Antes el endpoint era totalmente público y devolvía audits de
    // cualquier dueño para cualquier dominio. Esto leakeaba a competidores
    // los hallazgos técnicos de un dominio dado más los IDs (utilizables
    // contra audit-status.php). Ahora el alcance se decide por sesión:
    //   - admin autenticado → todos los audits del dominio
    //   - user autenticado → solo audits propios del dominio
    //   - anónimo → solo audits anónimos (user_id IS NULL) y no borrados
    $authUser = UserAuth::checkAuth();
    $isAdmin = Auth::checkAuth();
    if ($isAdmin) {
        $rows = $db->query(
            "SELECT id, global_score, global_level, result_json, created_at FROM audits WHERE domain = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT 12",
            [$domain]
        );
    } elseif ($authUser) {
        $rows = $db->query(
            "SELECT id, global_score, global_level, result_json, created_at FROM audits WHERE domain = ? AND user_id = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT 12",
            [$domain, (int) $authUser['id']]
        );
    } else {
        $rows = $db->query(
            "SELECT id, global_score, global_level, result_json, created_at FROM audits WHERE domain = ? AND user_id IS NULL AND is_deleted = 0 ORDER BY created_at DESC LIMIT 12",
            [$domain]
        );
    }

    $history = [];
    foreach ($rows as $row) {
        $result = JsonStore::decode($row['result_json']) ?? [];
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
    Response::error(Translator::t('api.history.fetch_error'), 500);
}
