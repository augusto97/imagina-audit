<?php
/**
 * Orquestador principal de auditoría
 * Ejecuta todos los analyzers, compila resultados y genera el informe final
 */

class AuditOrchestrator {
    private string $url;
    private array $leadData;
    private ?array $snapshotData;
    private ?string $predefinedAuditId;

    /**
     * @param string|null $auditId Si se pasa, se usa como ID del audit y se
     *   reporta progreso vía AuditProgress. Si es null, se genera un UUID
     *   interno y no se reporta progreso (caller legacy como compare.php).
     */
    public function __construct(string $url, array $leadData = [], ?array $snapshotData = null, ?string $auditId = null) {
        $this->url = $url;
        $this->leadData = $leadData;
        $this->snapshotData = $snapshotData;
        $this->predefinedAuditId = $auditId;
    }

    /**
     * Genera un UUID v4. Público para que los callers puedan reservar el ID
     * antes de instanciar el orquestador (audit.php lo necesita para
     * responder al cliente antes de arrancar el scan).
     */
    public static function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Ejecuta la auditoría completa
     * @return array AuditResult completo
     */
    public function run(): array {
        $startTime = microtime(true);
        $auditId = $this->predefinedAuditId ?? self::generateUuid();

        // Limpiar el memo-cache de Fetcher para que el siguiente scan no
        // herede respuestas del anterior (cada scan empieza limpio).
        Fetcher::clearScanCache();

        // Número total de steps — 11 fijos + 1 si hay snapshot
        $totalSteps = 11 + ($this->snapshotData !== null ? 1 : 0);
        $this->reportProgress($auditId, 'fetch', 0, $totalSteps, $startTime);

        // 1. Fetch inicial del HTML (una sola vez, reutilizado por todos los analyzers)
        $fetchResult = Fetcher::get($this->url, 15, true, 1);
        $html = $fetchResult['body'];
        $headers = $fetchResult['headers'];
        $fetchTime = $fetchResult['responseTime'];
        $httpVersion = $fetchResult['httpVersion'];
        $finalUrl = $fetchResult['finalUrl'];

        if ($fetchResult['statusCode'] === 0 || empty($html)) {
            throw new RuntimeException('No fue posible acceder al sitio web. Verifica que la URL sea correcta y el sitio esté en línea.');
        }

        // Detección de Cloudflare/WAF challenge: el sitio responde pero el
        // body es una página interstitial, no el HTML real. Cualquier
        // analyzer que corra contra este body produce un informe basura
        // (sin title, no-WP, sin scripts, etc.) que se guarda y se enseña
        // al lead. Antes solo abortabamos con statusCode==0; un challenge
        // viene con 403/503 o incluso 200 y body distinto.
        if ($this->looksLikeChallenge($fetchResult['statusCode'], $headers, $html)) {
            throw new RuntimeException('El sitio está protegido por Cloudflare/WAF y bloqueó el escaneo (challenge JavaScript). Algunos sitios requieren whitelist de IP para auditar.');
        }

        // Soft-404 probe: ¿qué responde el servidor para una URL inexistente?
        // Muchos sitios devuelven la home con status 200 a cualquier path,
        // lo que hace que los checks de archivos sensibles (.git, install.php,
        // backup.zip…) marquen 8 archivos como "expuestos" cuando en realidad
        // el sitio solo no tiene 404 configurado. Esto producía el peor falso
        // positivo del producto: informe rojo catastrófico ante prospectos.
        $soft404 = $this->detectSoft404($finalUrl, $html);

        $modules = [];
        $isWordPress = false;

        // 2. WordPress Detector
        $this->reportProgress($auditId, 'wordpress', 1, $totalSteps, $startTime);
        $wpDetector = null;
        try {
            $wpDetector = new WordPressDetector($finalUrl, $html, $headers, $soft404);
            $wpResult = $this->timed('WordPressDetector', fn() => $wpDetector->analyze());
            $modules[] = $wpResult;
            $isWordPress = $wpDetector->isWordPress();
        } catch (Throwable $e) {
            Logger::error('WordPressDetector falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('wordpress', 'WordPress', 'blocks');
        }

        // 3. Security Analyzer (recibe datos de WP para consultar vulnerabilidades)
        $this->reportProgress($auditId, 'security', 2, $totalSteps, $startTime);
        try {
            $wpData = [
                'isWordPress' => $isWordPress,
                'plugins' => $wpDetector ? $wpDetector->getDetectedPlugins() : [],
                'theme' => $wpDetector ? $wpDetector->getDetectedThemeInfo() : [],
                'wpVersion' => $wpDetector ? $wpDetector->getDetectedWpVersion() : null,
                // Propagamos el soft-404 a los analyzers que hacen probes
                // de archivos sensibles (security y wordpress detector).
                // Si está activado, esos checks devuelven `unknown` en vez
                // de marcarlos como "expuestos" — el sitio responde 200 a
                // todo, no podemos distinguir archivo real de inexistente.
                'soft404' => $soft404,
            ];
            $securityAnalyzer = new SecurityAnalyzer($finalUrl, $html, $headers, $wpData);
            $modules[] = $this->timed('SecurityAnalyzer', fn() => $securityAnalyzer->analyze());
        } catch (Throwable $e) {
            Logger::error('SecurityAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('security', 'Seguridad', 'shield');
        }

        // 4. Performance Analyzer (hace llamadas externas a Google PageSpeed)
        $this->reportProgress($auditId, 'performance', 3, $totalSteps, $startTime);
        $performanceAnalyzer = null;
        try {
            $performanceAnalyzer = new PerformanceAnalyzer($finalUrl, array_merge($headers, ['_html' => $html]), $fetchTime);
            $modules[] = $this->timed('PerformanceAnalyzer', fn() => $performanceAnalyzer->analyze());
        } catch (Throwable $e) {
            Logger::error('PerformanceAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('performance', 'Rendimiento', 'gauge');
        }

        // 5. SEO Analyzer
        $this->reportProgress($auditId, 'seo', 4, $totalSteps, $startTime);
        try {
            $seoAnalyzer = new SeoAnalyzer($finalUrl, $html, $headers);
            $modules[] = $this->timed('SeoAnalyzer', fn() => $seoAnalyzer->analyze());
        } catch (Throwable $e) {
            Logger::error('SeoAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('seo', 'SEO', 'search');
        }

        // 6. Mobile Analyzer (reutiliza datos de PerformanceAnalyzer)
        $this->reportProgress($auditId, 'mobile', 5, $totalSteps, $startTime);
        try {
            $mobileScore = $performanceAnalyzer ? $performanceAnalyzer->getMobileScore() : null;
            $mobileAnalyzer = new MobileAnalyzer($html, $mobileScore, $finalUrl);
            $modules[] = $mobileAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('MobileAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('mobile', 'Compatibilidad Móvil', 'smartphone');
        }

        // 7. Infrastructure Analyzer
        $this->reportProgress($auditId, 'infrastructure', 6, $totalSteps, $startTime);
        try {
            $infraAnalyzer = new InfrastructureAnalyzer($finalUrl, $headers, $fetchTime, $httpVersion);
            $modules[] = $infraAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('InfrastructureAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('infrastructure', 'Infraestructura', 'server');
        }

        // 8. Conversion Analyzer
        $this->reportProgress($auditId, 'conversion', 7, $totalSteps, $startTime);
        try {
            $conversionAnalyzer = new ConversionAnalyzer($html);
            $modules[] = $conversionAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('ConversionAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('conversion', 'Conversión y Marketing', 'bar-chart-3');
        }

        // 8b. Page Health Analyzer
        $this->reportProgress($auditId, 'page_health', 8, $totalSteps, $startTime);
        try {
            $pageHealthAnalyzer = new PageHealthAnalyzer($finalUrl, $html, array_merge($headers, ['_status_code' => $fetchResult['statusCode']]));
            $modules[] = $pageHealthAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('PageHealthAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('page_health', 'Salud de Página', 'heart-pulse');
        }

        // 8c. WpSnapshotAnalyzer (si se tiene snapshot del plugin wp-snapshot)
        if ($this->snapshotData !== null && isset($this->snapshotData['sections'])) {
            $this->reportProgress($auditId, 'wp_internal', 9, $totalSteps, $startTime);
            try {
                $snapshotAnalyzer = new WpSnapshotAnalyzer($this->snapshotData);
                $modules[] = $snapshotAnalyzer->analyze();
            } catch (Throwable $e) {
                Logger::error('WpSnapshotAnalyzer falló: ' . $e->getMessage());
                $modules[] = $this->createFailedModule('wp_internal', 'Análisis Interno', 'database');
            }
        }

        // 9. Detectar stack tecnológico (informativo, no afecta score)
        $techStep = $this->snapshotData !== null ? 10 : 9;
        $this->reportProgress($auditId, 'techstack', $techStep, $totalSteps, $startTime);
        $techStack = [];
        try {
            $techDetector = new TechDetector($html, $headers, $finalUrl);
            $techStack = $techDetector->detect();
        } catch (Throwable $e) {
            Logger::warning('TechDetector falló: ' . $e->getMessage());
        }

        // 9b. Extraer waterfall data + extended performance data
        $waterfall = $performanceAnalyzer ? $performanceAnalyzer->getNetworkRequests() : [];
        $extendedPerf = $performanceAnalyzer ? $performanceAnalyzer->getExtendedData() : [];

        // 10. Calcular resultados globales
        $compileStep = $this->snapshotData !== null ? 11 : 10;
        $this->reportProgress($auditId, 'compile', $compileStep, $totalSteps, $startTime);
        $globalScore = Scoring::calculateGlobalScore($modules);
        $globalLevel = Scoring::getLevel($globalScore);
        $totalIssues = Scoring::countIssues($modules);
        $solutionMap = Scoring::generateSolutionMap($modules);
        $economicImpact = Scoring::calculateEconomicImpact($modules);

        $endTime = microtime(true);
        $scanDurationMs = (int) round(($endTime - $startTime) * 1000);

        $domain = UrlValidator::extractDomain($this->url);

        Logger::audit($domain, $globalScore, round($scanDurationMs / 1000, 1));

        return [
            'id' => $auditId,
            'url' => $this->url,
            'domain' => $domain,
            'timestamp' => date('c'),
            'scanDurationMs' => $scanDurationMs,
            'globalScore' => $globalScore,
            'globalLevel' => $globalLevel,
            'totalIssues' => $totalIssues,
            'modules' => $modules,
            'isWordPress' => $isWordPress,
            'economicImpact' => $economicImpact,
            'solutionMap' => $solutionMap,
            'techStack' => $techStack,
            'waterfall' => $waterfall,
            'extendedPerf' => $extendedPerf,
        ];
    }

    /**
     * Crea un módulo con estado "fallido" cuando un analyzer lanza excepción
     */
    private function createFailedModule(string $id, string $name, string $icon): array {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $weightKey = "weight_$id";

        // Nombre localizado si existe en el bundle — si no, el que pasó el caller.
        $localName = Translator::has("modules.$id.name") ? Translator::t("modules.$id.name") : $name;
        $summary = Translator::has('modules.failed.summary')
            ? Translator::t('modules.failed.summary')
            : 'No fue posible analizar este módulo.';
        $salesMessage = Translator::has("modules.sales.$id")
            ? Translator::t("modules.sales.$id")
            : ($defaults["sales_$id"] ?? '');

        return [
            'id' => $id,
            'name' => $localName,
            'icon' => $icon,
            'score' => null,
            'level' => 'unknown',
            'weight' => $defaults[$weightKey] ?? 0.05,
            'metrics' => [],
            'summary' => $summary,
            'salesMessage' => $salesMessage,
        ];
    }

    /**
     * Reporta progreso del audit. No-op si no se pasó un auditId externo
     * (caller legacy que no necesita tracking — p. ej. compare.php).
     */
    private function reportProgress(string $auditId, string $step, int $completedSteps, int $totalSteps, float $startTime): void {
        if ($this->predefinedAuditId === null) {
            return;
        }
        AuditProgress::update($auditId, [
            'status' => 'running',
            'currentStep' => $step,
            'completedSteps' => $completedSteps,
            'totalSteps' => $totalSteps,
            'startedAt' => (int) $startTime,
        ]);
    }

    /**
     * Ejecuta un analyzer midiendo tiempo y loggeando el resultado.
     * Imprescindible para diagnosticar cuellos de botella en producción.
     */
    private function timed(string $name, callable $fn): mixed {
        $t0 = microtime(true);
        try {
            $result = $fn();
            $elapsed = (microtime(true) - $t0) * 1000;
            Logger::info("$name OK", ['elapsed_ms' => (int) $elapsed]);
            return $result;
        } catch (Throwable $e) {
            $elapsed = (microtime(true) - $t0) * 1000;
            Logger::error("$name FAIL: " . $e->getMessage(), ['elapsed_ms' => (int) $elapsed]);
            throw $e;
        }
    }

    /**
     * Detecta servidores con soft-404: piden una URL probadamente
     * inexistente y el server responde 200 con la home (o cualquier otra
     * página) en vez de 404. Con esto activado los analyzers no pueden
     * distinguir archivo real de inexistente solo por status code.
     *
     * Implementación: una sola probe a un path random (no follow redirects).
     * Si responde 200, comparamos un hash del body con el de la home —
     * páginas casi iguales = soft-404. Si responde 4xx/5xx, no es soft-404.
     */
    private function detectSoft404(string $baseUrl, string $homeHtml): array {
        try {
            $parsed = parse_url($baseUrl);
            if (!$parsed || !isset($parsed['host'])) return ['active' => false];
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'];
            $probePath = '/__imagina_audit_404_probe_' . bin2hex(random_bytes(6));
            $probeUrl = "$scheme://$host$probePath";
            $res = Fetcher::get($probeUrl, 5, false, 0);
            $status = (int) ($res['statusCode'] ?? 0);
            if ($status === 0) return ['active' => false];
            // 404/410 → server-side reconoce paths inexistentes (lo normal).
            if ($status === 404 || $status === 410) return ['active' => false];
            // Status 4xx que no sea 404 → tampoco soft (probable WAF/auth).
            if ($status >= 400 && $status < 500) return ['active' => false];
            // 200 / 3xx → revisamos similitud con la home. Si el body coincide
            // sustancialmente, es soft-404.
            $probeBody = (string) ($res['body'] ?? '');
            if ($probeBody === '') return ['active' => false];
            // Heurística barata: comparar longitudes y un substring inicial.
            // No necesitamos exactitud, solo "se parecen mucho".
            $homeLen = strlen($homeHtml);
            $probeLen = strlen($probeBody);
            if ($homeLen === 0 || $probeLen === 0) return ['active' => false];
            $ratio = min($homeLen, $probeLen) / max($homeLen, $probeLen);
            if ($ratio >= 0.85) {
                return ['active' => true, 'status' => $status, 'reason' => 'similar_body'];
            }
            // O si el server devuelve 200 con una página obviamente positiva
            // (sin "not found" en el cuerpo) — política conservadora:
            // solo si el body es claramente html.
            if ($status === 200 && stripos($probeBody, 'not found') === false && stripos($probeBody, '404') === false) {
                return ['active' => true, 'status' => 200, 'reason' => 'status_200_no_404_marker'];
            }
            return ['active' => false];
        } catch (Throwable $e) {
            Logger::warning('detectSoft404 falló: ' . $e->getMessage());
            return ['active' => false];
        }
    }

    /**
     * Detecta páginas de challenge de Cloudflare/WAF. Si el HTML que
     * recibimos no es el sitio real sino una página de "Just a moment…"
     * o similar, los analyzers producen basura. Mejor fallar el audit
     * con un mensaje claro que guardar un informe inservible.
     */
    private function looksLikeChallenge(int $statusCode, array $headers, string $html): bool {
        // Header explícito de Cloudflare mitigation
        if (!empty($headers['cf-mitigated'])) return true;
        if (!empty($headers['cf-chl-bypass'])) return true;

        $body = strtolower(substr($html, 0, 4096));
        $signatures = [
            'just a moment...',
            'checking your browser',
            'cf-browser-verification',
            'cf-im-under-attack',
            'attention required! | cloudflare',
            'enable javascript and cookies to continue',
            '/cdn-cgi/challenge-platform',
        ];
        foreach ($signatures as $sig) {
            if (str_contains($body, $sig)) return true;
        }
        // 403/503 + body extremadamente corto (< 1KB) y con "cloudflare" o
        // "blocked" → muy probable challenge.
        if (($statusCode === 403 || $statusCode === 503) && strlen($html) < 8192) {
            if (str_contains($body, 'cloudflare') || str_contains($body, 'blocked')) return true;
        }
        return false;
    }
}
