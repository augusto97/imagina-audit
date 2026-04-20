<?php
/**
 * Analiza la seguridad del sitio: SSL, headers, vulnerabilidades, exposiciones.
 *
 * Orquestador delgado que delega a 5 sub-checkers especializados:
 *   - SecurityTransportChecker     (SSL, HTTPS redirect, HSTS, TLS, DNSSEC, source code exposure)
 *   - SecurityHeadersChecker       (security headers HTTP, headers expuestos, SRI)
 *   - SecurityReputationChecker    (emails expuestos, DMARC, SPF, Safe Browsing)
 *   - SecurityWpChecker            (directory listing, archivos WP, REST API, etc — solo si es WP)
 *   - SecurityVulnerabilityChecker (core/plugin/theme CVSS — solo si es WP)
 */

class SecurityAnalyzer {
    private string $url;
    private string $html;
    private array $headers;
    private string $host;

    /** Datos de WordPress inyectados por el orquestador */
    private array $wpData;

    /**
     * @param string $url URL del sitio
     * @param string $html HTML descargado
     * @param array $headers Headers HTTP de respuesta
     * @param array $wpData Datos del WordPressDetector: plugins, theme, wpVersion, isWordPress
     */
    public function __construct(string $url, string $html, array $headers, array $wpData = []) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->host = parse_url($url, PHP_URL_HOST) ?: '';
        $this->wpData = $wpData;
    }

    /**
     * Ejecuta todas las verificaciones y compila el módulo de seguridad.
     */
    public function analyze(): array {
        $metrics = [];
        $ctx = [$this->url, $this->html, $this->headers, $this->host, $this->wpData];

        // Transporte (SSL/TLS/HSTS/redirects/source code exposure)
        $transport = new SecurityTransportChecker(...$ctx);
        $metrics[] = $transport->checkSsl();
        $metrics[] = $transport->checkHttpsRedirect();
        $metrics[] = $transport->checkHstsPreload();
        $metrics[] = $transport->checkWeakTlsVersions();
        $metrics[] = $transport->checkDnssec();
        $metrics[] = $transport->checkSourceCodeExposure();

        // Headers HTTP + SRI
        $headersChk = new SecurityHeadersChecker(...$ctx);
        $metrics[] = $headersChk->checkSecurityHeaders();
        $metrics[] = $headersChk->checkExposedHeaders();
        $metrics[] = $headersChk->checkSubresourceIntegrity();

        // Reputación (email, DNS, Safe Browsing)
        $reputation = new SecurityReputationChecker(...$ctx);
        $metrics[] = $reputation->checkExposedEmail();
        $metrics[] = $reputation->checkDmarc();
        $metrics[] = $reputation->checkSpf();
        $metrics[] = $reputation->checkSafeBrowsing();

        $isWordPress = $this->wpData['isWordPress'] ?? str_contains($this->html, '/wp-content/');

        if ($isWordPress) {
            // Checks WP-específicos (no vulnerabilidades)
            $wpChk = new SecurityWpChecker(...$ctx);
            $metrics[] = $wpChk->checkDirectoryListing();
            $metrics[] = $wpChk->checkWpInfoFiles();
            $metrics[] = $wpChk->checkWpInstallFiles();
            $metrics[] = $wpChk->checkPhpInUploads();
            $metrics[] = $wpChk->checkRestApiEnumerationExtra();
            $metrics[] = $wpChk->checkDefaultAdminUser();
            $metrics[] = $wpChk->checkSecurityPlugin();

            // Vulnerabilidades (core, plugins, tema)
            $vuln = new SecurityVulnerabilityChecker(...$ctx);
            $core = $vuln->checkCoreVulnerabilities();
            if ($core !== null) $metrics[] = $core;

            $plug = $vuln->checkPluginVulnerabilities();
            if ($plug !== null) $metrics[] = $plug;

            $theme = $vuln->checkThemeVulnerabilities();
            if ($theme !== null) $metrics[] = $theme;
        }

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'security',
            'name' => 'Seguridad',
            'icon' => 'shield',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_security'],
            'metrics' => $metrics,
            'summary' => "Tu sitio tiene una puntuación de seguridad de $score/100.",
            'salesMessage' => $defaults['sales_security'],
        ];
    }
}
