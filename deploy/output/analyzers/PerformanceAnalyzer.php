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
        $hasCache = isset($this->headers['cache-control']) || isset($this->headers['etag']) || isset($this->headers['expires']);
        $cacheScore = $hasCache ? 100 : 40;
        $metrics[] = Scoring::createMetric(
            'cache_headers',
            'Cache del navegador',
            $hasCache,
            $hasCache ? 'Configurado' : 'No configurado',
            $cacheScore,
            $hasCache
                ? 'Los headers de cache están configurados. Los archivos se almacenan en el navegador.'
                : 'No se detectaron headers de cache. El navegador descarga todo cada vez.',
            $hasCache ? '' : 'Configurar headers Cache-Control y Expires para archivos estáticos.',
            'Configuramos cache agresivo para archivos estáticos con expiración optimizada.'
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
}
