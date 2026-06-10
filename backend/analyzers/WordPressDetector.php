<?php
/**
 * Detecta WordPress, versión, plugins, tema, y exposiciones de seguridad
 */

class WordPressDetector {
    private string $url;
    private string $html;
    private array $headers;
    private HtmlParser $parser;
    private bool $isWordPress = false;
    private array $soft404;  // ['active' => bool, ...] del orchestrator

    /** Datos detectados expuestos para otros analyzers */
    private ?string $detectedWpVersion = null;
    private array $detectedPlugins = [];
    private array $detectedThemeInfo = ['slug' => null, 'name' => null, 'version' => null, 'childTheme' => false];

    public function __construct(string $url, string $html, array $headers, array $soft404 = ['active' => false]) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
        $this->soft404 = $soft404;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Ejecuta el análisis completo de WordPress
     */
    public function analyze(): array {
        // Detectar si es WordPress
        $this->isWordPress = $this->detectWordPress();

        $metrics = [];

        if (!$this->isWordPress) {
            $defaults = require dirname(__DIR__) . '/config/defaults.php';
            return [
                'id' => 'wordpress',
                'name' => Translator::t('modules.wordpress.name'),
                'icon' => 'blocks',
                'score' => null,
                'level' => 'info',
                'weight' => $defaults['weight_wordpress'],
                'metrics' => [Scoring::createMetric(
                    'wp_detected',
                    Translator::t('wordpress.not_wp.name'),
                    false,
                    Translator::t('wordpress.not_wp.display'),
                    null,
                    Translator::t('wordpress.not_wp.description'),
                    '',
                    Translator::t('wordpress.not_wp.solution')
                )],
                'summary' => Translator::t('wordpress.not_wp.module_summary'),
                'salesMessage' => $defaults['sales_wordpress'] !== '' ? $defaults['sales_wordpress'] : Translator::t('modules.sales.wordpress'),
            ];
        }

        // Versión de WordPress
        $wpVersion = $this->detectVersion();
        $this->detectedWpVersion = $wpVersion;
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $latestVersion = $defaults['latest_wp_version'];
        $isCurrent = $wpVersion !== null && version_compare($wpVersion, $latestVersion, '>=');

        $versionScore = 100;
        if ($wpVersion === null) {
            $versionScore = 70; // No detectada, no penalizar mucho
        } elseif (!$isCurrent) {
            $versionScore = 40;
        }

        $metrics[] = Scoring::createMetric(
            'wp_version',
            Translator::t('wordpress.version.name'),
            $wpVersion,
            $wpVersion ? "WordPress $wpVersion" : Translator::t('wordpress.version.display.none'),
            $versionScore,
            $wpVersion
                ? ($isCurrent
                    ? Translator::t('wordpress.version.desc.current')
                    : Translator::t('wordpress.version.desc.outdated', ['version' => $wpVersion, 'latest' => $latestVersion]))
                : Translator::t('wordpress.version.desc.unknown'),
            $isCurrent ? '' : Translator::t('wordpress.version.recommendation'),
            Translator::t('wordpress.version.solution')
        );

        // Tema
        $themeInfo = $this->detectTheme();
        $this->detectedThemeInfo = [
            'slug' => $themeInfo['slug'] ?? null,
            'name' => $themeInfo['name'],
            'version' => $themeInfo['version'],
            'childTheme' => $themeInfo['childTheme'],
        ];
        $themeName = $themeInfo['name'] ?: Translator::t('wordpress.theme.display.none');
        $themeVersion = $themeInfo['version'] ?? null;
        $themeDisplay = $themeName . ($themeVersion ? " v$themeVersion" : '');

        $metrics[] = Scoring::createMetric(
            'wp_theme',
            Translator::t('wordpress.theme.name'),
            $themeInfo['name'],
            $themeDisplay,
            $themeInfo['name'] ? 100 : 50,
            $themeInfo['name']
                ? Translator::t('wordpress.theme.desc.found', ['display' => $themeDisplay])
                : Translator::t('wordpress.theme.desc.missing'),
            '',
            Translator::t('wordpress.theme.solution'),
            ['themeName' => $themeInfo['name'], 'themeVersion' => $themeVersion, 'childTheme' => $themeInfo['childTheme']]
        );

        // Plugins
        $plugins = $this->detectPlugins();
        $this->detectedPlugins = $plugins;
        $outdatedCount = 0;
        $pluginsList = [];
        foreach ($plugins as $p) {
            if (isset($p['outdated']) && $p['outdated']) {
                $outdatedCount++;
            }
            $pluginsList[] = $p;
        }

        $pluginsScore = Scoring::clamp(100 - ($outdatedCount * 5));
        $pluginCount = count($plugins);
        $outdatedSuffix = $outdatedCount > 0
            ? ' ' . Translator::t('wordpress.plugins.desc.outdated_suffix', ['count' => $outdatedCount])
            : ' ' . Translator::t('wordpress.plugins.desc.all_up_suffix');
        $metrics[] = Scoring::createMetric(
            'wp_plugins',
            Translator::t('wordpress.plugins.name'),
            $pluginCount,
            Translator::t('wordpress.plugins.display', ['count' => $pluginCount, 'outdated' => $outdatedCount]),
            $pluginsScore,
            $pluginCount > 0
                ? Translator::t('wordpress.plugins.desc.found', ['count' => $pluginCount, 'outdatedSuffix' => trim($outdatedSuffix)])
                : Translator::t('wordpress.plugins.desc.none'),
            $outdatedCount > 0 ? Translator::t('wordpress.plugins.recommendation') : '',
            Translator::t('wordpress.plugins.solution'),
            ['plugins' => $pluginsList]
        );

        // Enumeración de usuarios vía /?author=1
        $userEnumResult = $this->checkUserEnumeration();
        $metrics[] = $userEnumResult;

        // REST API expuesta
        $restApiExposed = $this->checkRestApiUsers();
        $restScore = $restApiExposed ? 0 : 100;
        $metrics[] = Scoring::createMetric(
            'rest_api_exposed',
            Translator::t('wordpress.rest_api.name'),
            $restApiExposed,
            $restApiExposed ? Translator::t('wordpress.rest_api.display.exposed') : Translator::t('wordpress.rest_api.display.safe'),
            $restScore,
            $restApiExposed ? Translator::t('wordpress.rest_api.desc.exposed') : Translator::t('wordpress.rest_api.desc.safe'),
            $restApiExposed ? Translator::t('wordpress.rest_api.recommendation') : '',
            Translator::t('wordpress.rest_api.solution')
        );

        // XML-RPC
        $xmlrpcActive = $this->checkXmlRpc();
        $xmlrpcScore = $xmlrpcActive ? 50 : 100;
        $metrics[] = Scoring::createMetric(
            'xmlrpc_active',
            Translator::t('wordpress.xmlrpc.name'),
            $xmlrpcActive,
            $xmlrpcActive ? Translator::t('wordpress.xmlrpc.display.active') : Translator::t('wordpress.xmlrpc.display.inactive'),
            $xmlrpcScore,
            $xmlrpcActive ? Translator::t('wordpress.xmlrpc.desc.active') : Translator::t('wordpress.xmlrpc.desc.inactive'),
            $xmlrpcActive ? Translator::t('wordpress.xmlrpc.recommendation') : '',
            Translator::t('wordpress.xmlrpc.solution')
        );

        // Debug mode
        $debugMode = $this->checkDebugMode();
        $debugScore = $debugMode ? 0 : 100;
        $metrics[] = Scoring::createMetric(
            'debug_mode',
            Translator::t('wordpress.debug.name'),
            $debugMode,
            $debugMode ? Translator::t('wordpress.debug.display.visible') : Translator::t('wordpress.debug.display.hidden'),
            $debugScore,
            $debugMode ? Translator::t('wordpress.debug.desc.visible') : Translator::t('wordpress.debug.desc.hidden'),
            $debugMode ? Translator::t('wordpress.debug.recommendation') : '',
            Translator::t('wordpress.debug.solution')
        );

        // Archivos sensibles
        $sensitiveFiles = $this->checkSensitiveFiles();
        $sensitiveScore = Scoring::clamp(100 - (count($sensitiveFiles) * 30));
        $sensitiveCount = count($sensitiveFiles);
        $metrics[] = Scoring::createMetric(
            'sensitive_files',
            Translator::t('wordpress.sensitive.name'),
            $sensitiveCount,
            $sensitiveCount > 0
                ? Translator::t('wordpress.sensitive.display.exposed', ['count' => $sensitiveCount])
                : Translator::t('wordpress.sensitive.display.none'),
            $sensitiveScore,
            $sensitiveCount > 0
                ? Translator::t('wordpress.sensitive.desc.exposed', ['list' => implode(', ', $sensitiveFiles)])
                : Translator::t('wordpress.sensitive.desc.none'),
            $sensitiveCount > 0 ? Translator::t('wordpress.sensitive.recommendation') : '',
            Translator::t('wordpress.sensitive.solution'),
            ['files' => $sensitiveFiles]
        );

        return $this->buildModuleResult($metrics);
    }

