<?php
/**
 * Checks del snapshot sobre entorno servidor/PHP/WP: versiones, límites,
 * debug, auto-updates, caches.
 *
 * Sub-checker de WpSnapshotAnalyzer. Lee la estructura real del plugin
 * wp-snapshot (sections.environment.data, sections.performance.data,
 * sections.security.data.checks).
 */

class WpSnapshotEnvironmentChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    /**
     * Acceso al security.checks precalculado por el plugin. Cada check tiene
     * { label, value, status: good|warning|critical|info, note }.
     */
    private function secCheck(string $key): ?array {
        $checks = $this->getSection('security')['checks'] ?? [];
        return $checks[$key] ?? null;
    }

    public function analyzePhp(): array {
        $env = $this->getSection('environment');
        $phpVersion = $env['php_version'] ?? '';
        $memLimit = $env['php_memory_limit'] ?? '';
        $maxExec = (int) ($env['php_max_execution'] ?? 0);
        $wpMemory = $env['wp_memory_limit'] ?? '';
        $exts = $env['php_extensions'] ?? [];

        $issues = [];
        $score = 100;

        if ($phpVersion === '') {
            $score -= 20;
        } elseif (version_compare($phpVersion, '8.0', '<')) {
            $issues[] = "PHP $phpVersion es obsoleto (EOL)";
            $score -= 40;
        } elseif (version_compare($phpVersion, '8.1', '<')) {
            $issues[] = "PHP $phpVersion — actualizar a 8.2+ recomendado";
            $score -= 10;
        }

        $memBytes = $this->parseSize($memLimit);
        if ($memBytes > 0 && $memBytes < 256 * 1024 * 1024) {
            $issues[] = "memory_limit bajo ($memLimit) — WP recomienda 256M+";
            $score -= 15;
        }

        $wpMemBytes = $this->parseSize($wpMemory);
        if ($wpMemBytes > 0 && $wpMemBytes < 64 * 1024 * 1024) {
            $issues[] = "WP_MEMORY_LIMIT bajo ($wpMemory) — puede quedarse corto en plugins pesados";
            $score -= 10;
        }

        if ($maxExec > 0 && $maxExec < 60) {
            $issues[] = "max_execution_time de {$maxExec}s puede fallar en imports/backups";
            $score -= 5;
        }

        $requiredExts = ['curl', 'gd', 'mbstring', 'openssl', 'xml', 'zip', 'intl', 'imagick', 'fileinfo'];
        $missing = array_values(array_filter($requiredExts, fn($e) => isset($exts[$e]) && !$exts[$e]));
        if (!empty($missing)) {
            $issues[] = 'Extensiones faltantes: ' . implode(', ', $missing);
            $score -= count($missing) * 4;
        }

        return Scoring::createMetric(
            'env_php', 'Configuración PHP',
            $phpVersion,
            $phpVersion ? "PHP $phpVersion · memory $memLimit · exec {$maxExec}s" : 'No detectado',
            Scoring::clamp($score),
            empty($issues)
                ? "PHP $phpVersion con memory_limit $memLimit y {$maxExec}s de ejecución. Configuración correcta."
                : "Problemas detectados: " . implode('; ', $issues) . '.',
            !empty($issues) ? 'Actualizar PHP a 8.2+, subir memory_limit a 256M, max_execution_time a 120s, e instalar extensiones faltantes.' : '',
            'Optimizamos PHP/servidor para máximo rendimiento y compatibilidad.',
            ['phpVersion' => $phpVersion, 'memoryLimit' => $memLimit, 'wpMemoryLimit' => $wpMemory, 'maxExecution' => $maxExec, 'extensions' => $exts, 'missingExtensions' => $missing]
        );
    }

    public function analyzeDatabase(): ?array {
        $env = $this->getSection('environment');
        $dbVersion = $env['db_version'] ?? '';
        $dbType = $env['db_type'] ?? '';
        if ($dbVersion === '') return null;

        // CUIDADO: (float) '10.11' = 10.11 y (float) '10.3' = 10.3, así que
        // 10.11 < 10.3 como float es TRUE. Usamos version_compare para evitar
        // ese false positive (MariaDB 10.11 es más nueva que 10.3).
        $cleanVer = preg_replace('/[^0-9.]/', '', $dbVersion);
        $isMaria = stripos($dbType, 'maria') !== false || stripos($dbVersion, 'maria') !== false;

        $score = 100;
        $recommend = '';
        if ($isMaria) {
            if (version_compare($cleanVer, '10.3', '<'))       { $score = 30; $recommend = 'MariaDB < 10.3 está en EOL. Actualizar a 10.6+'; }
            elseif (version_compare($cleanVer, '10.6', '<'))   { $score = 70; $recommend = 'Actualizar MariaDB a 10.6+ para mejor rendimiento'; }
        } else {
            if (version_compare($cleanVer, '5.7', '<'))        { $score = 20; $recommend = 'MySQL < 5.7 es inseguro. Actualizar a 8.0+'; }
            elseif (version_compare($cleanVer, '8.0', '<'))    { $score = 60; $recommend = 'Actualizar MySQL a 8.0+'; }
        }

        return Scoring::createMetric(
            'env_database', 'Base de datos (motor)',
            $dbVersion,
            "$dbType $dbVersion",
            $score,
            $score >= 100
                ? "$dbType $dbVersion está actualizado."
                : "$dbType $dbVersion. $recommend",
            $recommend,
            'Gestionamos la actualización de motor de base de datos sin pérdida de datos.',
            ['dbType' => $dbType, 'dbVersion' => $dbVersion, 'isMariaDB' => $isMaria]
        );
    }

    public function analyzeWpVersion(): ?array {
        $env = $this->getSection('environment');
        $wpVersion = $env['wp_version'] ?? '';
        if ($wpVersion === '') return null;

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $latest = $defaults['latest_wp_version'] ?? '6.7.2';

        $cmp = version_compare($wpVersion, $latest);
        $score = $cmp >= 0 ? 100 : (version_compare($wpVersion, $latest, '<') && $this->majorDiff($wpVersion, $latest) >= 1 ? 50 : 80);

        return Scoring::createMetric(
            'wp_version_internal', 'Versión de WordPress',
            $wpVersion,
            $cmp >= 0 ? "$wpVersion (última)" : "$wpVersion → $latest disponible",
            $score,
            $cmp >= 0
                ? "WordPress $wpVersion está al día."
                : "WordPress $wpVersion. La versión más reciente conocida es $latest.",
            $cmp < 0 ? "Actualizar WordPress a $latest desde Escritorio → Actualizaciones. Hacer backup primero." : '',
            'Mantenemos WordPress core siempre actualizado, con backup y testing previo.',
            ['current' => $wpVersion, 'latest' => $latest]
        );
    }

    public function analyzeUploadLimits(): ?array {
        $env = $this->getSection('environment');
        $upload = $env['php_max_upload'] ?? '';
        $post = $env['php_post_max_size'] ?? '';
        if ($upload === '' && $post === '') return null;

        $uploadBytes = $this->parseSize($upload);
        $mb = $uploadBytes / (1024 * 1024);
        $score = $mb >= 64 ? 100 : ($mb >= 32 ? 85 : ($mb >= 8 ? 60 : 30));

        return Scoring::createMetric(
            'env_upload', 'Límites de subida',
            $upload,
            "upload $upload · post $post",
            $score,
            $mb < 32
                ? "Límites pequeños (upload=$upload, post=$post). Puede bloquear subidas de medios o imports."
                : "upload_max_filesize=$upload, post_max_size=$post. Adecuado.",
            $mb < 32 ? 'Subir upload_max_filesize y post_max_size a 64M en php.ini o .htaccess.' : '',
            'Configuramos los límites PHP apropiados para el contenido del sitio.',
            ['maxUpload' => $upload, 'postMax' => $post]
        );
    }

    public function analyzeWpDebug(): ?array {
        // Prefer security.checks si está (el plugin ya hizo el scoring)
        $check = $this->secCheck('wp_debug');
        $displayCheck = $this->secCheck('wp_debug_display');

        $env = $this->getSection('environment');
        $debug = $check['value'] ?? ($env['wp_debug'] ?? false);
        $display = $displayCheck['value'] ?? ($env['wp_debug_display'] ?? false);
        $log = $env['wp_debug_log'] ?? false;

        $isCritical = $debug && $display;
        $isWarning = $debug && !$display;

        return Scoring::createMetric(
            'wp_debug', 'WP_DEBUG en producción',
            $debug,
            $isCritical ? 'Debug + Display ACTIVOS (crítico)' : ($debug ? 'Debug ON · Display OFF' : 'Desactivado'),
            $isCritical ? 10 : ($isWarning ? 70 : 100),
            $isCritical
                ? 'WP_DEBUG y WP_DEBUG_DISPLAY están activos: los errores PHP se imprimen a los visitantes, exponiendo paths, versiones y posibles payloads.'
                : ($isWarning
                    ? 'WP_DEBUG activo pero DISPLAY desactivado. Aceptable si es para logging, pero lo ideal en producción es desactivar ambos.'
                    : 'WP_DEBUG desactivado. Correcto para producción.'),
            $isCritical
                ? 'En wp-config.php: define("WP_DEBUG", false); — o como mínimo define("WP_DEBUG_DISPLAY", false);'
                : ($isWarning ? 'Si no necesitas logs, define("WP_DEBUG", false); en wp-config.php.' : ''),
            'Configuramos WP_DEBUG correctamente: logs internos sin exponer errores a visitantes.',
            ['debug' => $debug, 'display' => $display, 'log' => $log]
        );
    }

    public function analyzeFileEditing(): ?array {
        $check = $this->secCheck('file_editing');
        $disallow = $check['value'] ?? false;

        return Scoring::createMetric(
            'file_editing', 'Editor de archivos (DISALLOW_FILE_EDIT)',
            $disallow,
            $disallow ? 'Bloqueado' : 'Habilitado',
            $disallow ? 100 : 60,
            $disallow
                ? 'DISALLOW_FILE_EDIT está activo — el editor de temas/plugins desde admin está bloqueado. Buena práctica.'
                : 'Editor de archivos activo. Si un atacante obtiene acceso al admin, puede inyectar código en temas/plugins.',
            !$disallow ? 'En wp-config.php: define("DISALLOW_FILE_EDIT", true);' : '',
            'Bloqueamos el editor de archivos y otros vectores de inyección de código.',
            ['disallow_file_edit' => $disallow]
        );
    }

    public function analyzeXmlRpc(): ?array {
        $check = $this->secCheck('xmlrpc');
        if ($check === null) return null;

        $xmlEnabled = $check['value'] ?? false;
        return Scoring::createMetric(
            'xmlrpc_status', 'XML-RPC',
            $xmlEnabled,
            $xmlEnabled ? 'Activo' : 'Desactivado',
            $xmlEnabled ? 50 : 100,
            $xmlEnabled
                ? 'XML-RPC está habilitado. Es superficie de ataque común para fuerza bruta y DDoS (pingback).'
                : 'XML-RPC desactivado. Correcto.',
            $xmlEnabled ? 'Desactivar XML-RPC si no lo usas (Jetpack/app móvil WP): add_filter("xmlrpc_enabled", "__return_false");' : '',
            'Desactivamos XML-RPC o lo protegemos contra fuerza bruta y pingback.',
            ['enabled' => $xmlEnabled]
        );
    }

    public function analyzeAutoUpdates(): ?array {
        $check = $this->secCheck('auto_updates_core');
        if ($check === null) return null;

        $enabled = $check['value'] ?? false;
        return Scoring::createMetric(
            'core_auto_updates', 'Auto-updates de WordPress core',
            $enabled,
            $enabled ? 'Habilitados' : 'Manuales',
            $enabled ? 100 : 70,
            $enabled
                ? 'Las actualizaciones automáticas del core están habilitadas. El sitio recibe parches de seguridad menores.'
                : 'Auto-updates del core deshabilitadas. El sitio no recibe parches de seguridad automáticos — requiere actualización manual.',
            !$enabled ? 'Habilitar actualizaciones menores automáticas o establecer un calendario de updates manual (semanal).' : '',
            'Configuramos actualizaciones automáticas seguras con monitoreo de compatibilidad.',
            ['enabled' => $enabled]
        );
    }

    public function analyzeDbPrefix(): ?array {
        $check = $this->secCheck('db_prefix');
        if ($check === null) return null;

        $isCustom = $check['value'] ?? false;
        $note = $check['note'] ?? '';

        return Scoring::createMetric(
            'db_prefix_status', 'Prefijo de base de datos',
            $isCustom,
            $isCustom ? 'Personalizado' : 'Por defecto (wp_)',
            $isCustom ? 100 : 70,
            $isCustom
                ? "Prefijo de tablas personalizado. $note Dificulta ataques SQL automatizados."
                : 'El prefijo de tablas es el default "wp_". Ataques SQL automatizados apuntan a este prefijo.',
            !$isCustom ? 'Considerar migrar a un prefijo custom (ej. "xyz_") — requiere actualizar wp-config.php y renombrar tablas/options.' : '',
            'Cambiamos el prefijo de DB y blindamos contra SQL injection automatizado.',
            ['isCustom' => $isCustom]
        );
    }

    public function analyzeSsl(): ?array {
        $check = $this->secCheck('ssl');
        if ($check === null) return null;

        $active = $check['value'] ?? false;
        return Scoring::createMetric(
            'ssl_internal', 'HTTPS (site_url)',
            $active,
            $active ? 'Activo' : 'NO activo',
            $active ? 100 : 20,
            $active
                ? 'El sitio usa HTTPS como URL principal. Correcto.'
                : 'El sitio_url usa HTTP. Los formularios y el login se transmiten sin cifrar.',
            !$active ? 'Migrar a HTTPS completo. Actualizar site_url/home_url y configurar redirect 301 desde HTTP.' : '',
            'Instalamos SSL, forzamos HTTPS y eliminamos mixed content.',
            ['active' => $active]
        );
    }

    public function analyzeObjectCache(): array {
        $perf = $this->getSection('performance');
        $active = $perf['object_cache_active'] ?? false;
        $type = $perf['object_cache_type'] ?? 'None (default transient/DB cache)';
        $dropin = $perf['object_cache_dropin'] ?? false;

        $score = $active ? 100 : 40;

        return Scoring::createMetric(
            'object_cache', 'Cache de objetos persistente',
            $active,
            $active ? "Activo: $type" : 'No configurado',
            $score,
            $active
                ? "Cache de objetos activo ($type). Las consultas repetidas a la DB se sirven desde memoria — mejora significativa de rendimiento."
                : "Sin cache de objetos persistente. Cada request regenera consultas a la DB. En sitios con tráfico, puede ser la mayor causa de lentitud.",
            !$active ? 'Instalar Redis o Memcached y activar el drop-in en wp-content/object-cache.php. Plugins: Redis Object Cache o W3 Total Cache.' : '',
            'Instalamos Redis/Memcached y configuramos object cache para reducir carga en DB.',
            ['active' => $active, 'type' => $type, 'dropin' => $dropin]
        );
    }

    public function analyzePageCache(): array {
        $perf = $this->getSection('performance');
        $pageCache = $perf['page_cache_likely'] ?? false;

        return Scoring::createMetric(
            'page_cache', 'Cache de página',
            $pageCache,
            $pageCache ? 'Detectado' : 'No detectado',
            $pageCache ? 100 : 50,
            $pageCache
                ? 'Se detectó cache de página (plugin tipo WP Rocket / LiteSpeed Cache / W3 Total Cache).'
                : 'No se detectó cache de página. Cada visita ejecuta PHP + DB queries — lento y costoso.',
            !$pageCache ? 'Instalar un plugin de cache (WP Rocket, LiteSpeed Cache, W3 Total Cache) o usar cache del servidor (Nginx FastCGI, Varnish).' : '',
            'Configuramos el cache de página con un plugin o a nivel de servidor para latencia <100ms.',
            ['detected' => $pageCache]
        );
    }

    public function analyzeOpcache(): array {
        $perf = $this->getSection('performance');
        $env = $this->getSection('environment');
        $enabled = $perf['opcache_enabled'] ?? ($env['opcache_enabled'] ?? false);

        return Scoring::createMetric(
            'opcache', 'OPcache de PHP',
            $enabled,
            $enabled ? 'Activo' : 'Inactivo',
            $enabled ? 100 : 40,
            $enabled
                ? 'OPcache activo — PHP cachea los scripts compilados. Ganancia típica: 30-60% de rendimiento PHP.'
                : 'OPcache desactivado. PHP recompila cada script en cada petición. En PHP 7.0+ es gratis y transparente — debería estar SIEMPRE activo.',
            !$enabled ? 'Habilitar OPcache en php.ini: opcache.enable=1, opcache.memory_consumption=256, opcache.max_accelerated_files=10000.' : '',
            'Habilitamos y tunamos OPcache para máximo rendimiento PHP.',
            ['enabled' => $enabled]
        );
    }

    public function analyzeImageEditor(): array {
        $perf = $this->getSection('performance');
        $editor = $perf['image_editor'] ?? 'default';

        $isImagick = stripos($editor, 'imagick') !== false;
        $score = $isImagick ? 100 : 70;

        return Scoring::createMetric(
            'image_editor', 'Editor de imágenes WP',
            $editor,
            $editor,
            $score,
            $isImagick
                ? "WordPress usa Imagick ($editor) para procesar imágenes. Mejor calidad y soporte WebP que GD."
                : "WordPress usa $editor (normalmente GD). Imagick produce imágenes más pequeñas y soporta formatos modernos (WebP, AVIF) mejor.",
            !$isImagick ? 'Instalar la extensión PHP Imagick y/o reinstalar el paquete ImageMagick en el servidor.' : '',
            'Configuramos Imagick + WebP para imágenes optimizadas por defecto.',
            ['editor' => $editor]
        );
    }

    public function analyzePermalinks(): ?array {
        $perf = $this->getSection('performance');
        $permalink = $perf['permalink_structure'] ?? '';
        if ($permalink === '') return null;

        // /?p=123 o vacío = default (malo para SEO)
        $isDefault = $permalink === '' || str_starts_with($permalink, '/?');

        return Scoring::createMetric(
            'permalinks', 'Estructura de permalinks',
            $permalink,
            $isDefault ? 'Default (?p=123)' : $permalink,
            $isDefault ? 40 : 100,
            $isDefault
                ? 'Permalinks por defecto (/?p=123). Terrible para SEO y usabilidad.'
                : "Estructura personalizada: $permalink. Bien para SEO.",
            $isDefault ? 'Cambiar en Ajustes → Permalinks a "Nombre de entrada" (/%postname%/).' : '',
            'Configuramos URLs amigables para SEO.',
            ['structure' => $permalink]
        );
    }

    private function majorDiff(string $v1, string $v2): int {
        $p1 = (int) explode('.', $v1)[0];
        $p2 = (int) explode('.', $v2)[0];
        return abs($p1 - $p2);
    }

    private function parseSize(string $val): int {
        if (empty($val)) return 0;
        $val = trim($val);
        if (preg_match('/^(\d+)\s*(\w+)?/', $val, $m)) {
            $num = (int) $m[1];
            $unit = strtolower($m[2] ?? '');
            switch ($unit) {
                case 'g': case 'gb': return $num * 1024 * 1024 * 1024;
                case 'm': case 'mb': return $num * 1024 * 1024;
                case 'k': case 'kb': return $num * 1024;
                default: return $num;
            }
        }
        return 0;
    }
}
