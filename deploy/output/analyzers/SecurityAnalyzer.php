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

        $isWordPress = $this->wpData['isWordPress'] ?? str_contains($this->html, '/wp-content/');

        if ($isWordPress) {
            // Directory listing
            $metrics[] = $this->checkDirectoryListing();

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
}
