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

        // PageSpeed mobile y desktop en paralelo (antes era secuencial: 16-30s → 8-15s)
        $apiKey = $this->resolveApiKey();
        $urls = [
            'mobile' => $this->buildPageSpeedUrl('mobile', $apiKey),
            'desktop' => $this->buildPageSpeedUrl('desktop', $apiKey),
        ];
        $responses = Fetcher::multiGet($urls, 30);
        $mobileResult = $this->parsePageSpeedResponse($responses['mobile'] ?? [], 'mobile');
        $desktopResult = $this->parsePageSpeedResponse($responses['desktop'] ?? [], 'desktop');

        // Score PageSpeed Mobile
        $this->mobileScore = $mobileResult['score'] ?? null;
        $this->mobileData = $mobileResult;

        // Si PSI no devuelve score (API caída, sin key, rate-limit), antes
        // pasábamos 50 al score: castigaba/regalaba arbitrariamente y
        // contaminaba el score global. Ahora null → la métrica queda
        // `unknown` y el Scoring la excluye del cálculo del módulo
        // (createMetric ya soporta esto).
        $metrics[] = Scoring::createMetric(
            'pagespeed_mobile',
            Translator::t('performance.psi_mobile.name'),
            $this->mobileScore,
            $this->mobileScore !== null
                ? Translator::t('performance.psi.display.score', ['score' => $this->mobileScore])
                : Translator::t('performance.psi.display.na'),
            $this->mobileScore,
            $this->mobileScore !== null
                ? Translator::t('performance.psi_mobile.desc.ok', ['score' => $this->mobileScore])
                : Translator::t('performance.psi_mobile.desc.na'),
            ($this->mobileScore !== null && $this->mobileScore < 70) ? Translator::t('performance.psi_mobile.recommend') : '',
            Translator::t('performance.psi_mobile.solution')
        );

        // Score PageSpeed Desktop — mismo trato.
        $desktopScore = $desktopResult['score'] ?? null;
        $metrics[] = Scoring::createMetric(
            'pagespeed_desktop',
            Translator::t('performance.psi_desktop.name'),
            $desktopScore,
            $desktopScore !== null
                ? Translator::t('performance.psi.display.score', ['score' => $desktopScore])
                : Translator::t('performance.psi.display.na'),
            $desktopScore,
            $desktopScore !== null
                ? Translator::t('performance.psi_desktop.desc.ok', ['score' => $desktopScore])
                : Translator::t('performance.psi_desktop.desc.na'),
            ($desktopScore !== null && $desktopScore < 70) ? Translator::t('performance.psi_desktop.recommend') : '',
            Translator::t('performance.psi_desktop.solution')
        );

        // LCP
        $this->lcp = $mobileResult['lcp'] ?? $desktopResult['lcp'] ?? null;
        if ($this->lcp !== null) {
            $lcpSeconds = round($this->lcp / 1000, 1);
            $lcpScore = $this->lcp <= 2500 ? 100 : ($this->lcp <= 4000 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'lcp',
                Translator::t('performance.lcp.name'),
                $this->lcp,
                Translator::t('performance.lcp.display', ['seconds' => $lcpSeconds]),
                $lcpScore,
                Translator::t('performance.lcp.desc.prefix', ['seconds' => $lcpSeconds])
                    . ($this->lcp <= 2500 ? Translator::t('performance.lcp.desc.good') : Translator::t('performance.lcp.desc.bad')),
                $this->lcp > 2500 ? Translator::t('performance.lcp.recommend') : '',
                Translator::t('performance.lcp.solution')
            );
        }

        // FCP
        $fcp = $mobileResult['fcp'] ?? null;
        if ($fcp !== null) {
            $fcpSeconds = round($fcp / 1000, 1);
            $fcpScore = $fcp <= 1800 ? 100 : ($fcp <= 3000 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'fcp',
                Translator::t('performance.fcp.name'),
                $fcp,
                Translator::t('performance.fcp.display', ['seconds' => $fcpSeconds]),
                $fcpScore,
                Translator::t('performance.fcp.desc', ['seconds' => $fcpSeconds]),
                $fcp > 1800 ? Translator::t('performance.fcp.recommend') : '',
                Translator::t('performance.fcp.solution')
            );
        }

        // CLS
        $cls = $mobileResult['cls'] ?? null;
        if ($cls !== null) {
            $clsScore = $cls <= 0.1 ? 100 : ($cls <= 0.25 ? 60 : 20);
            $clsFormatted = number_format($cls, 3);
            $metrics[] = Scoring::createMetric(
                'cls',
                Translator::t('performance.cls.name'),
                $cls,
                Translator::t('performance.cls.display', ['value' => $clsFormatted]),
                $clsScore,
                Translator::t('performance.cls.desc.prefix', ['value' => $clsFormatted])
                    . ($cls <= 0.1 ? Translator::t('performance.cls.desc.good') : Translator::t('performance.cls.desc.bad')),
                $cls > 0.1 ? Translator::t('performance.cls.recommend') : '',
                Translator::t('performance.cls.solution')
            );
        }

        // TBT
        $tbt = $mobileResult['tbt'] ?? null;
        if ($tbt !== null) {
            $tbtMs = round($tbt);
            $tbtScore = $tbt <= 200 ? 100 : ($tbt <= 600 ? 60 : 20);
            $metrics[] = Scoring::createMetric(
                'tbt',
                Translator::t('performance.tbt.name'),
                $tbt,
                Translator::t('performance.tbt.display', ['ms' => $tbtMs]),
                $tbtScore,
                Translator::t('performance.tbt.desc.prefix', ['ms' => $tbtMs])
                    . ($tbt <= 200 ? Translator::t('performance.tbt.desc.good') : Translator::t('performance.tbt.desc.bad')),
                $tbt > 200 ? Translator::t('performance.tbt.recommend') : '',
                Translator::t('performance.tbt.solution')
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
            $savingsStr = $totalSavings > 0
                ? Translator::t('performance.opp.display.savings', ['seconds' => round($totalSavings / 1000, 1)])
                : '';
            $suffix = $oppCount > 5
                ? Translator::t('performance.opp.desc.suffix_more', ['count' => $oppCount - 5])
                : Translator::t('performance.opp.desc.suffix_end');

            $metrics[] = Scoring::createMetric(
                'pagespeed_opportunities',
                Translator::t('performance.opp.name'),
                $oppCount,
                Translator::t('performance.opp.display', ['count' => $oppCount, 'savings' => $savingsStr]),
                $oppScore,
                Translator::t('performance.opp.desc.prefix', ['count' => $oppCount, 'list' => implode('; ', array_slice($oppDetails, 0, 5))]) . $suffix,
                Translator::t('performance.opp.recommend'),
                Translator::t('performance.opp.solution'),
                ['opportunities' => $opportunities]
            );
        }

        // TTFB propio
        $ttfb = $this->fetchTime;
        $ttfbScore = $ttfb <= 200 ? 100 : ($ttfb <= 500 ? 80 : ($ttfb <= 800 ? 50 : 20));
        $ttfbMs = round($ttfb);
        $metrics[] = Scoring::createMetric(
            'ttfb',
            Translator::t('performance.ttfb.name'),
            $ttfb,
            Translator::t('performance.ttfb.display', ['ms' => $ttfbMs]),
            $ttfbScore,
            Translator::t('performance.ttfb.desc.prefix', ['ms' => $ttfbMs])
                . ($ttfb <= 500 ? Translator::t('performance.ttfb.desc.good') : Translator::t('performance.ttfb.desc.bad')),
            $ttfb > 500 ? Translator::t('performance.ttfb.recommend') : '',
            Translator::t('performance.ttfb.solution')
        );

        // Compresión
        $encoding = $this->headers['content-encoding'] ?? '';
        $hasCompression = !empty($encoding);
        $compressionScore = $hasCompression ? 100 : 30;
        $metrics[] = Scoring::createMetric(
            'compression',
            Translator::t('performance.comp.name'),
            $hasCompression,
            $hasCompression
                ? Translator::t('performance.comp.display.ok', ['encoding' => strtoupper($encoding)])
                : Translator::t('performance.comp.display.none'),
            $compressionScore,
            $hasCompression
                ? Translator::t('performance.comp.desc.ok', ['encoding' => $encoding])
                : Translator::t('performance.comp.desc.none'),
            $hasCompression ? '' : Translator::t('performance.comp.recommend'),
            Translator::t('performance.comp.solution')
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

        // Detectar por comentarios HTML que dejan los plugins de cache.
        // Las firmas anteriores tenían entradas placeholder
        // "<!-- Starter starter starter" que no matcheaban con nada y
        // dejaban WP Super Cache, LiteSpeed y otros sin detectar.
        $htmlStr = $this->headers['_html'] ?? '';
        $hasCacheComment = false;
        if (!empty($htmlStr)) {
            $hasCacheComment = str_contains($htmlStr, '<!-- This website is like a Rocket')  // WP Rocket
                || str_contains($htmlStr, '<!-- Performance optimized by W3 Total Cache')      // W3TC
                || str_contains($htmlStr, '<!-- WP Fastest Cache')                              // WP Fastest Cache
                || str_contains($htmlStr, '<!-- Cached page generated by WP-Super-Cache')       // WP Super Cache
                || str_contains($htmlStr, 'Cached page served by WP Super Cache')               // WP Super Cache alt
                || str_contains($htmlStr, '<!-- Page generated by LiteSpeed Cache')             // LiteSpeed
                || str_contains($htmlStr, '<!-- Cache served by LiteSpeed')                     // LiteSpeed alt
                || str_contains($htmlStr, '<!-- Powered by Hummingbird Performance')            // Hummingbird (WPMU)
                || str_contains($htmlStr, '<!-- Generated by Comet Cache')                      // Comet Cache
                || str_contains($htmlStr, '<!-- Cached by WP-Optimize')                         // WP-Optimize
                || str_contains($htmlStr, '<!-- Autoptimize Plugin Activated')                  // Autoptimize
                || str_contains($htmlStr, 'data-rocket-')                                       // WP Rocket data attrs
                || str_contains($htmlStr, 'rocket-lazyload');
        }

        $hasCache = $hasCacheControl || $hasEtag || $hasExpires || $hasCachePlugin || $hasCacheComment;

        $cacheDetails = [];
        if ($hasCacheControl) $cacheDetails[] = Translator::t('performance.cache.detail.cc', ['value' => $cacheControl]);
        if ($hasEtag) $cacheDetails[] = Translator::t('performance.cache.detail.etag');
        if ($hasExpires) $cacheDetails[] = Translator::t('performance.cache.detail.expires', ['value' => $this->headers['expires'] ?? '']);
        if ($hasCachePlugin) $cacheDetails[] = Translator::t('performance.cache.detail.plugin_h');
        if ($hasCacheComment) $cacheDetails[] = Translator::t('performance.cache.detail.plugin_html');

        $cacheScore = $hasCache ? 100 : 40;
        $cacheDisplay = $hasCache
            ? Translator::t('performance.cache.display.ok', ['details' => implode(' · ', array_slice($cacheDetails, 0, 2))])
            : Translator::t('performance.cache.display.none');

        $metrics[] = Scoring::createMetric(
            'cache_headers',
            Translator::t('performance.cache.name'),
            $hasCache,
            $cacheDisplay,
            $cacheScore,
            $hasCache
                ? Translator::t('performance.cache.desc.ok', ['details' => implode('. ', $cacheDetails)])
                : Translator::t('performance.cache.desc.none'),
            $hasCache ? '' : Translator::t('performance.cache.recommend'),
            Translator::t('performance.cache.solution'),
            ['details' => $cacheDetails, 'hasCachePlugin' => $hasCachePlugin, 'hasCacheComment' => $hasCacheComment]
        );

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'performance',
            'name' => Translator::t('modules.performance.name'),
            'icon' => 'gauge',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_performance'],
            'metrics' => $metrics,
            'summary' => Translator::t('performance.summary', ['score' => $score]),
            'salesMessage' => $defaults['sales_performance'] !== '' ? $defaults['sales_performance'] : Translator::t('modules.sales.performance'),
        ];
    }

    /**
     * Resuelve la API key de Google PageSpeed (env primero, luego DB).
     * Delegado a lib/PageSpeed.php (compartido con el laboratorio).
     */
    private function resolveApiKey(): string {
        return PageSpeed::resolveApiKey();
    }

    /**
     * Construye la URL del endpoint de PageSpeed Insights para una estrategia.
     */
    private function buildPageSpeedUrl(string $strategy, string $apiKey = ''): string {
        return PageSpeed::buildUrl($this->url, $strategy, $apiKey, 'es');
    }

    /**
     * Parsea la respuesta cruda de PageSpeed en el formato interno.
     */
    private function parsePageSpeedResponse(array $response, string $strategy): array {
        return PageSpeed::parse($response, $strategy, $this->url);
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
