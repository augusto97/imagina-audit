<?php
/**
 * Checks del snapshot sobre entorno servidor/PHP/WP: versiones, límites,
 * debug, auto-updates, caches.
 *
 * Sub-checker de WpSnapshotAnalyzer.
 */

class WpSnapshotEnvironmentChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeEnvironment(): array {
        $env = $this->getSection('environment');
        $issues = [];
        $score = 100;

        $phpVersion = $env['php_version'] ?? '';
        if ($phpVersion) {
            $major = (float) $phpVersion;
            if ($major < 8.0) { $issues[] = "PHP $phpVersion (obsoleta)"; $score -= 30; }
            elseif ($major < 8.1) { $issues[] = "PHP $phpVersion (actualizar recomendado)"; $score -= 10; }
        }

        $memLimit = $env['php_memory_limit'] ?? '';
        if ($memLimit) {
            $bytes = $this->parseSize($memLimit);
            if ($bytes > 0 && $bytes < 256 * 1024 * 1024) {
                $issues[] = "Memory limit bajo: $memLimit";
                $score -= 10;
            }
        }

        $reqExts = ['curl', 'gd', 'mbstring', 'openssl', 'xml', 'zip', 'intl', 'imagick'];
        $exts = $env['php_extensions'] ?? [];
        $missing = [];
        foreach ($reqExts as $e) {
            if (isset($exts[$e]) && !$exts[$e]) $missing[] = $e;
        }
        if (!empty($missing)) {
            $issues[] = 'Extensiones faltantes: ' . implode(', ', $missing);
            $score -= count($missing) * 3;
        }

