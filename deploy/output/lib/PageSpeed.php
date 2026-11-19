<?php
/**
 * PageSpeed — cliente de Google PageSpeed Insights (API v5).
 *
 * Compartido por PerformanceAnalyzer (auditoría completa) y PerfLab
 * (laboratorio de rendimiento: mediciones repetidas solo de PSI). Antes
 * esta lógica vivía como métodos privados del analyzer; se extrajo tal
 * cual para no duplicar el parseo del waterfall, CrUX, etc.
 */
class PageSpeed {
    public const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Resuelve la API key de Google PageSpeed (env primero, luego DB)
     */
    public static function resolveApiKey(): string {
        $apiKey = env('GOOGLE_PAGESPEED_API_KEY', '');
        if (!empty($apiKey)) return $apiKey;

        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'google_pagespeed_api_key'");
            if ($row && !empty($row['value'])) {
                return $row['value'];
            }
        } catch (Throwable $e) {
            // Continuar sin key
        }
        return '';
    }

    /**
     * Construye la URL del endpoint de PageSpeed Insights para una estrategia.
     */
    public static function buildUrl(string $url, string $strategy, string $apiKey = '', string $locale = 'es'): string {
        $params = [
            'url' => $url,
            'category' => 'performance',
            'strategy' => $strategy,
            'locale' => $locale,
        ];
        if (!empty($apiKey)) {
            $params['key'] = $apiKey;
        }
        return self::ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * Parsea la respuesta cruda de PageSpeed en el formato interno.
     */
    public static function parse(array $response, string $strategy, string $url): array {
        $result = [
            'score' => null,
            'fcp' => null,
            'lcp' => null,
            'cls' => null,
            'tti' => null,
            'tbt' => null,
            'si' => null,
            'ttfb' => null,
        ];

        if (empty($response) || ($response['statusCode'] ?? 0) !== 200) {
            Logger::warning("PageSpeed API ($strategy) falló", [
                'status' => $response['statusCode'] ?? 0,
                'url' => $url,
            ]);
            return $result;
        }

        $data = json_decode($response['body'] ?? '', true);
        if ($data === null) {
            return $result;
        }

        // Score principal
        $result['score'] = isset($data['lighthouseResult']['categories']['performance']['score'])
            ? (int) round($data['lighthouseResult']['categories']['performance']['score'] * 100)
            : null;

        // Métricas individuales
        $audits = $data['lighthouseResult']['audits'] ?? [];

        $result['fcp'] = $audits['first-contentful-paint']['numericValue'] ?? null;
        $result['lcp'] = $audits['largest-contentful-paint']['numericValue'] ?? null;
        $result['cls'] = $audits['cumulative-layout-shift']['numericValue'] ?? null;
        $result['tti'] = $audits['interactive']['numericValue'] ?? null;
        $result['tbt'] = $audits['total-blocking-time']['numericValue'] ?? null;
        $result['si'] = $audits['speed-index']['numericValue'] ?? null;
        $result['ttfb'] = $audits['server-response-time']['numericValue'] ?? null;

        // Extraer oportunidades de mejora
        $opportunityKeys = [
            'render-blocking-resources',
            'uses-optimized-images',
            'uses-text-compression',
            'uses-responsive-images',
            'unused-javascript',
            'unused-css-rules',
            'offscreen-images',
            'efficiently-encode-images',
            'modern-image-formats',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'dom-size',
            'redirects',
            'uses-rel-preconnect',
            'server-response-time',
            'third-party-summary',
        ];

        $result['opportunities'] = [];
        foreach ($opportunityKeys as $key) {
            if (isset($audits[$key]) && isset($audits[$key]['score']) && $audits[$key]['score'] < 1) {
                $result['opportunities'][] = [
                    'id' => $key,
                    'title' => $audits[$key]['title'] ?? $key,
                    'displayValue' => $audits[$key]['displayValue'] ?? '',
                    'savings' => $audits[$key]['details']['overallSavingsMs'] ?? 0,
                ];
            }
        }

        // Extraer network-requests para waterfall
        $networkItems = $audits['network-requests']['details']['items'] ?? [];
        $result['networkRequests'] = [];
        foreach ($networkItems as $item) {
            $reqUrl = $item['url'] ?? '';
            if (empty($reqUrl) || str_starts_with($reqUrl, 'data:')) continue;

            // PageSpeed startTime/endTime son en ms relativos a timeOrigin
            $startTime = (float)($item['startTime'] ?? $item['networkRequestTime'] ?? 0);
            $endTime = (float)($item['endTime'] ?? $item['networkEndTime'] ?? 0);

            // Si los valores son < 100, probablemente están en segundos — convertir a ms
            if ($startTime > 0 && $startTime < 100) {
                $startTime *= 1000;
                $endTime *= 1000;
            }

            // Si endTime sigue inválido, estimar
            if ($endTime <= $startTime) {
                $transferSize = (int)($item['transferSize'] ?? 0);
                $endTime = $startTime + max(5, $transferSize / 50);
            }

            $result['networkRequests'][] = [
                'url' => $reqUrl,
                'resourceType' => $item['resourceType'] ?? 'Other',
                'startTime' => round($startTime, 1),
                'endTime' => round($endTime, 1),
                'transferSize' => (int)($item['transferSize'] ?? 0),
                'resourceSize' => (int)($item['resourceSize'] ?? 0),
                'statusCode' => (int)($item['statusCode'] ?? 0),
                'mimeType' => $item['mimeType'] ?? '',
                'protocol' => $item['protocol'] ?? '',
            ];
        }

        // Filtrar requests sin timing real
        $result['networkRequests'] = array_values(array_filter($result['networkRequests'], function ($req) {
            return $req['startTime'] > 0 || $req['endTime'] > 0;
        }));

        // CrUX data (real-user metrics from Chrome User Experience Report)
        $crux = $data['loadingExperience'] ?? [];
        if (!empty($crux['metrics'])) {
            $result['crux'] = [
                'overallCategory' => $crux['overall_category'] ?? null,
                'metrics' => [],
            ];
            $cruxMetricNames = [
                'LARGEST_CONTENTFUL_PAINT_MS' => 'LCP',
                'INTERACTION_TO_NEXT_PAINT' => 'INP',
                'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'CLS',
                'FIRST_CONTENTFUL_PAINT_MS' => 'FCP',
                'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'TTFB',
            ];
            foreach ($cruxMetricNames as $key => $label) {
                $m = $crux['metrics'][$key] ?? null;
                if ($m) {
                    $result['crux']['metrics'][] = [
                        'id' => $key,
                        'label' => $label,
                        'percentile' => $m['percentile'] ?? null,
                        'category' => $m['category'] ?? null,
                        'distributions' => $m['distributions'] ?? [],
                    ];
                }
            }
        }

        // Resource breakdown (from resource-summary audit)
        $resourceSummary = $audits['resource-summary']['details']['items'] ?? [];
        $result['resourceBreakdown'] = [];
        foreach ($resourceSummary as $item) {
            if (($item['transferSize'] ?? 0) > 0 || ($item['requestCount'] ?? 0) > 0) {
                $result['resourceBreakdown'][] = [
                    'resourceType' => $item['resourceType'] ?? $item['label'] ?? 'Other',
                    'label' => $item['label'] ?? $item['resourceType'] ?? 'Other',
                    'requestCount' => (int)($item['requestCount'] ?? 0),
                    'transferSize' => (int)($item['transferSize'] ?? 0),
                ];
            }
        }

        // LCP element
        $lcpElement = $audits['largest-contentful-paint-element']['details']['items'][0] ?? null;
        if ($lcpElement) {
            $node = $lcpElement['node'] ?? [];
            $result['lcpElement'] = [
                'selector' => $node['selector'] ?? '',
                'snippet' => $node['snippet'] ?? '',
                'nodeLabel' => $node['nodeLabel'] ?? '',
            ];
        }

        // CLS elements
        $clsItems = $audits['layout-shift-elements']['details']['items'] ?? [];
        $result['clsElements'] = [];
        foreach (array_slice($clsItems, 0, 5) as $item) {
            $node = $item['node'] ?? [];
            $result['clsElements'][] = [
                'selector' => $node['selector'] ?? '',
                'snippet' => $node['snippet'] ?? '',
                'nodeLabel' => $node['nodeLabel'] ?? '',
                'score' => $item['score'] ?? 0,
            ];
        }

        // Main thread work breakdown
        $mainThread = $audits['mainthread-work-breakdown']['details']['items'] ?? [];
        $result['mainThreadWork'] = [];
        foreach (array_slice($mainThread, 0, 8) as $item) {
            $result['mainThreadWork'][] = [
                'group' => $item['groupLabel'] ?? $item['group'] ?? '',
                'duration' => round($item['duration'] ?? 0),
            ];
        }

        // All Lighthouse audits with scores (for Structure tab)
        $result['lighthouseAudits'] = [];
        $auditCategories = [
            'performance' => $data['lighthouseResult']['categories']['performance']['auditRefs'] ?? [],
        ];
        foreach ($auditCategories['performance'] as $ref) {
            $auditId = $ref['id'] ?? '';
            $audit = $audits[$auditId] ?? null;
            if (!$audit || !isset($audit['score'])) continue;
            $score = $audit['score'];
            // Skip informational audits (score = null) and perfect scores unless they have details
            $impact = 'none';
            if ($score === null) $impact = 'info';
            elseif ($score < 0.5) $impact = 'high';
            elseif ($score < 0.9) $impact = 'medium';
            elseif ($score < 1) $impact = 'low';

            $result['lighthouseAudits'][] = [
                'id' => $auditId,
                'title' => $audit['title'] ?? $auditId,
                'description' => $audit['description'] ?? '',
                'score' => $score,
                'impact' => $impact,
                'displayValue' => $audit['displayValue'] ?? '',
                'metricSavings' => $ref['relevantAudits'] ?? [],
                'group' => $ref['group'] ?? '',
                'weight' => $ref['weight'] ?? 0,
            ];
        }
        // Sort by impact: high first, then medium, low, none
        usort($result['lighthouseAudits'], function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3, 'none' => 4];
            return ($order[$a['impact']] ?? 5) - ($order[$b['impact']] ?? 5);
        });

        return $result;
    }

    /**
     * Mensaje de error legible de una respuesta fallida de PSI, o null si
     * la respuesta es válida. PSI devuelve {error:{code,message}} en 4xx/5xx
     * (cuota agotada, URL que Lighthouse no pudo cargar, etc.).
     */
    public static function errorMessage(array $response): ?string {
        $status = (int) ($response['statusCode'] ?? 0);
        if ($status === 200) return null;
        if ($status === 0) return 'timeout';
        $data = json_decode($response['body'] ?? '', true);
        $msg = is_array($data) ? ($data['error']['message'] ?? '') : '';
        $msg = trim(strip_tags((string) $msg));
        if (mb_strlen($msg) > 400) $msg = mb_substr($msg, 0, 400) . '…';
        return $msg !== '' ? "HTTP $status: $msg" : "HTTP $status";
    }
}
