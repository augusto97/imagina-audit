<?php
/**
 * Analiza el rendimiento del sitio usando Google PageSpeed Insights API
 * También verifica compresión, cache y TTFB propios
 */

class PerformanceAnalyzer {
    private string $url;
    private array $headers;
    private float $fetchTime;

    // Datos compartidos con otros analyzers
    private ?int $mobileScore = null;
    private ?float $lcp = null;
    private ?array $mobileData = null;

    public function __construct(string $url, array $headers, float $fetchTime = 0) {
        $this->url = rtrim($url, '/');
        $this->headers = $headers;
        $this->fetchTime = $fetchTime;
    }

    /**
     * Ejecuta el análisis de rendimiento
     */
    public function analyze(): array {
        $metrics = [];

        // Consultar PageSpeed API (mobile y desktop)
        $mobileResult = $this->queryPageSpeed('mobile');
        $desktopResult = $this->queryPageSpeed('desktop');

        // Score PageSpeed Mobile
        $this->mobileScore = $mobileResult['score'] ?? null;
        $this->mobileData = $mobileResult;

        $metrics[] = Scoring::createMetric(
            'pagespeed_mobile',
            'PageSpeed Mobile',
            $this->mobileScore,
            $this->mobileScore !== null ? "{$this->mobileScore}/100" : 'No disponible',
            $this->mobileScore ?? 50,
            $this->mobileScore !== null
                ? "Google PageSpeed califica tu sitio móvil con {$this->mobileScore}/100."
                : 'No fue posible obtener la puntuación de PageSpeed móvil.',
            ($this->mobileScore !== null && $this->mobileScore < 70) ? 'Optimizar la velocidad de carga en dispositivos móviles.' : '',
            'Optimizamos tu sitio para obtener scores de 90+ en PageSpeed.'
        );

        // Score PageSpeed Desktop
        $desktopScore = $desktopResult['score'] ?? null;
        $metrics[] = Scoring::createMetric(
            'pagespeed_desktop',
            'PageSpeed Desktop',
            $desktopScore,
            $desktopScore !== null ? "{$desktopScore}/100" : 'No disponible',
            $desktopScore ?? 50,
            $desktopScore !== null
                ? "Google PageSpeed califica tu sitio desktop con {$desktopScore}/100."
                : 'No fue posible obtener la puntuación de PageSpeed desktop.',
            ($desktopScore !== null && $desktopScore < 70) ? 'Optimizar la velocidad de carga en escritorio.' : '',
            'Configuramos cache, CDN y optimizaciones avanzadas de rendimiento.'
        );

        // LCP
        $this->lcp = $mobileResult['lcp'] ?? $desktopResult['lcp'] ?? null;
        if ($this->lcp !== null) {
            $lcpSeconds = round($this->lcp / 1000, 1);
            $lcpScore = $this->lcp <= 2500 ? 100 : ($this->lcp <= 4000 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'lcp',
                'Largest Contentful Paint (LCP)',
                $this->lcp,
                "{$lcpSeconds}s",
                $lcpScore,
                "El contenido principal tarda {$lcpSeconds}s en cargar. " .
                ($this->lcp <= 2500 ? 'Buen tiempo de carga.' : 'Se recomienda menos de 2.5 segundos.'),
                $this->lcp > 2500 ? 'Optimizar imágenes, lazy loading y reducir recursos bloqueantes.' : '',
                'Reducimos el LCP con cache, CDN, optimización de imágenes y código.'
            );
        }

        // FCP
        $fcp = $mobileResult['fcp'] ?? null;
        if ($fcp !== null) {
            $fcpSeconds = round($fcp / 1000, 1);
            $fcpScore = $fcp <= 1800 ? 100 : ($fcp <= 3000 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'fcp',
                'First Contentful Paint (FCP)',
                $fcp,
                "{$fcpSeconds}s",
                $fcpScore,
                "El primer contenido visible aparece en {$fcpSeconds}s.",
                $fcp > 1800 ? 'Reducir recursos bloqueantes y optimizar CSS crítico.' : '',
                'Implementamos CSS crítico inline y optimización de carga.'
            );
        }

        // CLS
        $cls = $mobileResult['cls'] ?? null;
        if ($cls !== null) {
            $clsScore = $cls <= 0.1 ? 100 : ($cls <= 0.25 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'cls',
                'Cumulative Layout Shift (CLS)',
                $cls,
                number_format($cls, 3),
                $clsScore,
                "El desplazamiento visual acumulado es $cls. " .
                ($cls <= 0.1 ? 'Buen valor.' : 'Se recomienda menos de 0.1.'),
                $cls > 0.1 ? 'Definir dimensiones para imágenes y embeds. Evitar insertar contenido dinámico arriba.' : '',
                'Eliminamos los shifts de layout para una experiencia visual estable.'
            );
        }

        // TBT
        $tbt = $mobileResult['tbt'] ?? null;
        if ($tbt !== null) {
            $tbtMs = round($tbt);
            $tbtScore = $tbt <= 200 ? 100 : ($tbt <= 600 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'tbt',
                'Total Blocking Time (TBT)',
                $tbt,
                "{$tbtMs}ms",
                $tbtScore,
                "El tiempo de bloqueo total es {$tbtMs}ms. " .
                ($tbt <= 200 ? 'Buen valor.' : 'Se recomienda menos de 200ms.'),
                $tbt > 200 ? 'Reducir el JavaScript pesado y dividir tareas largas.' : '',
                'Optimizamos el JavaScript y eliminamos scripts innecesarios.'
            );
        }

        // Oportunidades de mejora de PageSpeed
        $opportunities = $this->extractOpportunities($mobileResult);
        if (!empty($opportunities)) {
            $oppCount = count($opportunities);
            $totalSavings = 0;
            $oppDetails = [];
            foreach ($opportunities as $opp) {
                $totalSavings += $opp['savings'];
                $oppDetails[] = $opp['title'] . ($opp['savings'] > 0 ? ' (-' . round($opp['savings'] / 1000, 1) . 's)' : '');
            }
            $oppScore = $oppCount <= 2 ? 80 : ($oppCount <= 4 ? 55 : 25);
            $savingsText = $totalSavings > 0 ? round($totalSavings / 1000, 1) . 's de ahorro potencial' : '';

            $metrics[] = Scoring::createMetric(
                'pagespeed_opportunities',
                'Oportunidades de mejora',
                $oppCount,
                "$oppCount oportunidades" . ($savingsText ? " · $savingsText" : ''),
                $oppScore,
                "Google detectó $oppCount oportunidades de optimización: " . implode('; ', array_slice($oppDetails, 0, 5)) . ($oppCount > 5 ? "... y " . ($oppCount - 5) . " más." : '.'),
                'Aplicar las optimizaciones sugeridas por PageSpeed para mejorar la velocidad de carga.',
                'Implementamos todas las optimizaciones recomendadas por Google PageSpeed.',
                ['opportunities' => $opportunities]
            );
        }

        // TTFB propio
        $ttfb = $this->fetchTime;
        $ttfbScore = $ttfb <= 200 ? 100 : ($ttfb <= 500 ? 80 : ($ttfb <= 800 ? 50 : 20));
        $metrics[] = Scoring::createMetric(
            'ttfb',
            'Tiempo de respuesta del servidor (TTFB)',
            $ttfb,
            round($ttfb) . 'ms',
            $ttfbScore,
            "El servidor responde en " . round($ttfb) . "ms. " .
            ($ttfb <= 500 ? 'Buen tiempo.' : 'Se recomienda menos de 500ms.'),
            $ttfb > 500 ? 'Mejorar el hosting, habilitar cache de servidor y optimizar consultas a base de datos.' : '',
            'Recomendamos hosting optimizado y configuramos cache de servidor.'
        );

        // Compresión
        $encoding = $this->headers['content-encoding'] ?? '';
        $hasCompression = !empty($encoding);
        $compressionScore = $hasCompression ? 100 : 30;
        $metrics[] = Scoring::createMetric(
            'compression',
            'Compresión de contenido',
            $hasCompression,
            $hasCompression ? strtoupper($encoding) : 'Sin compresión',
            $compressionScore,
            $hasCompression
                ? "El contenido se sirve con compresión $encoding."
                : 'El contenido no está comprimido. Esto aumenta el tiempo de descarga.',
            $hasCompression ? '' : 'Habilitar compresión GZIP o Brotli en el servidor.',
            'Configuramos compresión Brotli/GZIP para reducir el tamaño de transferencia.'
        );

        // Cache headers
        $cacheControl = $this->headers['cache-control'] ?? '';
        $hasEtag = isset($this->headers['etag']);
        $hasExpires = isset($this->headers['expires']);
        $hasCacheControl = !empty($cacheControl);

        // Detectar cache de plugins WP por headers específicos
        $hasCachePlugin = isset($this->headers['x-litespeed-cache'])
            || isset($this->headers['x-cache-handler'])
            || isset($this->headers['x-wp-cf-super-cache'])
            || isset($this->headers['x-srcache-fetch-status'])
            || isset($this->headers['x-nitropack-cache'])
            || isset($this->headers['x-proxy-cache'])
            || (isset($this->headers['x-cache']) && stripos($this->headers['x-cache'], 'HIT') !== false)
            || (isset($this->headers['cf-cache-status']) && stripos($this->headers['cf-cache-status'], 'HIT') !== false);

        // Detectar por comentarios HTML que dejan los plugins de cache
        $htmlStr = $this->headers['_html'] ?? '';
        $hasCacheComment = false;
        if (!empty($htmlStr)) {
            $hasCacheComment = str_contains($htmlStr, '<!-- This website is like a Rocket')
                || str_contains($htmlStr, '<!-- Performance optimized by W3 Total Cache')
                || str_contains($htmlStr, '<!-- WP Fastest Cache')
                || str_contains($htmlStr, '<!-- Starter starter starter')
                || str_contains($htmlStr, '<!-- Cache served by LiteSpeed')
                || str_contains($htmlStr, '<!-- Super Cache')
                || str_contains($htmlStr, '<!-- Starter starter starter')
                || str_contains($htmlStr, '<!-- Starter starter starter')
                || str_contains($htmlStr, '<!-- Starter starter starter')
                || str_contains($htmlStr, 'data-rocket-')
                || str_contains($htmlStr, 'rocket-lazyload');
        }

        $hasCache = $hasCacheControl || $hasEtag || $hasExpires || $hasCachePlugin || $hasCacheComment;

        $cacheDetails = [];
        if ($hasCacheControl) $cacheDetails[] = "Cache-Control: $cacheControl";
        if ($hasEtag) $cacheDetails[] = 'ETag presente';
        if ($hasExpires) $cacheDetails[] = 'Expires: ' . ($this->headers['expires'] ?? '');
        if ($hasCachePlugin) $cacheDetails[] = 'Plugin de cache activo (headers de servidor)';
        if ($hasCacheComment) $cacheDetails[] = 'Plugin de cache detectado en HTML';

        $cacheScore = $hasCache ? 100 : 40;
        $cacheDisplay = $hasCache ? implode(' · ', array_slice($cacheDetails, 0, 2)) : 'No configurado';

        $metrics[] = Scoring::createMetric(
            'cache_headers',
            'Cache del navegador',
            $hasCache,
            $cacheDisplay,
            $cacheScore,
            $hasCache
                ? 'Cache configurado: ' . implode('. ', $cacheDetails) . '. Los archivos se almacenan para cargas más rápidas.'
                : 'No se detectaron headers de cache ni plugin de cache activo. El navegador descarga todo cada vez.',
            $hasCache ? '' : 'Instalar un plugin de cache (WP Rocket, LiteSpeed Cache) y configurar headers Cache-Control.',
            'Configuramos cache agresivo para archivos estáticos con expiración optimizada.',
            ['details' => $cacheDetails, 'hasCachePlugin' => $hasCachePlugin, 'hasCacheComment' => $hasCacheComment]
        );

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'performance',
            'name' => 'Rendimiento',
            'icon' => 'gauge',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_performance'],
            'metrics' => $metrics,
            'summary' => "Tu sitio tiene una puntuación de rendimiento de $score/100.",
            'salesMessage' => $defaults['sales_performance'],
        ];
    }

    /**
     * Consulta la API de Google PageSpeed Insights
     */
    private function queryPageSpeed(string $strategy): array {
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

        $apiKey = env('GOOGLE_PAGESPEED_API_KEY', '');

        // Si no hay key en .env, intentar obtenerla de la tabla settings
        if (empty($apiKey)) {
            try {
                $db = Database::getInstance();
                $row = $db->queryOne("SELECT value FROM settings WHERE key = 'google_pagespeed_api_key'");
                if ($row && !empty($row['value'])) {
                    $apiKey = $row['value'];
                }
            } catch (Throwable $e) {
                // Continuar sin key
            }
        }

        $params = [
            'url' => $this->url,
            'category' => 'performance',
            'strategy' => $strategy,
            'locale' => 'es',
        ];
        if (!empty($apiKey)) {
            $params['key'] = $apiKey;
        }

        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query($params);
        $response = Fetcher::get($apiUrl, 30, true, 1);

        if ($response['statusCode'] !== 200) {
            Logger::warning("PageSpeed API ($strategy) falló", [
                'status' => $response['statusCode'],
                'url' => $this->url,
            ]);
            return $result;
        }

        $data = json_decode($response['body'], true);
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
            $url = $item['url'] ?? '';
            if (empty($url) || str_starts_with($url, 'data:')) continue;

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
                'url' => $url,
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
     * Retorna el score móvil para uso en MobileAnalyzer
     */
    public function getMobileScore(): ?int {
        return $this->mobileScore;
    }

    /**
     * Retorna los datos del PageSpeed mobile
     */
    public function getMobileData(): ?array {
        return $this->mobileData;
    }

    /**
     * Retorna el LCP para cálculo de impacto económico
     */
    public function getLcp(): ?float {
        return $this->lcp;
    }

    /**
     * Retorna los network requests del waterfall (mobile)
     */
    public function getNetworkRequests(): array {
        return $this->mobileData['networkRequests'] ?? [];
    }

    /**
     * Retorna datos extendidos de rendimiento (CrUX, resource breakdown, audits)
     */
    public function getExtendedData(): array {
        return [
            'crux' => $this->mobileData['crux'] ?? null,
            'resourceBreakdown' => $this->mobileData['resourceBreakdown'] ?? [],
            'lighthouseAudits' => $this->mobileData['lighthouseAudits'] ?? [],
            'lcpElement' => $this->mobileData['lcpElement'] ?? null,
            'clsElements' => $this->mobileData['clsElements'] ?? [],
            'mainThreadWork' => $this->mobileData['mainThreadWork'] ?? [],
        ];
    }

    /**
     * Extrae las oportunidades de mejora del resultado de PageSpeed
     */
    private function extractOpportunities(?array $pageSpeedResult): array {
        if (!$pageSpeedResult || empty($pageSpeedResult['opportunities'])) {
            return [];
        }
        return $pageSpeedResult['opportunities'];
    }
}
