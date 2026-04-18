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

    /** Datos detectados expuestos para otros analyzers */
    private ?string $detectedWpVersion = null;
    private array $detectedPlugins = [];
    private array $detectedThemeInfo = ['slug' => null, 'name' => null, 'version' => null, 'childTheme' => false];

    public function __construct(string $url, string $html, array $headers) {
        $this->url = rtrim($url, '/');
        $this->html = $html;
        $this->headers = $headers;
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
                'name' => 'WordPress',
                'icon' => 'blocks',
                'score' => null,
                'level' => 'info',
                'weight' => $defaults['weight_wordpress'],
                'metrics' => [Scoring::createMetric(
                    'wp_detected',
                    'Detección de WordPress',
                    false,
                    'No es WordPress',
                    null,
                    'No se detectó WordPress en este sitio. Este módulo no aplica y no afecta la puntuación global.',
                    '',
                    'Somos especialistas exclusivos en WordPress con más de 15 años de experiencia.'
                )],
                'summary' => 'Este sitio no está construido con WordPress. Este módulo no aplica.',
                'salesMessage' => $defaults['sales_wordpress'],
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
            'Versión de WordPress',
            $wpVersion,
            $wpVersion ? "WordPress $wpVersion" : 'No detectada',
            $versionScore,
            $wpVersion
                ? ($isCurrent ? 'WordPress está actualizado.' : "WordPress $wpVersion está desactualizado. La última versión es $latestVersion.")
                : 'No fue posible detectar la versión de WordPress.',
            $isCurrent ? '' : 'Actualizar WordPress a la última versión para corregir vulnerabilidades y mejorar rendimiento.',
            'Actualizamos WordPress semanalmente con testing previo de compatibilidad.'
        );

        // Tema
        $themeInfo = $this->detectTheme();
        $this->detectedThemeInfo = [
            'slug' => $themeInfo['slug'] ?? null,
            'name' => $themeInfo['name'],
            'version' => $themeInfo['version'],
            'childTheme' => $themeInfo['childTheme'],
        ];
        $themeName = $themeInfo['name'] ?: 'No detectado';
        $themeVersion = $themeInfo['version'] ?? null;
        $themeDisplay = $themeName . ($themeVersion ? " v$themeVersion" : '');

        $metrics[] = Scoring::createMetric(
            'wp_theme',
            'Tema de WordPress',
            $themeInfo['name'],
            $themeDisplay,
            $themeInfo['name'] ? 100 : 50,
            $themeInfo['name']
                ? "Tema activo: $themeDisplay."
                : 'No se pudo detectar el tema activo.',
            '',
            'Mantenemos tu tema actualizado y optimizado.',
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
        $metrics[] = Scoring::createMetric(
            'wp_plugins',
            'Plugins detectados',
            count($plugins),
            count($plugins) . ' plugins (' . $outdatedCount . ' desactualizados)',
            $pluginsScore,
            count($plugins) > 0
                ? 'Se detectaron ' . count($plugins) . ' plugins.' . ($outdatedCount > 0 ? " $outdatedCount necesitan actualización." : ' Todos parecen estar al día.')
                : 'No se detectaron plugins (pueden estar ocultos).',
            $outdatedCount > 0 ? 'Actualizar los plugins desactualizados para corregir vulnerabilidades.' : '',
            'Actualizamos todos tus plugins semanalmente con testing de compatibilidad.',
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
            'REST API de usuarios',
            $restApiExposed,
            $restApiExposed ? 'Expuesta - usuarios visibles' : 'Protegida',
            $restScore,
            $restApiExposed
                ? 'La REST API expone los nombres de usuario del sitio. Esto facilita ataques de fuerza bruta.'
                : 'La REST API de usuarios está protegida o no accesible.',
            $restApiExposed ? 'Desactivar o restringir el acceso al endpoint /wp-json/wp/v2/users.' : '',
            'Protegemos la REST API y bloqueamos la enumeración de usuarios.'
        );

        // XML-RPC
        $xmlrpcActive = $this->checkXmlRpc();
        $xmlrpcScore = $xmlrpcActive ? 50 : 100;
        $metrics[] = Scoring::createMetric(
            'xmlrpc_active',
            'XML-RPC',
            $xmlrpcActive,
            $xmlrpcActive ? 'Activo' : 'Desactivado o no accesible',
            $xmlrpcScore,
            $xmlrpcActive
                ? 'XML-RPC está activo. Puede ser usado para ataques de fuerza bruta y DDoS amplificado.'
                : 'XML-RPC no está accesible.',
            $xmlrpcActive ? 'Desactivar XML-RPC si no se usa para aplicaciones externas.' : '',
            'Desactivamos XML-RPC y protegemos contra ataques de fuerza bruta.'
        );

        // Debug mode
        $debugMode = $this->checkDebugMode();
        $debugScore = $debugMode ? 0 : 100;
        $metrics[] = Scoring::createMetric(
            'debug_mode',
            'Modo debug',
            $debugMode,
            $debugMode ? 'Errores PHP visibles' : 'Desactivado',
            $debugScore,
            $debugMode
                ? 'Se detectaron errores PHP visibles en la página. Esto expone información del servidor.'
                : 'No se detectaron errores PHP visibles.',
            $debugMode ? 'Desactivar WP_DEBUG en producción y ocultar mensajes de error.' : '',
            'Configuramos el modo debug correctamente y ocultamos errores en producción.'
        );

        // Archivos sensibles
        $sensitiveFiles = $this->checkSensitiveFiles();
        $sensitiveScore = Scoring::clamp(100 - (count($sensitiveFiles) * 30));
        $metrics[] = Scoring::createMetric(
            'sensitive_files',
            'Archivos sensibles expuestos',
            count($sensitiveFiles),
            count($sensitiveFiles) > 0 ? count($sensitiveFiles) . ' archivos expuestos' : 'Ninguno detectado',
            $sensitiveScore,
            count($sensitiveFiles) > 0
                ? 'Se encontraron archivos sensibles accesibles públicamente: ' . implode(', ', $sensitiveFiles)
                : 'No se detectaron archivos sensibles expuestos.',
            count($sensitiveFiles) > 0 ? 'Eliminar o proteger inmediatamente los archivos sensibles expuestos.' : '',
            'Protegemos todos los archivos sensibles y configuramos reglas de acceso.',
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
     * Detecta plugins a partir de rutas en el HTML
     */
    private function detectPlugins(): array {
        $plugins = [];
        preg_match_all('#/wp-content/plugins/([a-z0-9_-]+)/#i', $this->html, $matches);

        if (empty($matches[1])) {
            return $plugins;
        }

        $slugs = array_unique($matches[1]);
        $count = 0;

        foreach ($slugs as $slug) {
            if ($count >= 15) break;
            $count++;

            $plugin = [
                'slug' => $slug,
                'name' => str_replace('-', ' ', ucfirst($slug)),
                'detectedVersion' => null,
                'latestVersion' => null,
                'outdated' => false,
            ];

            // Consultar API de WordPress.org (incluye versión latest)
            $apiUrl = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=' . urlencode($slug);
            $api = Fetcher::get($apiUrl, 5, true, 0);
            if ($api['statusCode'] === 200) {
                $data = json_decode($api['body'], true);
                if ($data && isset($data['version'])) {
                    $plugin['name'] = $data['name'] ?? $plugin['name'];
                    $plugin['latestVersion'] = $data['version'];
                }
            }

            // Solo buscar readme.txt si la API dio versión (para comparar)
            if ($plugin['latestVersion']) {
                $readmeUrl = $this->url . '/wp-content/plugins/' . $slug . '/readme.txt';
                $readme = Fetcher::get($readmeUrl, 3, false, 0);
                if ($readme['statusCode'] === 200 && preg_match('/Stable tag:\s*([\d.]+)/i', $readme['body'], $m)) {
                    $plugin['detectedVersion'] = $m[1];
                    if (version_compare($m[1], $plugin['latestVersion'], '<')) {
                        $plugin['outdated'] = true;
                    }
                }
            }

            $plugins[] = $plugin;
            usleep(100000); // 100ms entre plugins
        }

        return $plugins;
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
     * Verifica si hay errores PHP visibles
     */
    private function checkDebugMode(): bool {
        $patterns = ['Fatal error:', 'Warning:', 'Notice:', 'WP_DEBUG', 'Parse error:', 'Deprecated:'];
        foreach ($patterns as $pattern) {
            if (str_contains($this->html, $pattern)) {
                return true;
            }
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
            'Enumeración de usuarios',
            $exposed,
            $exposed ? "Expuesto" . ($username ? " ($username)" : '') : 'Protegido',
            $score,
            $exposed
                ? 'La enumeración de usuarios está activa. Se detectó el username "' . ($username ?? '?') . '" vía /?author=1. Los atacantes pueden usar estos nombres de usuario para ataques de fuerza bruta.'
                : 'La enumeración de usuarios vía /?author=1 está protegida o deshabilitada.',
            $exposed ? 'Bloquear la enumeración de usuarios con un plugin de seguridad o regla en .htaccess.' : '',
            'Bloqueamos la enumeración de usuarios y protegemos contra ataques de fuerza bruta.'
        );
    }

    /**
     * Verifica archivos sensibles accesibles
     */
    private function checkSensitiveFiles(): array {
        $found = [];
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

        foreach ($filesToCheck as $file) {
            $response = Fetcher::head($this->url . $file, 3);
            if ($response['statusCode'] === 200) {
                $found[] = $file;
            }
        }

        return $found;
    }

    /**
     * Construye el resultado del módulo
     */
    private function buildModuleResult(array $metrics): array {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'wordpress',
            'name' => 'WordPress',
            'icon' => 'blocks',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_wordpress'],
            'metrics' => $metrics,
            'summary' => $this->isWordPress
                ? "Tu instalación de WordPress tiene una puntuación de $score/100."
                : 'Este sitio no parece estar construido con WordPress.',
            'salesMessage' => $defaults['sales_wordpress'],
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