        return Scoring::createMetric(
            'env_config', 'Configuración PHP',
            $phpVersion, "PHP $phpVersion · " . ($memLimit ?: '?'),
            Scoring::clamp($score),
            empty($issues)
                ? "PHP $phpVersion, memory " . ($memLimit ?: '?') . ". Configuración adecuada."
                : "Problemas: " . implode('. ', $issues) . '.',
            !empty($issues) ? 'Actualizar PHP a versión 8.1+ y aumentar memory_limit a 256M o más.' : '',
            'Optimizamos la configuración PHP y el servidor para máximo rendimiento.',
            ['phpVersion' => $phpVersion, 'memoryLimit' => $memLimit, 'extensions' => $exts, 'missingExtensions' => $missing]
        );
    }

    public function analyzeMysqlVersion(): ?array {
        $env = $this->getSection('environment');
        $ver = $env['mysql_version'] ?? ($env['db_version'] ?? '');
        if (empty($ver)) return null;

        $num = (float) preg_replace('/[^0-9.]/', '', $ver);
        $isMariaDB = stripos($ver, 'mariadb') !== false || stripos($ver, 'maria') !== false;

        $score = 100;
        if ($isMariaDB) {
            if ($num < 10.3) $score = 40;
            elseif ($num < 10.5) $score = 70;
        } else {
            if ($num < 5.7) $score = 30;
            elseif ($num < 8.0) $score = 70;
        }

        return Scoring::createMetric(
            'mysql_version', 'Versión MySQL/MariaDB',
            $ver, $ver,
            $score,
            $score >= 100 ? "Versión $ver actualizada." : "Versión $ver. Se recomienda actualizar para mejor rendimiento y seguridad.",
            $score < 100 ? ($isMariaDB ? 'Actualizar MariaDB a 10.5+ o superior.' : 'Actualizar MySQL a 8.0+ o superior.') : '',
            'Gestionamos la actualización de la base de datos sin pérdida de información.',
            ['version' => $ver, 'isMariaDB' => $isMariaDB]
        );
    }

    public function analyzeUploadLimits(): ?array {
        $env = $this->getSection('environment');
        $maxUpload = $env['max_upload_size'] ?? ($env['upload_max_filesize'] ?? '');
        $postMax = $env['php_post_max_size'] ?? ($env['post_max_size'] ?? '');
        if (empty($maxUpload) && empty($postMax)) return null;

        $uploadBytes = $this->parseSize($maxUpload);
        $mb = $uploadBytes / (1024 * 1024);
        $score = $mb >= 64 ? 100 : ($mb >= 32 ? 90 : ($mb >= 8 ? 70 : 40));

        return Scoring::createMetric(
            'upload_limits', 'Límites de subida',
            $maxUpload, "Upload: $maxUpload · Post: $postMax",
            $score,
            $mb < 8
                ? "Límite de subida muy bajo ($maxUpload). Los usuarios no podrán subir imágenes grandes o documentos."
                : "Límite de subida: $maxUpload, post máximo: $postMax. " . ($mb >= 32 ? 'Adecuado.' : 'Considerar aumentar.'),
            $mb < 32 ? 'Aumentar upload_max_filesize y post_max_size en php.ini o .htaccess.' : '',
            'Configuramos los límites de subida adecuados según las necesidades del sitio.',
            ['maxUpload' => $maxUpload, 'postMax' => $postMax]
        );
    }

    public function analyzeExecutionLimits(): ?array {
        $env = $this->getSection('environment');
        $maxExec = $env['max_execution_time'] ?? ($env['php_max_execution_time'] ?? null);
        if ($maxExec === null) return null;

        $sec = (int) $maxExec;
        $score = $sec >= 120 ? 100 : ($sec >= 60 ? 80 : ($sec >= 30 ? 60 : 30));

        return Scoring::createMetric(
            'execution_limits', 'Tiempo de ejecución PHP',
            $sec, "{$sec}s",
            $score,
            $sec < 30
                ? "Tiempo de ejecución PHP muy corto ({$sec}s). Operaciones como importaciones, backups o actualizaciones pueden fallar."
                : "Tiempo de ejecución: {$sec}s. " . ($sec >= 60 ? 'Adecuado.' : 'Podría ser insuficiente para operaciones pesadas.'),
            $sec < 60 ? 'Aumentar max_execution_time a 120s o más en php.ini.' : '',
            'Ajustamos los límites PHP para operaciones de mantenimiento sin interrupciones.',
            ['maxExecutionTime' => $sec]
        );
    }

    public function analyzeSiteUrlMismatch(): ?array {
        $env = $this->getSection('environment');
        $siteUrl = $env['site_url'] ?? ($this->snapshot['site_url'] ?? '');
        $homeUrl = $env['home_url'] ?? ($env['home'] ?? '');
        if (empty($siteUrl) || empty($homeUrl)) return null;

        $match = rtrim($siteUrl, '/') === rtrim($homeUrl, '/');

        return Scoring::createMetric(
            'site_url_match', 'Coincidencia Site URL / Home URL',
            $match, $match ? 'Coinciden' : 'No coinciden',
            $match ? 100 : 50,
            $match
                ? 'site_url y home_url coinciden correctamente.'
                : "site_url ($siteUrl) y home_url ($homeUrl) no coinciden. Esto puede causar problemas de redirección y SEO.",
            !$match ? 'Verificar que ambas URLs estén correctas en Ajustes → General o directamente en la DB.' : '',
            'Corregimos inconsistencias de URL que afectan SEO y funcionalidad.',
            ['siteUrl' => $siteUrl, 'homeUrl' => $homeUrl]
        );
    }

    public function analyzeMultisite(): ?array {
        $env = $this->getSection('environment');
        $isMultisite = $env['is_multisite'] ?? ($env['multisite'] ?? false);
        if (!$isMultisite) return null;

        return Scoring::createMetric(
            'multisite', 'WordPress Multisite',
            true, 'Multisite activo',
            null,
            'Este sitio es una instalación WordPress Multisite. Requiere mantenimiento especializado y cuidado extra en actualizaciones.',
            'Verificar que todos los subsitios estén actualizados y que la configuración de red sea segura.',
            'Gestionamos redes WordPress Multisite con actualizaciones coordinadas.',
            ['isMultisite' => true]
        );
    }

    public function analyzeWpDebug(): array {
        $env = $this->getSection('environment');
        $debug = $env['wp_debug'] ?? false;
        $debugDisplay = $env['wp_debug_display'] ?? false;
        $debugLog = $env['wp_debug_log'] ?? false;

        $bad = $debug && $debugDisplay;
        $warn = $debug && !$debugDisplay;

        return Scoring::createMetric(
            'wp_debug_config', 'Configuración WP_DEBUG',
            $debug, $bad ? 'Debug + Display activos' : ($debug ? 'Debug activo (log)' : 'Desactivado'),
            $bad ? 10 : ($warn ? 70 : 100),
            $bad
                ? 'WP_DEBUG y WP_DEBUG_DISPLAY están activos en producción. Los errores PHP se muestran a los visitantes, exponiendo información sensible.'
                : ($warn
                    ? 'WP_DEBUG activo pero DISPLAY desactivado. Aceptable para desarrollo pero no recomendado en producción.'
                    : 'WP_DEBUG desactivado. Correcto para producción.'),
            $bad ? 'Cambiar en wp-config.php: define("WP_DEBUG", false); O mantener activo pero con WP_DEBUG_DISPLAY=false.' : '',
            'Configuramos WP_DEBUG correctamente: activo para logs pero sin mostrar errores a usuarios.',
            ['debug' => $debug, 'display' => $debugDisplay, 'log' => $debugLog]
        );
    }

    public function analyzeFileEditing(): array {
        $env = $this->getSection('environment');
        $disallowEdit = $env['disallow_file_edit'] ?? false;
        $disallowMods = $env['disallow_file_mods'] ?? false;

        return Scoring::createMetric(
            'file_editing', 'Edición de archivos deshabilitada',
            $disallowEdit, $disallowEdit ? 'Protegido' : 'Editor activo',
            $disallowEdit ? 100 : 60,
            $disallowEdit
                ? 'DISALLOW_FILE_EDIT está definido. El editor de temas/plugins está bloqueado, buena práctica de seguridad.'
                : 'El editor de archivos desde el admin está activo. Si un atacante obtiene acceso al admin, puede inyectar código en plugins/temas.',
            !$disallowEdit ? 'Agregar en wp-config.php: define("DISALLOW_FILE_EDIT", true);' : '',
            'Bloqueamos el editor de archivos para proteger contra inyección de código.',
            ['disallowFileEdit' => $disallowEdit, 'disallowFileMods' => $disallowMods]
        );
    }

    public function analyzeAutoUpdates(): ?array {
        $env = $this->getSection('environment');
        $autoUpdaterDisabled = $env['automatic_updater_disabled'] ?? ($env['auto_update_disabled'] ?? null);
        if ($autoUpdaterDisabled === null) return null;

        return Scoring::createMetric(
            'auto_updates', 'Actualizaciones automáticas',
            !$autoUpdaterDisabled, $autoUpdaterDisabled ? 'Deshabilitadas' : 'Habilitadas',
            $autoUpdaterDisabled ? 50 : 100,
            $autoUpdaterDisabled
                ? 'Las actualizaciones automáticas están deshabilitadas. El sitio no recibirá parches de seguridad automáticos de WordPress.'
                : 'Las actualizaciones automáticas están habilitadas. El sitio recibe parches de seguridad menores automáticamente.',
            $autoUpdaterDisabled ? 'Habilitar al menos las actualizaciones menores de seguridad o implementar un proceso de actualización manual regular.' : '',
            'Configuramos actualizaciones automáticas seguras con monitoreo de compatibilidad.',
            ['disabled' => $autoUpdaterDisabled]
        );
    }

    public function analyzeObjectCache(): ?array {
        $perf = $this->getSection('performance');
        if (empty($perf)) return null;

        $active = $perf['object_cache_active'] ?? false;
        $type = $perf['object_cache_type'] ?? 'none';

        return Scoring::createMetric(
            'object_cache', 'Cache de objetos',
            $active, $active ? $type : 'No configurado',
            $active ? 100 : 50,
            $active
                ? "Cache de objetos activo ($type). Las consultas repetidas a DB se sirven desde cache, mejorando el rendimiento."
                : 'No hay cache de objetos persistente. Cada request genera consultas DB repetidas que podrían cachearse.',
            !$active ? 'Configurar Redis o Memcached como cache de objetos. Mejora dramáticamente el rendimiento.' : '',
            'Instalamos y configuramos Redis/Memcached para cache de objetos persistente.',
            ['active' => $active, 'type' => $type]
        );
    }

    public function analyzeOpcache(): ?array {
        $perf = $this->getSection('performance');
        $env = $this->getSection('environment');
        $opcache = $perf['opcache_active'] ?? ($env['opcache_enabled'] ?? ($env['opcache'] ?? null));
        if ($opcache === null) return null;

        return Scoring::createMetric(
            'opcache', 'OPcache PHP',
            $opcache, $opcache ? 'Activo' : 'Inactivo',
            $opcache ? 100 : 50,
            $opcache
                ? 'OPcache está activo. PHP no necesita recompilar los scripts en cada petición, mejorando significativamente el rendimiento.'
                : 'OPcache no está activo. Cada petición PHP requiere compilar los scripts desde cero, lo que aumenta el tiempo de respuesta.',
            !$opcache ? 'Habilitar OPcache en php.ini: opcache.enable=1, opcache.memory_consumption=128.' : '',
            'Habilitamos y optimizamos OPcache para máximo rendimiento PHP.',
            ['active' => $opcache]
        );
    }

    /**
     * Convierte una cadena tipo "256M" a bytes.
     */
    private function parseSize(string $val): int {
        if (empty($val)) return 0;
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        switch ($last) {
            case 'g': $num *= 1024;
            case 'm': $num *= 1024;
            case 'k': $num *= 1024;
        }
        return $num;
    }
}
