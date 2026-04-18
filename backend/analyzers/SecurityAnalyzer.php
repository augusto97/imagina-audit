<?php
/**
 * Analiza la seguridad del sitio: SSL, headers, vulnerabilidades, exposiciones
 *
 * Usa la API de WPVulnerability.net como fuente principal de vulnerabilidades
 * con fallback a la tabla local de SQLite.
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
     * @param array $wpData Datos de WP del WordPressDetector:
     *   - 'plugins'   => [['slug'=>..,'name'=>..,'detectedVersion'=>..], ...]
     *   - 'theme'     => ['slug'=>..,'name'=>..,'version'=>..]
     *   - 'wpVersion' => string|null
     *   - 'isWordPress' => bool
     */
    public function __construct(string $url, string $html, array $headers, array $wpData = []) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->host = parse_url($url, PHP_URL_HOST) ?: '';
        $this->wpData = $wpData;
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

        // Email expuesto en texto plano
        $metrics[] = $this->checkExposedEmail();

        // DMARC record
        $metrics[] = $this->checkDmarc();

        // Google Safe Browsing
        $metrics[] = $this->checkSafeBrowsing();

        // SPF record (complemento de DMARC)
        $metrics[] = $this->checkSpf();

        // Source code exposure (.git/.svn)
        $metrics[] = $this->checkSourceCodeExposure();

        // HSTS preload
        $metrics[] = $this->checkHstsPreload();

        // Subresource Integrity
        $metrics[] = $this->checkSubresourceIntegrity();

        // DNSSEC
        $metrics[] = $this->checkDnssec();

        // Weak TLS versions
        $metrics[] = $this->checkWeakTlsVersions();

        $isWordPress = $this->wpData['isWordPress'] ?? str_contains($this->html, '/wp-content/');

        if ($isWordPress) {
            // Directory listing
            $metrics[] = $this->checkDirectoryListing();

            // WP version leak files
            $metrics[] = $this->checkWpInfoFiles();

            // Install/upgrade files accessible
            $metrics[] = $this->checkWpInstallFiles();

            // PHP in uploads (malware indicator)
            $metrics[] = $this->checkPhpInUploads();

            // REST API enumeration (extras)
            $metrics[] = $this->checkRestApiEnumerationExtra();

            // Default admin user
            $metrics[] = $this->checkDefaultAdminUser();

            // Security plugin detected (positive)
            $metrics[] = $this->checkSecurityPlugin();

            // Vulnerabilidades de WordPress core
            $coreMetric = $this->checkCoreVulnerabilities();
            if ($coreMetric !== null) {
                $metrics[] = $coreMetric;
            }

            // Vulnerabilidades de plugins (API WPVulnerability + fallback SQLite)
            $pluginMetric = $this->checkPluginVulnerabilities();
            if ($pluginMetric !== null) {
                $metrics[] = $pluginMetric;
            }

            // Vulnerabilidades del tema
            $themeMetric = $this->checkThemeVulnerabilities();
            if ($themeMetric !== null) {
                $metrics[] = $themeMetric;
            }
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

    // =========================================================================
    // Verificaciones SSL / Headers (sin cambios)
    // =========================================================================

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

    private function checkHttpsRedirect(): array {
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

    private function checkExposedHeaders(): array {
        $exposed = [];
        $score = 100;

        if (isset($this->headers['server'])) {
            $server = $this->headers['server'];
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

    // =========================================================================
    // Vulnerabilidades — WPVulnerability.net API + fallback SQLite
    // =========================================================================

    /**
     * Consulta la API de WPVulnerability.net para un slug dado.
     * Usa cache de 24h y fallback a SQLite local.
     *
     * @param string $type 'plugin', 'theme' o 'core'
     * @param string $slug Slug del plugin/tema o versión del core
     * @return array|null Respuesta JSON de la API o null si falla
     */
    private function queryWpVulnerabilityApi(string $type, string $slug): ?array {
        $cache = new Cache();
        $cacheKey = "vuln_{$type}_{$slug}";

        // 1. Intentar cache
        $cached = $cache->getByName($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Consultar API
        $apiUrl = "https://www.wpvulnerability.net/{$type}/{$slug}/";

        try {
            $response = Fetcher::get($apiUrl, 5, true, 0);

            if ($response['statusCode'] === 200) {
                $data = json_decode($response['body'], true);
                if ($data !== null && isset($data['data'])) {
                    // Guardar en cache 24h
                    $cache->setByName($cacheKey, $data, 86400);
                    return $data;
                }
            }
        } catch (Throwable $e) {
            Logger::warning("WPVulnerability API falló para $type/$slug: " . $e->getMessage());
        }

        // 3. Marcar en cache como vacío para no reintentar en 24h
        $cache->setByName($cacheKey, ['error' => 1, 'data' => null], 86400);
        return null;
    }

    /**
     * Filtra las vulnerabilidades que aplican a una versión específica
     *
     * @param array $vulnerabilities Array de vulnerabilidades de la API
     * @param string|null $detectedVersion Versión detectada del plugin/tema/core
     * @return array Vulnerabilidades que afectan la versión detectada
     */
    private function filterApplicableVulnerabilities(array $vulnerabilities, ?string $detectedVersion): array {
        $applicable = [];

        foreach ($vulnerabilities as $vuln) {
            $operator = $vuln['operator'] ?? [];
            $maxVersion = $operator['max_version'] ?? null;
            $maxOperator = $operator['max_operator'] ?? 'le';
            $unfixed = (string) ($operator['unfixed'] ?? '0');

            // Si no hay versión detectada, incluir si está unfixed (es riesgo potencial)
            if ($detectedVersion === null) {
                if ($unfixed === '1') {
                    $applicable[] = $this->formatVulnerability($vuln, null);
                }
                continue;
            }

            // Si está unfixed, siempre afecta
            if ($unfixed === '1') {
                $applicable[] = $this->formatVulnerability($vuln, null);
                continue;
            }

            // Comparar versión detectada con max_version
            if ($maxVersion !== null) {
                $isAffected = false;
                if ($maxOperator === 'le') {
                    $isAffected = version_compare($detectedVersion, $maxVersion, '<=');
                } elseif ($maxOperator === 'lt') {
                    $isAffected = version_compare($detectedVersion, $maxVersion, '<');
                }

                if ($isAffected) {
                    $applicable[] = $this->formatVulnerability($vuln, $maxVersion);
                }
            }
        }

        return $applicable;
    }

    /**
     * Formatea una vulnerabilidad de la API en un formato estandarizado
     */
    private function formatVulnerability(array $vuln, ?string $fixedInVersion): array {
        $sources = $vuln['source'] ?? [];
        $cveId = '';
        $description = $vuln['name'] ?? 'Vulnerabilidad desconocida';

        foreach ($sources as $source) {
            $sourceId = $source['id'] ?? '';
            if (str_starts_with($sourceId, 'CVE-')) {
                $cveId = $sourceId;
            }
            if (!empty($source['description'])) {
                $description = $source['description'];
            }
        }

        $impact = $vuln['impact']['cvss'] ?? [];
        $cvssScore = $impact['score'] ?? null;
        $severity = strtolower($impact['severity'] ?? 'medium');

        $unfixed = (string) ($vuln['operator']['unfixed'] ?? '0');

        return [
            'name' => $vuln['name'] ?? 'Vulnerabilidad desconocida',
            'cve' => $cveId,
            'severity' => $severity,
            'cvssScore' => $cvssScore,
            'description' => $description,
            'fixedInVersion' => $unfixed === '1' ? null : $fixedInVersion,
            'unfixed' => $unfixed === '1',
        ];
    }

    /**
     * Verifica vulnerabilidades del WordPress core
     */
    private function checkCoreVulnerabilities(): ?array {
        $wpVersion = $this->wpData['wpVersion'] ?? null;
        if ($wpVersion === null) {
            return null;
        }

        $vulnerable = [];

        try {
            $apiData = $this->queryWpVulnerabilityApi('core', $wpVersion);

            if ($apiData !== null && isset($apiData['data']['vulnerability'])) {
                $vulnerable = $this->filterApplicableVulnerabilities(
                    $apiData['data']['vulnerability'],
                    $wpVersion
                );
            }
        } catch (Throwable $e) {
            Logger::warning('Error verificando vulnerabilidades core: ' . $e->getMessage());
        }

        $count = count($vulnerable);
        $score = Scoring::clamp(100 - ($count * 25));

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $latestVersion = $defaults['latest_wp_version'];

        $detailsList = array_map(function (array $v) {
            $line = $v['name'];
            if ($v['cve']) $line .= " ({$v['cve']})";
            if ($v['cvssScore']) $line .= " — CVSS: {$v['cvssScore']}";
            if ($v['unfixed']) $line .= ' — SIN CORRECCIÓN';
            return $line;
        }, $vulnerable);

        return Scoring::createMetric(
            'core_vulnerabilities',
            'Vulnerabilidades de WordPress Core',
            $count,
            $count > 0
                ? "$count vulnerabilidades en WordPress $wpVersion"
                : "WordPress $wpVersion — sin vulnerabilidades conocidas",
            $score,
            $count > 0
                ? "Tu versión de WordPress ($wpVersion) tiene $count vulnerabilidades conocidas. La versión actual es $latestVersion."
                : "No se detectaron vulnerabilidades conocidas para WordPress $wpVersion.",
            $count > 0 ? "Actualizar WordPress a la versión $latestVersion inmediatamente." : '',
            'Actualizamos WordPress semanalmente con testing previo de compatibilidad.',
            ['vulnerabilities' => $vulnerable, 'details' => $detailsList]
        );
    }

    /**
     * Verifica vulnerabilidades conocidas en plugins detectados
     * Fuente principal: API WPVulnerability.net
     * Fallback: tabla local vulnerabilities en SQLite
     */
    private function checkPluginVulnerabilities(): ?array {
        $plugins = $this->wpData['plugins'] ?? [];

        // Si no se pasaron plugins desde el detector, extraer slugs del HTML
        if (empty($plugins)) {
            preg_match_all('#/wp-content/plugins/([a-z0-9_-]+)/#i', $this->html, $matches);
            if (!empty($matches[1])) {
                $slugs = array_unique($matches[1]);
                foreach ($slugs as $slug) {
                    $plugins[] = ['slug' => $slug, 'name' => $slug, 'detectedVersion' => null];
                }
            }
        }

        if (empty($plugins)) {
            return null;
        }

        $vulnerable = [];
        $pluginsChecked = 0;

        foreach ($plugins as $plugin) {
            if ($pluginsChecked >= 15) break;

            $slug = $plugin['slug'] ?? '';
            $name = $plugin['name'] ?? $slug;
            $version = $plugin['detectedVersion'] ?? null;

            if (empty($slug)) continue;
            $pluginsChecked++;

            // Respetar rate limit: 200ms entre requests
            if ($pluginsChecked > 1) {
                usleep(200000);
            }

            $foundViaApi = false;

            try {
                $apiData = $this->queryWpVulnerabilityApi('plugin', $slug);

                if ($apiData !== null && isset($apiData['data']['vulnerability'])) {
                    $pluginVulns = $this->filterApplicableVulnerabilities(
                        $apiData['data']['vulnerability'],
                        $version
                    );

                    foreach ($pluginVulns as $v) {
                        $v['plugin'] = $apiData['data']['name'] ?? $name;
                        $v['pluginSlug'] = $slug;
                        $v['detectedVersion'] = $version;
                        $vulnerable[] = $v;
                    }

                    $foundViaApi = true;
                }
            } catch (Throwable $e) {
                Logger::warning("WPVulnerability API error para plugin $slug: " . $e->getMessage());
            }

            // Fallback: consultar tabla local si la API no retornó datos
            if (!$foundViaApi) {
                try {
                    $db = Database::getInstance();
                    $rows = $db->query(
                        "SELECT * FROM vulnerabilities WHERE plugin_slug = ?",
                        [$slug]
                    );
                    foreach ($rows as $row) {
                        $vulnerable[] = [
                            'plugin' => $row['plugin_name'],
                            'pluginSlug' => $slug,
                            'detectedVersion' => $version,
                            'name' => $row['description'],
                            'cve' => $row['cve_id'] ?? '',
                            'severity' => $row['severity'] ?? 'medium',
                            'cvssScore' => null,
                            'description' => $row['description'],
                            'fixedInVersion' => $row['fixed_in_version'] ?? null,
                            'unfixed' => false,
                        ];
                    }
                } catch (Throwable $e) {
                    Logger::warning("Fallback SQLite error para plugin $slug: " . $e->getMessage());
                }
            }
        }

        $count = count($vulnerable);
        $score = Scoring::clamp(100 - ($count * 20));

        // Construir detalles legibles para el informe
        $detailsList = array_map(function (array $v) {
            $line = ($v['plugin'] ?? 'Plugin') . ': ' . $v['name'];
            if (!empty($v['cve'])) $line .= " ({$v['cve']})";
            if (!empty($v['cvssScore'])) $line .= " — CVSS: {$v['cvssScore']}";
            if ($v['unfixed'] ?? false) {
                $line .= ' — ALERTA: Sin corrección disponible';
            } elseif (!empty($v['fixedInVersion'])) {
                $line .= " — Actualiza a versión {$v['fixedInVersion']}";
            }
            return $line;
        }, $vulnerable);

        return Scoring::createMetric(
            'plugin_vulnerabilities',
            'Vulnerabilidades en Plugins',
            $count,
            $count > 0 ? "$count vulnerabilidades detectadas" : 'Ninguna detectada',
            $score,
            $count > 0
                ? "Se detectaron $count vulnerabilidades conocidas en los plugins instalados."
                : 'No se detectaron vulnerabilidades conocidas en los plugins.',
            $count > 0 ? 'Actualizar inmediatamente los plugins afectados.' : '',
            'Actualizamos todos tus plugins semanalmente y monitoreamos vulnerabilidades activamente.',
            ['vulnerabilities' => $vulnerable, 'details' => $detailsList]
        );
    }

    /**
     * Verifica vulnerabilidades del tema activo
     */
    private function checkThemeVulnerabilities(): ?array {
        $theme = $this->wpData['theme'] ?? [];
        $slug = $theme['slug'] ?? null;
        $version = $theme['version'] ?? null;
        $name = $theme['name'] ?? $slug;

        if ($slug === null) {
            return null;
        }

        $vulnerable = [];

        // Respetar rate limit
        usleep(200000);

        try {
            $apiData = $this->queryWpVulnerabilityApi('theme', $slug);

            if ($apiData !== null && isset($apiData['data']['vulnerability'])) {
                $themeVulns = $this->filterApplicableVulnerabilities(
                    $apiData['data']['vulnerability'],
                    $version
                );

                foreach ($themeVulns as $v) {
                    $v['theme'] = $apiData['data']['name'] ?? $name;
                    $vulnerable[] = $v;
                }
            }
        } catch (Throwable $e) {
            Logger::warning("Error verificando vulnerabilidades del tema $slug: " . $e->getMessage());
        }

        $count = count($vulnerable);
        if ($count === 0) {
            return null; // No mostrar métrica si no hay vulnerabilidades del tema
        }

        $score = Scoring::clamp(100 - ($count * 25));

        $detailsList = array_map(function (array $v) {
            $line = $v['name'];
            if (!empty($v['cve'])) $line .= " ({$v['cve']})";
            if (!empty($v['cvssScore'])) $line .= " — CVSS: {$v['cvssScore']}";
            if ($v['unfixed'] ?? false) {
                $line .= ' — ALERTA: Sin corrección disponible';
            } elseif (!empty($v['fixedInVersion'])) {
                $line .= " — Actualiza a versión {$v['fixedInVersion']}";
            }
            return $line;
        }, $vulnerable);

        return Scoring::createMetric(
            'theme_vulnerabilities',
            "Vulnerabilidades del Tema ($name)",
            $count,
            "$count vulnerabilidades detectadas",
            $score,
            "El tema $name tiene $count vulnerabilidades conocidas.",
            'Actualizar el tema a la última versión o reemplazarlo.',
            'Mantenemos tu tema actualizado y protegido contra vulnerabilidades.',
            ['vulnerabilities' => $vulnerable, 'details' => $detailsList]
        );
    }

    private function checkExposedEmail(): array {
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $this->html, $matches);
        $emails = array_unique($matches[0] ?? []);
        // Filtrar emails que son parte de schemas o scripts
        $realEmails = array_filter($emails, fn($e) => !str_contains($e, 'example.com') && !str_contains($e, 'wixpress') && !str_contains($e, 'schema.org'));
        $realEmails = array_values($realEmails);
        $count = count($realEmails);

        return Scoring::createMetric(
            'exposed_email', 'Email expuesto en texto plano', $count,
            $count === 0 ? 'No detectado' : "$count email(s) expuesto(s)",
            $count === 0 ? 100 : 50,
            $count === 0
                ? 'No se detectaron direcciones de email en texto plano. Correcto.'
                : "Se encontraron $count email(s) en texto plano: " . implode(', ', array_slice($realEmails, 0, 3)) . '. Los bots de spam rastrean la web buscando emails expuestos.',
            $count > 0 ? 'Ocultar los emails usando formularios de contacto o codificación JavaScript.' : '',
            'Protegemos los emails de contacto contra bots de spam.',
            ['emails' => array_slice($realEmails, 0, 5)]
        );
    }

    private function checkDmarc(): array {
        $records = @dns_get_record('_dmarc.' . $this->host, DNS_TXT);
        $hasDmarc = false;
        $dmarcValue = '';

        if ($records) {
            foreach ($records as $r) {
                $txt = $r['txt'] ?? '';
                if (stripos($txt, 'v=DMARC1') !== false) {
                    $hasDmarc = true;
                    $dmarcValue = $txt;
                    break;
                }
            }
        }

        return Scoring::createMetric(
            'dmarc', 'Registro DMARC', $hasDmarc,
            $hasDmarc ? 'Configurado' : 'No encontrado',
            $hasDmarc ? 100 : 40,
            $hasDmarc
                ? 'DMARC está configurado para este dominio. Protege contra suplantación de identidad por email.'
                : 'No se encontró registro DMARC. Sin DMARC, cualquiera puede enviar emails suplantando tu dominio (phishing/spoofing).',
            $hasDmarc ? '' : 'Configurar un registro DMARC en el DNS del dominio para proteger contra suplantación de email.',
            'Configuramos DMARC, SPF y DKIM para proteger tu dominio contra phishing.',
            ['value' => $dmarcValue]
        );
    }

    private function checkSafeBrowsing(): array {
        // Try Google Safe Browsing Lookup API (requires API key)
        $apiKey = env('GOOGLE_PAGESPEED_API_KEY', '');
        if (empty($apiKey)) {
            try {
                $db = Database::getInstance();
                $row = $db->queryOne("SELECT value FROM settings WHERE key = 'google_pagespeed_api_key'");
                if ($row && !empty($row['value'])) $apiKey = $row['value'];
            } catch (Throwable $e) {}
        }

        if (empty($apiKey)) {
            return Scoring::createMetric(
                'safe_browsing', 'Google Safe Browsing', null, 'Sin API key',
                null, // Informativo
                'No se pudo verificar Google Safe Browsing (requiere API key de Google). La misma key de PageSpeed funciona.',
                '', 'Monitoreamos que tu sitio no aparezca en listas negras de Google.'
            );
        }

        $url = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . urlencode($apiKey);
        $requestBody = [
            'client' => ['clientId' => 'imagina-audit', 'clientVersion' => '1.0'],
            'threatInfo' => [
                'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                'platformTypes' => ['ANY_PLATFORM'],
                'threatEntryTypes' => ['URL'],
                'threatEntries' => [['url' => $this->url]],
            ],
        ];

        try {
            $response = Fetcher::post($url, $requestBody, 5);

            if ($response['statusCode'] !== 200) {
                return Scoring::createMetric(
                    'safe_browsing', 'Google Safe Browsing', null, 'Error en API',
                    null,
                    'No se pudo consultar Google Safe Browsing (error ' . $response['statusCode'] . ').',
                    '', 'Monitoreamos que tu sitio no aparezca en listas negras.'
                );
            }

            $data = json_decode($response['body'], true);
            $threats = $data['matches'] ?? [];
            $isSafe = empty($threats);

            if ($isSafe) {
                return Scoring::createMetric(
                    'safe_browsing', 'Google Safe Browsing', true, 'Sitio seguro',
                    100,
                    'El sitio NO aparece en la lista negra de Google Safe Browsing. No se detectó malware, phishing ni software no deseado.',
                    '', 'Monitoreamos continuamente que tu sitio no sea marcado como peligroso.'
                );
            }

            $threatTypes = array_map(fn($t) => $t['threatType'] ?? 'Unknown', $threats);
            $threatLabels = [
                'MALWARE' => 'Malware',
                'SOCIAL_ENGINEERING' => 'Phishing/Ingeniería social',
                'UNWANTED_SOFTWARE' => 'Software no deseado',
                'POTENTIALLY_HARMFUL_APPLICATION' => 'Aplicación peligrosa',
            ];
            $labels = array_map(fn($t) => $threatLabels[$t] ?? $t, $threatTypes);

            return Scoring::createMetric(
                'safe_browsing', 'Google Safe Browsing', false,
                'EN LISTA NEGRA',
                0,
                'ALERTA: El sitio está marcado como peligroso por Google: ' . implode(', ', $labels) . '. Google muestra una advertencia roja a los usuarios que intentan visitarlo.',
                'Limpiar el sitio de malware/contenido malicioso y solicitar una revisión en Google Search Console.',
                'Limpiamos sitios infectados y solicitamos la remoción de la lista negra de Google.',
                ['threats' => $threats, 'threatTypes' => $threatTypes]
            );
        } catch (Throwable $e) {
            return Scoring::createMetric(
                'safe_browsing', 'Google Safe Browsing', null, 'Error',
                null,
                'No se pudo verificar: ' . $e->getMessage(),
                '', 'Monitoreamos que tu sitio no aparezca en listas negras.'
            );
        }
    }

    private function checkSpf(): array {
        $records = @dns_get_record($this->host, DNS_TXT);
        $hasSpf = false;
        $spfValue = '';

        if ($records) {
            foreach ($records as $r) {
                $txt = $r['txt'] ?? '';
                if (stripos($txt, 'v=spf1') !== false) {
                    $hasSpf = true;
                    $spfValue = $txt;
                    break;
                }
            }
        }

        return Scoring::createMetric(
            'spf', 'Registro SPF', $hasSpf,
            $hasSpf ? 'Configurado' : 'No encontrado',
            $hasSpf ? 100 : 50,
            $hasSpf
                ? 'SPF configurado. Especifica qué servidores pueden enviar email en nombre del dominio.'
                : 'No se encontró SPF. Cualquiera puede enviar email suplantando tu dominio.',
            $hasSpf ? '' : 'Configurar un registro SPF (TXT) que liste los servidores autorizados a enviar email.',
            'Configuramos SPF, DKIM y DMARC para proteger tu dominio.',
            ['value' => $spfValue]
        );
    }

    private function checkSourceCodeExposure(): array {
        $paths = ['/.git/config', '/.git/HEAD', '/.svn/entries', '/.hg/hgrc', '/.DS_Store'];
        $found = [];
        foreach ($paths as $p) {
            $resp = Fetcher::head($this->url . $p, 3);
            if ($resp['statusCode'] === 200) $found[] = $p;
        }
        $count = count($found);
        return Scoring::createMetric(
            'source_code_exposure', 'Exposición de código fuente', $count,
            $count === 0 ? 'Protegido' : "$count archivos expuestos",
            $count === 0 ? 100 : 0,
            $count === 0
                ? 'No se detectaron archivos de control de versiones expuestos (.git, .svn). Correcto.'
                : 'CRÍTICO: Archivos de control de versiones accesibles: ' . implode(', ', $found) . '. Un atacante puede descargar todo el código fuente incluyendo credenciales.',
            $count > 0 ? 'Bloquear acceso a /.git/, /.svn/, etc. en .htaccess o eliminar estos directorios del servidor web.' : '',
            'Protegemos contra fugas de código fuente y archivos de sistema.',
            ['files' => $found]
        );
    }

    private function checkHstsPreload(): array {
        $hsts = $this->headers['strict-transport-security'] ?? '';
        $hasHsts = !empty($hsts);
        $hasPreload = stripos($hsts, 'preload') !== false;
        $hasIncludeSubDomains = stripos($hsts, 'includesubdomains') !== false;

        $maxAge = 0;
        if (preg_match('/max-age=(\d+)/i', $hsts, $m)) {
            $maxAge = (int)$m[1];
        }

        $preloadReady = $hasHsts && $hasPreload && $hasIncludeSubDomains && $maxAge >= 31536000;

        return Scoring::createMetric(
            'hsts_preload', 'HSTS Preload',
            $preloadReady ? 'ready' : ($hasHsts ? 'partial' : 'none'),
            $preloadReady ? 'Listo para preload' : ($hasHsts ? 'HSTS sin preload' : 'Sin HSTS'),
            $preloadReady ? 100 : ($hasHsts ? 70 : 40),
            $preloadReady
                ? 'HSTS completamente configurado con preload, includeSubDomains y max-age >= 1 año. Listo para enviar a hstspreload.org.'
                : ($hasHsts
                    ? 'HSTS presente pero falta preload/includeSubDomains/max-age suficiente para calificar al preload list de Chrome.'
                    : 'Sin HSTS. Configurar para forzar HTTPS y poder solicitar inclusión en el preload list.'),
            !$preloadReady ? 'Configurar: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload. Luego registrar en hstspreload.org' : '',
            'Configuramos HSTS con preload para máxima protección HTTPS.',
            ['value' => $hsts, 'maxAge' => $maxAge, 'hasPreload' => $hasPreload, 'hasIncludeSubDomains' => $hasIncludeSubDomains]
        );
    }

    private function checkSubresourceIntegrity(): array {
        // Count external scripts without integrity attribute
        preg_match_all('/<script[^>]+src=["\']https?:\/\/([^"\']+)["\'][^>]*>/i', $this->html, $matches, PREG_SET_ORDER);
        $external = 0;
        $withIntegrity = 0;
        $withoutIntegrity = [];

        foreach ($matches as $m) {
            $src = $m[1];
            // Solo scripts de otro dominio
            if (str_contains($src, $this->host)) continue;
            $external++;
            if (str_contains($m[0], 'integrity=')) $withIntegrity++;
            else $withoutIntegrity[] = $src;
        }

        if ($external === 0) {
            return Scoring::createMetric(
                'sri', 'Subresource Integrity (SRI)', null, 'Sin scripts externos', null,
                'No se detectaron scripts externos. SRI no aplica.',
                '', 'Configuramos SRI en scripts de CDN para protección contra CDN comprometido.'
            );
        }

        $pct = (int) round(($withIntegrity / $external) * 100);
        return Scoring::createMetric(
            'sri', 'Subresource Integrity (SRI)', $pct, "$withIntegrity/$external con SRI",
            $pct >= 80 ? 100 : ($pct >= 50 ? 70 : 40),
            "De $external scripts externos, $withIntegrity tienen atributo integrity ($pct%). SRI protege contra CDNs comprometidos.",
            $pct < 80 ? 'Agregar atributo integrity="sha384-..." a todos los scripts cargados desde CDN externo.' : '',
            'Implementamos SRI en todos los recursos externos.',
            ['external' => $external, 'withIntegrity' => $withIntegrity, 'withoutIntegrity' => array_slice($withoutIntegrity, 0, 5)]
        );
    }

    private function checkDnssec(): array {
        // Check if parent zone has DS record for this domain
        $parts = explode('.', $this->host);
        if (count($parts) < 2) {
            return Scoring::createMetric('dnssec', 'DNSSEC', null, 'N/A', null, 'Dominio inválido.', '', '');
        }
        $domain = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $this->host;

        $records = @dns_get_record($domain, DNS_ANY);
        $hasDnssec = false;
        if ($records) {
            foreach ($records as $r) {
                if (isset($r['type']) && in_array($r['type'], ['DS', 'DNSKEY', 'RRSIG'])) {
                    $hasDnssec = true;
                    break;
                }
            }
        }

        return Scoring::createMetric(
            'dnssec', 'DNSSEC', $hasDnssec,
            $hasDnssec ? 'Habilitado' : 'No habilitado',
            $hasDnssec ? 100 : 60,
            $hasDnssec
                ? 'DNSSEC habilitado. Protege contra envenenamiento de caché DNS y redirecciones maliciosas.'
                : 'DNSSEC no habilitado. Sin firma DNS, es posible suplantar los registros DNS del dominio.',
            !$hasDnssec ? 'Habilitar DNSSEC en tu registrador o proveedor DNS (Cloudflare lo hace con 1 click).' : '',
            'Configuramos DNSSEC para proteger contra ataques de DNS.'
        );
    }

    private function checkWeakTlsVersions(): array {
        // Intentar conectar con TLS 1.0 y 1.1 explícitamente
        $weakFound = [];
        $contexts = [
            'TLS 1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT ?? 32,
            'TLS 1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT ?? 128,
        ];

        foreach ($contexts as $version => $method) {
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'crypto_method' => $method,
                ],
            ]);
            $errno = 0; $errstr = '';
            $sock = @stream_socket_client("ssl://{$this->host}:443", $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $ctx);
            if ($sock) {
                $weakFound[] = $version;
                fclose($sock);
            }
        }

        $count = count($weakFound);
        return Scoring::createMetric(
            'weak_tls', 'Versiones TLS débiles', $count,
            $count === 0 ? 'Solo TLS 1.2+' : implode(', ', $weakFound) . ' habilitado',
            $count === 0 ? 100 : ($count === 1 ? 50 : 20),
            $count === 0
                ? 'El servidor solo acepta TLS 1.2 y superior. Correcto.'
                : 'El servidor acepta versiones TLS débiles: ' . implode(', ', $weakFound) . '. Son vulnerables a ataques como POODLE y BEAST.',
            $count > 0 ? 'Desactivar TLS 1.0 y 1.1 en la configuración del servidor. Solo habilitar TLS 1.2 y TLS 1.3.' : '',
            'Configuramos el servidor para aceptar solo versiones modernas de TLS.',
            ['weakVersions' => $weakFound]
        );
    }

    private function checkWpInfoFiles(): array {
        $files = ['/readme.html', '/license.txt', '/wp-config-sample.php'];
        $exposed = [];
        foreach ($files as $f) {
            $resp = Fetcher::head($this->url . $f, 3);
            if ($resp['statusCode'] === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_info_files', 'Archivos de información de WordPress', $count,
            $count === 0 ? 'Protegidos' : "$count archivos expuestos",
            $count === 0 ? 100 : 50,
            $count === 0
                ? 'Los archivos informativos de WordPress (readme.html, license.txt) están protegidos. Correcto.'
                : 'Archivos expuestos: ' . implode(', ', $exposed) . '. readme.html revela la versión exacta de WordPress facilitando ataques dirigidos.',
            $count > 0 ? 'Eliminar o bloquear acceso a estos archivos en .htaccess.' : '',
            'Eliminamos archivos informativos que revelan la versión de WordPress.',
            ['files' => $exposed]
        );
    }

    private function checkWpInstallFiles(): array {
        $files = ['/wp-admin/install.php', '/wp-admin/upgrade.php', '/wp-admin/install-helper.php'];
        $exposed = [];
        foreach ($files as $f) {
            $resp = Fetcher::head($this->url . $f, 3);
            // Some install files return 200 but show "already installed". Check that 403/404 is the safe state.
            if ($resp['statusCode'] === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_install_files', 'Archivos de instalación WordPress', $count,
            $count === 0 ? 'Protegidos' : "$count accesibles",
            $count === 0 ? 100 : 30,
            $count === 0
                ? 'Los archivos de instalación de WordPress están protegidos.'
                : 'Archivos de instalación accesibles: ' . implode(', ', $exposed) . '. Pueden ser explotados en ciertos escenarios.',
            $count > 0 ? 'Bloquear acceso a /wp-admin/install.php, /wp-admin/upgrade.php vía .htaccess después de la instalación.' : '',
            'Bloqueamos archivos de instalación después del setup inicial.',
            ['files' => $exposed]
        );
    }

    private function checkPhpInUploads(): array {
        // Verificar si hay archivos .php en /wp-content/uploads/
        $testPaths = [
            '/wp-content/uploads/',
            '/wp-content/uploads/2024/',
            '/wp-content/uploads/2025/',
            '/wp-content/uploads/2026/',
        ];

        // Fetch uploads listing
        $response = Fetcher::get($this->url . '/wp-content/uploads/', 5, true, 0);
        $foundPhp = [];

        if ($response['statusCode'] === 200 && str_contains($response['body'], 'Index of')) {
            // Extract .php files from directory listing
            preg_match_all('/href=["\']([^"\']+\.php)["\']/i', $response['body'], $matches);
            foreach ($matches[1] ?? [] as $phpFile) {
                if (!str_starts_with($phpFile, '?')) $foundPhp[] = $phpFile;
            }
        }

        $count = count($foundPhp);
        return Scoring::createMetric(
            'php_in_uploads', 'Archivos PHP en uploads', $count,
            $count === 0 ? 'Ninguno detectado' : "$count archivos PHP",
            $count === 0 ? 100 : 0,
            $count === 0
                ? 'No se detectaron archivos PHP en /wp-content/uploads/. Correcto.'
                : 'CRÍTICO: Se detectaron ' . $count . ' archivos PHP en /wp-content/uploads/. Esto es un fuerte indicador de malware o backdoor instalado por un atacante.',
            $count > 0 ? 'Revisar cada archivo PHP en uploads, escanear el sitio con un plugin de seguridad, cambiar todas las contraseñas, y agregar regla en .htaccess para bloquear ejecución de PHP en uploads.' : '',
            'Escaneamos y limpiamos malware de los directorios de uploads.',
            ['phpFiles' => array_slice($foundPhp, 0, 10)]
        );
    }

    private function checkRestApiEnumerationExtra(): array {
        $endpoints = [
            '/wp-json/wp/v2/users',
            '/wp-json/wp/v2/pages',
            '/wp-json/wp/v2/posts',
            '/wp-json/wp/v2/media',
        ];
        $exposed = [];

        foreach ($endpoints as $ep) {
            $resp = Fetcher::get($this->url . $ep, 3, true, 0);
            if ($resp['statusCode'] === 200) {
                $data = json_decode($resp['body'], true);
                if (is_array($data) && !empty($data)) {
                    $exposed[] = ['endpoint' => $ep, 'count' => count($data)];
                }
            }
        }

        $count = count($exposed);
        return Scoring::createMetric(
            'rest_api_enumeration', 'REST API — Enumeración', $count,
            $count === 0 ? 'Protegida' : count($exposed) . ' endpoints exponen datos',
            $count === 0 ? 100 : ($count >= 3 ? 30 : 50),
            $count === 0
                ? 'La REST API no expone datos públicamente. Correcto.'
                : 'La REST API expone datos en: ' . implode(', ', array_map(fn($e) => $e['endpoint'], $exposed)) . '. Facilita recolectar información del sitio.',
            $count > 0 ? 'Restringir acceso a endpoints REST API para usuarios no autenticados usando plugin de seguridad o código personalizado.' : '',
            'Protegemos la REST API de WordPress contra enumeración de datos.',
            ['endpoints' => $exposed]
        );
    }

    private function checkDefaultAdminUser(): array {
        // Check common default usernames via /?author=N and REST API
        $defaultUsers = ['admin', 'administrator', 'test', 'demo', 'user', 'wordpress'];
        $detectedUsers = [];

        // Try REST API
        $response = Fetcher::get($this->url . '/wp-json/wp/v2/users', 3, true, 0);
        if ($response['statusCode'] === 200) {
            $data = json_decode($response['body'], true);
            if (is_array($data)) {
                foreach ($data as $u) {
                    $slug = strtolower($u['slug'] ?? '');
                    if (in_array($slug, $defaultUsers)) {
                        $detectedUsers[] = $slug;
                    }
                }
            }
        }

        // Try /?author=1 to get the first user's slug
        if (empty($detectedUsers)) {
            for ($i = 1; $i <= 3; $i++) {
                $resp = Fetcher::get($this->url . '/?author=' . $i, 3, false, 0);
                if (in_array($resp['statusCode'], [301, 302])) {
                    $location = $resp['headers']['location'] ?? '';
                    if (preg_match('#/author/([^/]+)/?#i', $location, $m)) {
                        $slug = strtolower($m[1]);
                        if (in_array($slug, $defaultUsers)) {
                            $detectedUsers[] = $slug;
                        }
                    }
                }
            }
        }

        $count = count(array_unique($detectedUsers));
        return Scoring::createMetric(
            'default_admin_user', 'Usuario admin por defecto', $count,
            $count === 0 ? 'Ninguno' : implode(', ', array_unique($detectedUsers)),
            $count === 0 ? 100 : 20,
            $count === 0
                ? 'No se detectaron nombres de usuario por defecto (admin, administrator, etc.).'
                : 'Usuarios con nombre predecible detectados: ' . implode(', ', array_unique($detectedUsers)) . '. Los atacantes apuntan a estos nombres para ataques de fuerza bruta.',
            $count > 0 ? 'Crear un nuevo usuario admin con nombre personalizado y eliminar el usuario por defecto.' : '',
            'Renombramos usuarios admin por defecto para prevenir ataques de fuerza bruta.',
            ['detected' => array_values(array_unique($detectedUsers))]
        );
    }

    private function checkSecurityPlugin(): array {
        $plugins = [
            'wordfence' => 'Wordfence',
            'sucuri' => 'Sucuri',
            'ithemes-security' => 'Solid Security (iThemes)',
            'better-wp-security' => 'Solid Security (iThemes)',
            'all-in-one-wp-security' => 'All In One WP Security',
            'really-simple-ssl' => 'Really Simple SSL',
            'defender-security' => 'Defender Security',
            'shield-security' => 'Shield Security',
            'jetpack' => 'Jetpack (incluye security)',
        ];

        $detected = [];
        foreach ($plugins as $slug => $name) {
            if (str_contains($this->html, "/$slug/") || stripos($this->html, $slug) !== false) {
                $detected[] = $name;
            }
        }
        $detected = array_unique($detected);
        $count = count($detected);

        return Scoring::createMetric(
            'security_plugin', 'Plugin de seguridad', $count,
            $count === 0 ? 'No detectado' : implode(', ', $detected),
            $count > 0 ? 100 : 70,
            $count > 0
                ? 'Se detectó plugin de seguridad: ' . implode(', ', $detected) . '. Ayuda a proteger contra malware, brute force y vulnerabilidades.'
                : 'No se detectó ningún plugin de seguridad instalado. Se recomienda usar Wordfence, Solid Security u otro.',
            $count === 0 ? 'Instalar un plugin de seguridad como Wordfence o Solid Security para protección adicional.' : '',
            'Instalamos y configuramos plugins de seguridad para protección en múltiples capas.',
            ['detected' => $detected]
        );
    }
}
