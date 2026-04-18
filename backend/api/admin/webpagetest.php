<?php
/**
 * WebPageTest deep analysis
 * POST /api/admin/webpagetest.php — submit a new test
 *   Body: { url: string }
 *   Returns: { testId: string, status: 'submitted' }
 *
 * GET /api/admin/webpagetest.php?testId=X — check status / get results
 *   Returns: { status: 'pending'|'running'|'completed', data?: {...} }
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// Get API key from settings or .env
function getWptKey(): string {
    $key = env('WEBPAGETEST_API_KEY', '');
    if (empty($key)) {
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE key = 'webpagetest_api_key'");
            if ($row && !empty($row['value'])) $key = $row['value'];
        } catch (Throwable $e) {}
    }
    return $key;
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $url = $body['url'] ?? '';

    if (empty($url)) {
        Response::error('URL requerida', 400);
    }

    $apiKey = getWptKey();
    if (empty($apiKey)) {
        Response::error('WebPageTest API key no configurada. WebPageTest (ahora Catchpoint) requiere un plan de pago para acceso a la API. Configura tu key en Ajustes > API Keys si tienes una.', 400);
    }

    // Submit test
    $params = [
        'url' => $url,
        'f' => 'json',
        'k' => $apiKey,
        'runs' => 1,
        'fvonly' => 1,
        'location' => 'Dulles:Chrome',
    ];

    $submitUrl = 'https://www.webpagetest.org/runtest.php?' . http_build_query($params);
    $response = Fetcher::get($submitUrl, 15, true, 1);

    if ($response['statusCode'] !== 200) {
        Response::error('Error al enviar test a WebPageTest', 500);
    }

    $data = json_decode($response['body'], true);
    if (!$data || ($data['statusCode'] ?? 0) < 200 || ($data['statusCode'] ?? 0) >= 300) {
        Response::error($data['statusText'] ?? 'Error desconocido de WebPageTest', 500);
    }

    $testId = $data['data']['testId'] ?? null;
    if (!$testId) {
        Response::error('No se recibió testId de WebPageTest', 500);
    }

    Response::success([
        'testId' => $testId,
        'status' => 'submitted',
        'pollUrl' => $data['data']['jsonUrl'] ?? null,
    ]);
}

if ($method === 'GET') {
    $testId = $_GET['testId'] ?? '';
    if (empty($testId)) {
        Response::error('testId requerido', 400);
    }

    // Check status / get results
    $resultUrl = "https://www.webpagetest.org/jsonResult.php?test=" . urlencode($testId);
    $response = Fetcher::get($resultUrl, 15, true, 0);

    if ($response['statusCode'] !== 200) {
        Response::error('Error al consultar WebPageTest', 500);
    }

    $data = json_decode($response['body'], true);
    if (!$data) {
        Response::error('Respuesta inválida de WebPageTest', 500);
    }

    $statusCode = $data['statusCode'] ?? 0;

    // 1XX = test pending/running
    if ($statusCode >= 100 && $statusCode < 200) {
        Response::success([
            'status' => 'running',
            'statusText' => $data['statusText'] ?? 'En progreso...',
        ]);
    }

    // 4XX = error
    if ($statusCode >= 400) {
        Response::error($data['statusText'] ?? 'Test falló', 500);
    }

    // 200 = completed — extract HAR-like data
    $run = $data['data']['runs']['1']['firstView'] ?? [];
    $requests = $run['requests'] ?? [];

    $waterfall = [];
    foreach ($requests as $req) {
        $waterfall[] = [
            'url' => $req['full_url'] ?? $req['url'] ?? '',
            'statusCode' => (int)($req['responseCode'] ?? 0),
            'resourceType' => mapContentType($req['contentType'] ?? ''),
            'startTime' => (float)($req['load_start'] ?? 0),
            'endTime' => (float)(($req['load_start'] ?? 0) + ($req['load_ms'] ?? 0)),
            'transferSize' => (int)($req['bytesIn'] ?? 0),
            'resourceSize' => (int)($req['objectSize'] ?? 0),
            'mimeType' => $req['contentType'] ?? '',
            'protocol' => $req['protocol'] ?? '',
            // Detailed timing breakdown — the key advantage over PageSpeed
            'dns' => (float)($req['dns_ms'] ?? 0),
            'connect' => (float)($req['connect_ms'] ?? 0),
            'ssl' => (float)($req['ssl_ms'] ?? 0),
            'ttfb' => (float)($req['ttfb_ms'] ?? 0),
            'download' => (float)($req['download_ms'] ?? 0),
        ];
    }

    // Summary metrics
    $summary = [
        'loadTime' => $run['loadTime'] ?? 0,
        'fullyLoaded' => $run['fullyLoaded'] ?? 0,
        'ttfb' => $run['TTFB'] ?? 0,
        'bytesIn' => $run['bytesIn'] ?? 0,
        'requests' => $run['requestsFull'] ?? count($requests),
        'domElements' => $run['domElements'] ?? 0,
        'firstPaint' => $run['firstPaint'] ?? 0,
        'domContentLoaded' => $run['domContentLoadedEventStart'] ?? 0,
    ];

    Response::success([
        'status' => 'completed',
        'testId' => $testId,
        'summary' => $summary,
        'waterfall' => $waterfall,
        'webpagetestUrl' => "https://www.webpagetest.org/result/$testId/",
    ]);
}

function mapContentType(string $contentType): string {
    if (str_contains($contentType, 'html')) return 'Document';
    if (str_contains($contentType, 'css')) return 'Stylesheet';
    if (str_contains($contentType, 'javascript') || str_contains($contentType, 'js')) return 'Script';
    if (str_contains($contentType, 'image') || str_contains($contentType, 'svg')) return 'Image';
    if (str_contains($contentType, 'font') || str_contains($contentType, 'woff') || str_contains($contentType, 'ttf')) return 'Font';
    if (str_contains($contentType, 'json') || str_contains($contentType, 'xml')) return 'XHR';
    if (str_contains($contentType, 'video') || str_contains($contentType, 'audio')) return 'Media';
    return 'Other';
}
