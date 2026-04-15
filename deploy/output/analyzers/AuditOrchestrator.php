<?php
/**
 * Orquestador principal de auditoría
 * Ejecuta todos los analyzers, compila resultados y genera el informe final
 */

class AuditOrchestrator {
    private string $url;
    private array $leadData;

    public function __construct(string $url, array $leadData = []) {
        $this->url = $url;
        $this->leadData = $leadData;
    }

    /**
     * Ejecuta la auditoría completa
     * @return array AuditResult completo
     */
    public function run(): array {
        $startTime = microtime(true);
        $auditId = $this->generateUuid();

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
        $wpDetector = null;
        try {
            $wpDetector = new WordPressDetector($finalUrl, $html, $headers);
            $wpResult = $wpDetector->analyze();
            $modules[] = $wpResult;
            $isWordPress = $wpDetector->isWordPress();
        } catch (Throwable $e) {
            Logger::error('WordPressDetector falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('wordpress', 'WordPress', 'blocks');
        }

        // 3. Security Analyzer (recibe datos de WP para consultar vulnerabilidades)
        try {
            $wpData = [
                'isWordPress' => $isWordPress,
                'plugins' => $wpDetector ? $wpDetector->getDetectedPlugins() : [],
                'theme' => $wpDetector ? $wpDetector->getDetectedThemeInfo() : [],
                'wpVersion' => $wpDetector ? $wpDetector->getDetectedWpVersion() : null,
            ];
            $securityAnalyzer = new SecurityAnalyzer($finalUrl, $html, $headers, $wpData);
            $modules[] = $securityAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('SecurityAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('security', 'Seguridad', 'shield');
        }

        // 4. Performance Analyzer (hace llamadas externas a Google PageSpeed)
        $performanceAnalyzer = null;
        try {
            $performanceAnalyzer = new PerformanceAnalyzer($finalUrl, $headers, $fetchTime);
            $modules[] = $performanceAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('PerformanceAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('performance', 'Rendimiento', 'gauge');
        }

        // 5. SEO Analyzer
        try {
            $seoAnalyzer = new SeoAnalyzer($finalUrl, $html, $headers);
            $modules[] = $seoAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('SeoAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('seo', 'SEO', 'search');
        }

        // 6. Mobile Analyzer (reutiliza datos de PerformanceAnalyzer)
        try {
            $mobileScore = $performanceAnalyzer ? $performanceAnalyzer->getMobileScore() : null;
            $mobileAnalyzer = new MobileAnalyzer($html, $mobileScore);
            $modules[] = $mobileAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('MobileAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('mobile', 'Compatibilidad Móvil', 'smartphone');
        }

        // 7. Infrastructure Analyzer
        try {
            $infraAnalyzer = new InfrastructureAnalyzer($finalUrl, $headers, $fetchTime, $httpVersion);
            $modules[] = $infraAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('InfrastructureAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('infrastructure', 'Infraestructura', 'server');
        }

        // 8. Conversion Analyzer
        try {
            $conversionAnalyzer = new ConversionAnalyzer($html);
            $modules[] = $conversionAnalyzer->analyze();
        } catch (Throwable $e) {
            Logger::error('ConversionAnalyzer falló: ' . $e->getMessage());
            $modules[] = $this->createFailedModule('conversion', 'Conversión y Marketing', 'bar-chart-3');
        }

        // 9. Backups (módulo estático — siempre en warning porque no se puede verificar externamente)
        $modules[] = $this->createBackupsModule();

        // 10. Calcular resultados globales
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
     * Crea el módulo de Backups (estático, no se puede verificar externamente)
     */
    private function createBackupsModule(): array {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';

        $metrics = [
            Scoring::createMetric(
                'backup_auto',
                'Backups automáticos',
                null,
                'No verificable externamente',
                30,
                'No es posible verificar externamente si tu sitio tiene backups automáticos configurados.',
                'Configurar backups automáticos diarios con retención de al menos 30 días.',
                'Configuramos backups diarios automáticos con retención de 30 días almacenados fuera del servidor.'
            ),
            Scoring::createMetric(
                'backup_offsite',
                'Almacenamiento externo',
                null,
                'No verificable externamente',
                30,
                'Los backups deben almacenarse fuera del servidor principal para proteger contra pérdida total.',
                'Almacenar backups en un servicio externo como Google Drive, Dropbox o Amazon S3.',
                'Almacenamos tus backups en la nube, separados del servidor, para máxima seguridad.'
            ),
            Scoring::createMetric(
                'backup_restore',
                'Plan de restauración',
                null,
                'No verificable externamente',
                30,
                'Es importante tener un plan probado de restauración en caso de emergencia.',
                'Establecer un procedimiento de restauración y probarlo periódicamente.',
                'Incluimos restauración gratuita en caso de emergencia con tiempo de respuesta de 2 horas.'
            ),
        ];

        return [
            'id' => 'backups',
            'name' => 'Backups y Recuperación',
            'icon' => 'hard-drive',
            'score' => 30,
            'level' => 'warning',
            'weight' => $defaults['weight_backups'],
            'metrics' => $metrics,
            'summary' => 'Los backups no se pueden verificar externamente. La mayoría de sitios no tienen backups adecuados.',
            'salesMessage' => $defaults['sales_backups'],
        ];
    }

    /**
     * Genera un UUID v4
     */
    private function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
