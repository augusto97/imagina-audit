<?php
/**
 * Analiza la seguridad del sitio: SSL, headers, vulnerabilidades, exposiciones
 */

class SecurityAnalyzer {
    private string $url;
    private string $html;
    private array $headers;
    private string $host;

    public function __construct(string $url, string $html, array $headers) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->host = parse_url($url, PHP_URL_HOST) ?: '';
    }

    /**
     * Ejecuta el análisis de seguridad
     */
    public function analyze(): array {
        $metrics = [];

        // SSL
        $metrics[] = $this->checkSsl();

        // Redirección HTTP → HTTPS
        $metrics[] = $this->checkHttpsRedirect();

        // Headers de seguridad
        $metrics[] = $this->checkSecurityHeaders();

        // Headers expuestos
        $metrics[] = $this->checkExposedHeaders();

        // Directory listing (solo si es WordPress)
        if (str_contains($this->html, '/wp-content/')) {
            $metrics[] = $this->checkDirectoryListing();
        }

        // Vulnerabilidades de plugins (consulta BD)
        $vulnMetric = $this->checkPluginVulnerabilities();
        if ($vulnMetric !== null) {
            $metrics[] = $vulnMetric;
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

    /**
     * Verifica el certificado SSL
     */
    private function checkSsl(): array {
        $ssl = Fetcher::getSslInfo($this->host);

        if (!$ssl['valid']) {
            return Scoring::createMetric(
                'ssl_valid',
                'Certificado SSL',
                false,
                'No válido o no presente',
                0,
                'El sitio no tiene un certificado SSL válido. Los visitantes verán advertencias de seguridad.',
                'Instalar un certificado SSL (Let\'s Encrypt es gratuito).',
                'Instalamos y configuramos SSL gratuito con Let\'s Encrypt en tu hosting.'
            );
        }

        $days = $ssl['daysUntilExpiry'];
        $score = $days > 30 ? 100 : ($days > 7 ? 60 : 20);

        return Scoring::createMetric(
            'ssl_valid',
            'Certificado SSL',
            true,
            "Válido hasta {$ssl['validTo']} ({$days} días)",
            $score,
            $days > 30
                ? "Certificado SSL válido emitido por {$ssl['issuer']}. Expira el {$ssl['validTo']}."
                : "Certificado SSL próximo a expirar ({$days} días). Emitido por {$ssl['issuer']}.",
            $days <= 30 ? 'Renovar el certificado SSL antes de que expire.' : '',
            'Monitoreamos la expiración del SSL y lo renovamos automáticamente.',
            ['issuer' => $ssl['issuer'], 'validFrom' => $ssl['validFrom'], 'validTo' => $ssl['validTo']]
        );
    }

    /**
     * Verifica redirección HTTP → HTTPS
     */
    private function checkHttpsRedirect(): array {
        // Construir URL HTTP
        $httpUrl = preg_replace('#^https://#', 'http://', $this->url);

        $response = Fetcher::get($httpUrl, 5, false, 0);
        $redirectsToHttps = false;

        if (in_array($response['statusCode'], [301, 302, 307, 308])) {
            $location = $response['headers']['location'] ?? '';
            if (str_starts_with($location, 'https://')) {
                $redirectsToHttps = true;
            }
        }

        return Scoring::createMetric(
            'https_redirect',
            'Redirección HTTP → HTTPS',
            $redirectsToHttps,
            $redirectsToHttps ? 'Configurada correctamente' : 'No configurada',
            $redirectsToHttps ? 100 : 30,
            $redirectsToHttps
                ? 'HTTP redirige correctamente a HTTPS.'
                : 'HTTP no redirige a HTTPS. Los visitantes podrían acceder a la versión no segura.',
            $redirectsToHttps ? '' : 'Configurar redirección 301 de HTTP a HTTPS.',
            'Configuramos la redirección HTTPS y forzamos conexiones seguras.'
        );
    }

    /**
     * Verifica headers de seguridad HTTP
     */
    private function checkSecurityHeaders(): array {
        $headersToCheck = [
            'x-content-type-options' => ['name' => 'X-Content-Type-Options', 'points' => 14],
            'x-frame-options' => ['name' => 'X-Frame-Options', 'points' => 14],
            'content-security-policy' => ['name' => 'Content-Security-Policy', 'points' => 14],
            'strict-transport-security' => ['name' => 'Strict-Transport-Security', 'points' => 14],
            'x-xss-protection' => ['name' => 'X-XSS-Protection', 'points' => 11],
            'referrer-policy' => ['name' => 'Referrer-Policy', 'points' => 11],
            'permissions-policy' => ['name' => 'Permissions-Policy', 'points' => 11],
        ];

        $present = [];
        $missing = [];
        $score = 0;

        foreach ($headersToCheck as $headerKey => $info) {
            if (isset($this->headers[$headerKey])) {
                $present[] = $info['name'];
                $score += $info['points'];
            } else {
                $missing[] = $info['name'];
            }
        }

        // Bonus si todos presentes
        if (empty($missing)) {
            $score += 11;
        }

        $score = min(100, $score);

        return Scoring::createMetric(
            'security_headers',
            'Headers de seguridad HTTP',
            count($present),
            count($present) . '/' . count($headersToCheck) . ' headers presentes',
            $score,
            count($present) === count($headersToCheck)
                ? 'Todos los headers de seguridad están configurados correctamente.'
                : 'Faltan headers de seguridad: ' . implode(', ', $missing) . '.',
            empty($missing) ? '' : 'Agregar los headers de seguridad faltantes en la configuración del servidor.',
            'Configuramos todos los headers de seguridad HTTP recomendados.',
            ['present' => $present, 'missing' => $missing]
        );
    }

    /**
     * Verifica headers que no deberían estar expuestos
     */
    private function checkExposedHeaders(): array {
        $exposed = [];
        $score = 100;

        if (isset($this->headers['server'])) {
            $server = $this->headers['server'];
            // Solo penalizar si muestra versión
            if (preg_match('/[\d.]+/', $server)) {
                $exposed[] = "Server: $server";
                $score -= 20;
            }
        }

        if (isset($this->headers['x-powered-by'])) {
            $exposed[] = 'X-Powered-By: ' . $this->headers['x-powered-by'];
            $score -= 20;
        }

        $score = max(0, $score);

        return Scoring::createMetric(
            'exposed_headers',
            'Headers de servidor expuestos',
            count($exposed),
            count($exposed) > 0 ? implode(', ', $exposed) : 'No expuestos',
            $score,
            count($exposed) > 0
                ? 'Se detectaron headers que exponen información del servidor: ' . implode('; ', $exposed)
                : 'No se detectaron headers que expongan información del servidor.',
            count($exposed) > 0 ? 'Ocultar la versión del servidor y el header X-Powered-By.' : '',
            'Ocultamos información del servidor para reducir la superficie de ataque.'
        );
    }

    /**
     * Verifica directory listing
     */
    private function checkDirectoryListing(): array {
        $listings = [];

        $dirs = ['/wp-content/uploads/', '/wp-content/plugins/'];
        foreach ($dirs as $dir) {
            $response = Fetcher::get($this->url . $dir, 5, true, 0);
            if ($response['statusCode'] === 200 && str_contains($response['body'], 'Index of')) {
                $listings[] = $dir;
            }
        }

        $score = empty($listings) ? 100 : max(0, 100 - (count($listings) * 30));

        return Scoring::createMetric(
            'directory_listing',
            'Listado de directorios',
            count($listings),
            count($listings) > 0 ? 'Activo en ' . count($listings) . ' directorios' : 'Desactivado',
            $score,
            count($listings) > 0
                ? 'El listado de directorios está activo en: ' . implode(', ', $listings) . '. Cualquiera puede ver los archivos.'
                : 'El listado de directorios está desactivado.',
            count($listings) > 0 ? 'Desactivar directory listing con "Options -Indexes" en .htaccess.' : '',
            'Desactivamos el listado de directorios y protegemos la estructura del sitio.'
        );
    }

    /**
     * Verifica vulnerabilidades conocidas en plugins detectados
     */
    private function checkPluginVulnerabilities(): ?array {
        // Extraer slugs de plugins del HTML
        $pluginSlugs = [];
        preg_match_all('#/wp-content/plugins/([a-z0-9_-]+)/#i', $this->html, $matches);
        if (!empty($matches[1])) {
            $pluginSlugs = array_unique($matches[1]);
        }

        if (empty($pluginSlugs)) {
            return null;
        }

        try {
            $db = Database::getInstance();
            $vulnerable = [];

            foreach ($pluginSlugs as $slug) {
                $rows = $db->query(
                    "SELECT * FROM vulnerabilities WHERE plugin_slug = ?",
                    [$slug]
                );
                foreach ($rows as $row) {
                    $vulnerable[] = [
                        'plugin' => $row['plugin_name'],
                        'severity' => $row['severity'],
                        'cve' => $row['cve_id'],
                        'description' => $row['description'],
                    ];
                }
            }

            $score = Scoring::clamp(100 - (count($vulnerable) * 20));

            return Scoring::createMetric(
                'plugin_vulnerabilities',
                'Vulnerabilidades conocidas',
                count($vulnerable),
                count($vulnerable) > 0 ? count($vulnerable) . ' vulnerabilidades detectadas' : 'Ninguna detectada',
                $score,
                count($vulnerable) > 0
                    ? 'Se detectaron ' . count($vulnerable) . ' vulnerabilidades conocidas en los plugins instalados.'
                    : 'No se detectaron vulnerabilidades conocidas en la base de datos local.',
                count($vulnerable) > 0 ? 'Actualizar inmediatamente los plugins afectados.' : '',
                'Monitoreamos vulnerabilidades y aplicamos parches de seguridad de forma proactiva.',
                ['vulnerabilities' => $vulnerable]
            );
        } catch (Throwable $e) {
            Logger::warning('Error consultando vulnerabilidades: ' . $e->getMessage());
            return null;
        }
    }
}
