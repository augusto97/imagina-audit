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

        $modules = [];
        $isWordPress = false;

        // 2. WordPress Detector
        $this->reportProgress($auditId, 'wordpress', 1, $totalSteps, $startTime);
        $wpDetector = null;
        try {
            $wpDetector = new WordPressDetector($finalUrl, $html, $headers);
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

        return [
            'id' => $id,
            'name' => $name,
            'icon' => $icon,
            'score' => null,
            'level' => 'unknown',
            'weight' => $defaults[$weightKey] ?? 0.05,
            'metrics' => [],
            'summary' => "No fue posible analizar este módulo.",
            'salesMessage' => $defaults["sales_$id"] ?? '',
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
}