    /**
     * Detecta si el sitio es WordPress
     */
    private function detectWordPress(): bool {
        // 1. Meta generator
        $generator = $this->parser->getMeta('generator');
        if ($generator && stripos($generator, 'wordpress') !== false) {
            return true;
        }

        // 2. Presencia de /wp-content/ en HTML
        if ($this->parser->contains('/wp-content/')) {
            return true;
        }

        // 3. Presencia de /wp-includes/
        if ($this->parser->contains('/wp-includes/')) {
            return true;
        }

        // 4. Link rel=api.w.org
        $apiLink = $this->parser->getLinkByRel('https://api.w.org/');
        if ($apiLink !== null) {
            return true;
        }

        // También verificar en headers
        if (isset($this->headers['link']) && str_contains($this->headers['link'], 'api.w.org')) {
            return true;
        }

        // 5. Fetch /wp-json/ (timeout corto)
        $wpJson = Fetcher::get($this->url . '/wp-json/', 5, false, 0);
        if ($wpJson['statusCode'] === 200) {
            $data = json_decode($wpJson['body'], true);
            if ($data !== null && isset($data['name'])) {
                return true;
            }
        }

        // 6. Fetch /wp-login.php
        $wpLogin = Fetcher::head($this->url . '/wp-login.php', 3);
        if ($wpLogin['statusCode'] === 200) {
            return true;
        }

        return false;
    }

