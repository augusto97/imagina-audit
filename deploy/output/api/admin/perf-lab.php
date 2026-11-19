<?php
/**
 * /api/admin/perf-lab.php — Laboratorio de rendimiento (solo admin).
 *
 *   GET    ?action=list[&url=]      → { runs, urls, hasApiKey }
 *   GET    ?action=get&id=N         → detalle + resultado + prueba anterior
 *   POST   {action:'create', url, strategy, runs, note?}
 *   POST   {action:'step', id}      → ejecuta la siguiente ronda (≤ ~90s)
 *   POST   {action:'note', id, note}
 *   DELETE ?id=N
 *
 * No crea auditorías, leads ni emails, y no usa la caché de 24h: cada
 * prueba mide de nuevo contra PageSpeed Insights.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();
// Un step dura lo que tarde PSI (20-60s). Sin soltar el lock de sesión
// todo el admin quedaría bloqueado mientras mide.
session_write_close();

$lab = new PerfLab();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    if ($action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        $run = $id > 0 ? $lab->get($id) : null;
        if (!$run) Response::error(Translator::t('admin_api.perf_lab.not_found'), 404);
        Response::success($run);
    }
    $url = trim((string) ($_GET['url'] ?? ''));
    Response::success([
        'runs' => $lab->listRuns($url !== '' ? $url : null, (int) ($_GET['limit'] ?? 100)),
        'urls' => $lab->recentUrls(),
        'hasApiKey' => PerfLab::hasApiKey(),
    ]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) Response::error(Translator::t('admin_api.common.id_required'));
    $lab->delete($id);
    Response::success();
}

if ($method !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$body = Response::getJsonBody();
$action = (string) ($body['action'] ?? '');

switch ($action) {
    case 'create':
        $url = trim((string) ($body['url'] ?? ''));
        if ($url === '') Response::error(Translator::t('api.audit.url_required'));
        try {
            $url = UrlValidator::validate($url);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage());
        }
        $strategy = (string) ($body['strategy'] ?? 'mobile');
        if (!in_array($strategy, PerfLab::STRATEGIES, true)) {
            Response::error(Translator::t('admin_api.perf_lab.invalid_strategy'));
        }
        $runs = (int) ($body['runs'] ?? 1);
        if (!in_array($runs, [1, 3], true)) $runs = 1;
        Response::success($lab->create($url, $strategy, $runs, (string) ($body['note'] ?? '')));
        break;

    case 'step':
        $id = (int) ($body['id'] ?? 0);
        $run = $id > 0 ? $lab->step($id) : null;
        if (!$run) Response::error(Translator::t('admin_api.perf_lab.not_found'), 404);
        Response::success($run);
        break;

    case 'note':
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0 || !$lab->updateNote($id, (string) ($body['note'] ?? ''))) {
            Response::error(Translator::t('admin_api.perf_lab.not_found'), 404);
        }
        Response::success();
        break;

    default:
        Response::error(Translator::t('admin_api.perf_lab.invalid_action'), 400);
}