    /**
     * Detecta la versión de WordPress
     */
    private function detectVersion(): ?string {
        // 1. Meta generator
        $generator = $this->parser->getMeta('generator');
        if ($generator && preg_match('/WordPress\s+([\d.]+)/i', $generator, $m)) {
            return $m[1];
        }

        // 2. Feed RSS
        $feed = Fetcher::get($this->url . '/feed/', 5, true, 0);
        if ($feed['statusCode'] === 200 && preg_match('#<generator>.*?v=([\d.]+)</generator>#i', $feed['body'], $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Detecta el tema activo
     */
    private function detectTheme(): array {
        $result = ['slug' => null, 'name' => null, 'version' => null, 'childTheme' => false];

        // Buscar rutas /wp-content/themes/NOMBRE/ en el HTML
        $themes = [];
        preg_match_all('#/wp-content/themes/([a-z0-9_-]+)/#i', $this->html, $matches);
        if (!empty($matches[1])) {
            $themes = array_unique($matches[1]);
        }

        if (empty($themes)) {
            return $result;
        }

        $result['slug'] = $themes[0];
        $result['name'] = $themes[0];
        $result['childTheme'] = count($themes) > 1;

        // Intentar obtener versión del style.css
        $styleUrl = $this->url . '/wp-content/themes/' . $themes[0] . '/style.css';
        $style = Fetcher::get($styleUrl, 5, false, 0);
        if ($style['statusCode'] === 200) {
            if (preg_match('/Theme Name:\s*(.+)/i', $style['body'], $m)) {
                $result['name'] = trim($m[1]);
            }
            if (preg_match('/Version:\s*([\d.]+)/i', $style['body'], $m)) {
                $result['version'] = trim($m[1]);
            }
        }

        return $result;
    }

    /**
     * Detecta plugins a partir de rutas en el HTML.
     *
     * PARALELIZADO: antes hacía 15 plugins × 2 fetches secuenciales ≈ 30s.
     * Ahora dispara las 15 peticiones a wp.org API en paralelo (~3-5s) y
     * luego los 15 readme.txt también en paralelo. Total: ~8-10s.
     */
    private function detectPlugins(): array {
        $plugins = [];
        preg_match_all('#/wp-content/plugins/([a-z0-9_-]+)/#i', $this->html, $matches);

        if (empty($matches[1])) {
            return $plugins;
        }

        $slugs = array_slice(array_values(array_unique($matches[1])), 0, 15);

        // 1. Batch 1: todas las wp.org API en paralelo
        $apiUrls = [];
        foreach ($slugs as $slug) {
            $apiUrls[$slug] = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=' . urlencode($slug);
        }
        $apiResponses = Fetcher::multiGet($apiUrls, 5);

        // Procesar respuestas y preparar batch 2
        $readmeUrls = [];
        foreach ($slugs as $slug) {
            $plugin = [
                'slug' => $slug,
                'name' => str_replace('-', ' ', ucfirst($slug)),
                'detectedVersion' => null,
                'latestVersion' => null,
                'outdated' => false,
            ];
            $api = $apiResponses[$slug] ?? null;
            if ($api && $api['statusCode'] === 200) {
                $data = json_decode($api['body'], true);
                if ($data && isset($data['version'])) {
                    $plugin['name'] = $data['name'] ?? $plugin['name'];
                    $plugin['latestVersion'] = $data['version'];
                    // Solo buscamos readme si la API nos dio una versión con la que comparar
                    $readmeUrls[$slug] = $this->url . '/wp-content/plugins/' . $slug . '/readme.txt';
                }
            }
            $plugins[$slug] = $plugin;
        }

        // 2. Batch 2: todos los readme.txt del sitio auditado en paralelo
        if (!empty($readmeUrls)) {
            $readmeResponses = Fetcher::multiGet($readmeUrls, 3);
            foreach ($readmeResponses as $slug => $readme) {
                if ($readme['statusCode'] === 200 && preg_match('/Stable tag:\s*([\d.]+)/i', $readme['body'], $m)) {
                    $plugins[$slug]['detectedVersion'] = $m[1];
                    if (version_compare($m[1], $plugins[$slug]['latestVersion'], '<')) {
                        $plugins[$slug]['outdated'] = true;
                    }
                }
            }
        }

        return array_values($plugins);
    }

    /**
     * Verifica si la REST API expone usuarios
     */
    private function checkRestApiUsers(): bool {
        $response = Fetcher::get($this->url . '/wp-json/wp/v2/users', 5, true, 0);
        if ($response['statusCode'] === 200) {
            $data = json_decode($response['body'], true);
            return is_array($data) && !empty($data);
        }
        return false;
    }

    /**
     * Verifica si XML-RPC está activo
     */
    private function checkXmlRpc(): bool {
        $response = Fetcher::head($this->url . '/xmlrpc.php', 3);
        return in_array($response['statusCode'], [200, 405]);
    }

    /**
     * Verifica si hay errores PHP visibles. Antes usábamos str_contains
     * con palabras sueltas ("Warning:", "Notice:") sobre todo el HTML, lo
     * que producía masivos falsos positivos: cualquier página con texto
     * legítimo ("Warning:", "Notice:") o un plugin que mencionara
     * "WP_DEBUG" en JS inline marcaba el sitio como crítico.
     * Ahora exigimos el patrón completo de error PHP:
     *   <b>Fatal error</b>:  ...mensaje... in /path on line N
     * que es lo que PHP emite cuando display_errors=On.
     */
    private function checkDebugMode(): bool {
        // Patrón de error PHP estándar — incluye "in /path/ on line N".
        if (preg_match('/(?:<b>)?(Fatal error|Warning|Notice|Parse error|Deprecated)(?:<\/b>)?:\s+[^<]{4,}\s+in\s+\/[^\s<]+\s+on\s+line\s+\d+/i', $this->html)) {
            return true;
        }
        // O el bloque típico de stack trace que PHP emite.
        if (str_contains($this->html, 'Stack trace:') && str_contains($this->html, '#0 ')) {
            return true;
        }
        return false;
    }

    /**
     * Verifica enumeración de usuarios vía /?author=1
     */
    private function checkUserEnumeration(): array {
        // Fetch /?author=1 sin seguir redirects
        $response = Fetcher::get($this->url . '/?author=1', 5, false, 0);

        $exposed = false;
        $username = null;

        // Si redirige a /author/NOMBRE/, el username está expuesto
        if (in_array($response['statusCode'], [301, 302])) {
            $location = $response['headers']['location'] ?? '';
            if (preg_match('#/author/([^/]+)/?#i', $location, $m)) {
                $exposed = true;
                $username = $m[1];
            }
        }

        // Si retorna 200 y contiene /author/ en el body
        if (!$exposed && $response['statusCode'] === 200) {
            if (preg_match('#/author/([a-z0-9_-]+)/?#i', $response['body'] ?? '', $m)) {
                $exposed = true;
                $username = $m[1];
            }
        }

        $score = $exposed ? 30 : 100;
        return Scoring::createMetric(
            'user_enumeration',
            Translator::t('wordpress.user_enum.name'),
            $exposed,
            $exposed
                ? ($username
                    ? Translator::t('wordpress.user_enum.display.exposed_with_user', ['username' => $username])
                    : Translator::t('wordpress.user_enum.display.exposed'))
                : Translator::t('wordpress.user_enum.display.safe'),
            $score,
            $exposed
                ? Translator::t('wordpress.user_enum.desc.exposed', ['username' => $username ?? '?'])
                : Translator::t('wordpress.user_enum.desc.safe'),
            $exposed ? Translator::t('wordpress.user_enum.recommendation') : '',
            Translator::t('wordpress.user_enum.solution')
        );
    }

    /**
     * Verifica archivos sensibles accesibles
     */
    private function checkSensitiveFiles(): array {
        // Si soft-404 está activo, no podemos distinguir archivo real de
        // inexistente — antes este check marcaba los 8 archivos como
        // "expuestos" y le pegaba un -240 al score, generando un informe
        // catastrófico ante un prospecto cuando el sitio simplemente no
        // tiene un 404 configurado. Devolver lista vacía es honesto.
        if (!empty($this->soft404['active'])) return [];

        $filesToCheck = [
            '/wp-config.php.bak',
            '/wp-config.old',
            '/.env',
            '/debug.log',
            '/wp-content/debug.log',
            '/error_log',
            '/backup.zip',
            '/backup.sql',
        ];
        // PARALELIZADO: 8 HEAD en paralelo ~1s vs 24s secuencial
        $urls = [];
        foreach ($filesToCheck as $file) $urls[$file] = $this->url . $file;
        $responses = Fetcher::multiGet($urls, 3);
        $found = [];
        foreach ($filesToCheck as $file) {
            // Validar firma de contenido además del status code:
            // - .env: contiene KEY=VALUE
            // - .bak/.old (wp-config): contiene "define(" o "DB_"
            // - backup.zip: empieza con magic bytes ZIP (PK)
            // - debug.log/error_log: contiene marca de error
            $resp = $responses[$file] ?? null;
            if (!$resp || ($resp['statusCode'] ?? 0) !== 200) continue;
            if (!self::sensitiveFileSignatureMatches($file, $resp['body'] ?? '')) continue;
            $found[] = $file;
        }

        return $found;
    }

    /**
     * Valida que el body de la respuesta contenga firma del archivo
     * sensible esperado, en vez de solo confiar en status 200. Mata los
     * falsos positivos restantes cuando el server-side reescribe paths
     * desconocidos a una página genérica.
     */
    private static function sensitiveFileSignatureMatches(string $path, string $body): bool {
        if ($body === '') return false;
        $head = substr($body, 0, 4096);
        return match (true) {
            str_ends_with($path, '/.env') => (bool) preg_match('/^[A-Z_][A-Z0-9_]*\s*=/m', $head),
            str_contains($path, 'wp-config') => str_contains($head, 'DB_') || str_contains($head, 'define('),
            str_contains($path, '.zip') => str_starts_with($body, 'PK'),
            str_contains($path, '.sql') => (bool) preg_match('/(CREATE TABLE|INSERT INTO|--\s)/i', $head),
            str_contains($path, 'debug.log') || str_contains($path, 'error_log') => (bool) preg_match('/(PHP (Fatal|Warning|Notice|Parse) error|Stack trace)/i', $head),
            default => true,
        };
    }

    /**
     * Construye el resultado del módulo
     */
    private function buildModuleResult(array $metrics): array {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'wordpress',
            'name' => Translator::t('modules.wordpress.name'),
            'icon' => 'blocks',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_wordpress'],
            'metrics' => $metrics,
            'summary' => $this->isWordPress
                ? Translator::t('wordpress.summary.wp', ['score' => $score])
                : Translator::t('wordpress.summary.not_wp'),
            'salesMessage' => $defaults['sales_wordpress'] !== '' ? $defaults['sales_wordpress'] : Translator::t('modules.sales.wordpress'),
        ];
    }

    /**
     * Retorna si el sitio es WordPress (para uso externo)
     */
    public function isWordPress(): bool {
        return $this->isWordPress;
    }

    /**
     * Retorna la versión de WordPress detectada
     */
    public function getDetectedWpVersion(): ?string {
        return $this->detectedWpVersion;
    }

    /**
     * Retorna los plugins detectados con sus versiones
     * Cada elemento: ['slug' => string, 'name' => string, 'detectedVersion' => ?string, ...]
     */
    public function getDetectedPlugins(): array {
        return $this->detectedPlugins;
    }

    /**
     * Retorna la información del tema detectado
     * ['slug' => ?string, 'name' => ?string, 'version' => ?string, 'childTheme' => bool]
     */
    public function getDetectedThemeInfo(): array {
        return $this->detectedThemeInfo;
    }
}
