<?php
/**
 * Analiza el JSON de wp-snapshot (plugin mrabro/wp-snapshot) y genera
 * un módulo de "Análisis Interno" con datos que no se pueden obtener
 * desde fuera del sitio.
 */

class WpSnapshotAnalyzer {
    private array $snapshot;
    private array $sections;

    public function __construct(array $snapshot) {
        $this->snapshot = $snapshot;
        $this->sections = $snapshot['sections'] ?? [];
    }

    public function analyze(): array {
        $metrics = [];

        $checks = [
            $this->analyzeEnvironment(),
            $this->analyzeMysqlVersion(),
            $this->analyzeUploadLimits(),
            $this->analyzeExecutionLimits(),
            $this->analyzeSiteUrlMismatch(),
            $this->analyzeMultisite(),
            $this->analyzeWpDebug(),
            $this->analyzeFileEditing(),
            $this->analyzeAutoUpdates(),
            $this->analyzePlugins(),
            $this->analyzeInactivePlugins(),
            $this->analyzePluginOverload(),
            $this->analyzeAbandonedPlugins(),
            $this->analyzeThemes(),
            $this->analyzeInactiveThemes(),
            $this->analyzeDatabase(),
            $this->analyzeDbEngine(),
            $this->analyzeAutoload(),
            $this->analyzeRevisions(),
            $this->analyzeTransients(),
            $this->analyzeOrphanedMeta(),
            $this->analyzeSpamComments(),
            $this->analyzeTrashedPosts(),
            $this->analyzeCron(),
            $this->analyzeUsers(),
            $this->analyzeWeakAdminUsers(),
            $this->analyzeObjectCache(),
            $this->analyzeOpcache(),
            $this->analyzeMediaSize(),
        ];

        $metrics = array_values(array_filter($checks, fn($m) => $m !== null));

        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'wp_internal',
            'name' => 'Análisis Interno (WordPress)',
            'icon' => 'database',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => 0.10,
            'metrics' => $metrics,
            'summary' => "Análisis interno del sitio basado en el snapshot: $score/100.",
            'salesMessage' => 'Analizamos el estado interno de tu WordPress y lo optimizamos a nivel de plugins, base de datos, configuración y rendimiento.',
        ];
    }

    private function getSection(string $key): array {
        return $this->sections[$key]['data'] ?? [];
    }

    private function analyzeEnvironment(): array {
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

    private function analyzeWpDebug(): array {
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

    private function analyzeFileEditing(): array {
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

    private function analyzePlugins(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $total = $plugins['total_plugins'] ?? 0;
        $active = $plugins['active_count'] ?? 0;
        $updateAvailable = $plugins['update_available'] ?? 0;

        $list = $plugins['plugins'] ?? [];
        $outdated = array_values(array_filter($list, fn($p) => $p['has_update'] ?? false));

        $score = 100;
        if ($updateAvailable > 0) $score -= min(40, $updateAvailable * 8);

        $outdatedSummary = array_map(fn($p) => [
            'name' => $p['name'] ?? '?',
            'current' => $p['version'] ?? '?',
            'update' => $p['update_version'] ?? '?',
            'active' => $p['is_active'] ?? false,
        ], $outdated);

        return Scoring::createMetric(
            'plugins_updates', 'Plugins desactualizados',
            $updateAvailable, "$updateAvailable pendientes de $total instalados",
            Scoring::clamp($score),
            $updateAvailable === 0
                ? "Todos los $total plugins están actualizados."
                : "$updateAvailable de $total plugins tienen actualizaciones pendientes. Plugins desactualizados son la principal causa de sitios WordPress comprometidos.",
            $updateAvailable > 0 ? 'Actualizar todos los plugins con actualizaciones pendientes. Hacer backup previo.' : '',
            'Actualizamos todos los plugins semanalmente con testing de compatibilidad.',
            ['total' => $total, 'active' => $active, 'updateAvailable' => $updateAvailable, 'outdated' => $outdatedSummary]
        );
    }

    private function analyzeInactivePlugins(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $inactive = $plugins['inactive_count'] ?? 0;
        $list = $plugins['plugins'] ?? [];
        $inactiveList = array_values(array_filter($list, fn($p) => !($p['is_active'] ?? false)));

        $score = $inactive === 0 ? 100 : ($inactive <= 3 ? 80 : ($inactive <= 10 ? 50 : 30));

        return Scoring::createMetric(
            'inactive_plugins', 'Plugins inactivos',
            $inactive, $inactive === 0 ? 'Ninguno' : "$inactive plugins",
            $score,
            $inactive === 0
                ? 'No hay plugins inactivos instalados. Correcto.'
                : "Hay $inactive plugins inactivos instalados. Los plugins desactivados aún pueden ser vulnerables y ocupar espacio. Deberían eliminarse si no se usan.",
            $inactive > 0 ? 'Eliminar los plugins que no se usan. Si un plugin está desactivado permanentemente, desinstalarlo.' : '',
            'Limpiamos plugins inactivos y optimizamos el sitio eliminando código innecesario.',
            ['count' => $inactive, 'list' => array_map(fn($p) => ['name' => $p['name'] ?? '?', 'version' => $p['version'] ?? '?'], $inactiveList)]
        );
    }

    private function analyzeThemes(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $total = $themes['total_themes'] ?? 0;
        $active = $themes['active_theme'] ?? [];
        $activeName = $active['name'] ?? 'Desconocido';
        $updateAvailable = 0;
        // has_update field exists in active_theme
        if (!empty($active['has_update'])) $updateAvailable = 1;

        $score = $updateAvailable > 0 ? 60 : ($total > 3 ? 80 : 100);

        return Scoring::createMetric(
            'themes_status', 'Temas instalados',
            $total, "$total temas (activo: $activeName)",
            $score,
            "Hay $total temas instalados. Activo: $activeName" . ($updateAvailable > 0 ? '. Tiene actualización disponible.' : ''),
            $total > 3 ? 'Eliminar temas no usados. Mantener solo el activo y un default como fallback.' : ($updateAvailable > 0 ? 'Actualizar el tema activo.' : ''),
            'Mantenemos solo los temas necesarios y actualizados.',
            ['total' => $total, 'active' => $active]
        );
    }

    private function analyzeDatabase(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $totalSize = $db['total_db_size'] ?? 0;
        $totalRows = $db['total_rows'] ?? 0;
        $totalTables = $db['total_tables'] ?? 0;
        $humanSize = $db['total_db_size_human'] ?? '?';

        // Top 5 tables by size
        $tables = $db['tables'] ?? [];
        $topTables = array_slice($tables, 0, 5);
        $topTablesFmt = array_map(fn($t) => [
            'name' => $t['name'],
            'rows' => $t['rows'],
            'size' => $this->formatBytes($t['total_size']),
        ], $topTables);

        $gbSize = $totalSize / (1024 * 1024 * 1024);
        $score = $gbSize < 0.5 ? 100 : ($gbSize < 1 ? 80 : ($gbSize < 3 ? 60 : 30));

        return Scoring::createMetric(
            'db_size', 'Tamaño de la base de datos',
            $totalSize, $humanSize,
            $score,
            "La base de datos pesa $humanSize con $totalRows filas en $totalTables tablas. " . ($gbSize > 1 ? 'DB grande — considerar optimización.' : 'Tamaño razonable.'),
            $gbSize > 1 ? 'Limpiar revisiones, transients, meta huérfana. Considerar migrar datos históricos a archivos externos.' : '',
            'Optimizamos la base de datos eliminando datos innecesarios y creando índices.',
            ['totalSize' => $totalSize, 'humanSize' => $humanSize, 'totalRows' => $totalRows, 'totalTables' => $totalTables, 'topTables' => $topTablesFmt]
        );
    }

    private function analyzeAutoload(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $autoloadSize = $db['autoload_size'] ?? 0;
        $autoloadHuman = $db['autoload_size_human'] ?? '?';
        $autoloadedCount = $db['autoloaded_options'] ?? 0;
        $mb = $autoloadSize / (1024 * 1024);
        $score = $mb < 0.5 ? 100 : ($mb < 1 ? 80 : ($mb < 2 ? 50 : 20));

        return Scoring::createMetric(
            'autoload_size', 'Opciones autoload',
            $autoloadSize, "$autoloadHuman ($autoloadedCount opciones)",
            $score,
            $mb < 0.5
                ? "Autoload de $autoloadHuman con $autoloadedCount opciones. Dentro del rango saludable."
                : "Autoload de $autoloadHuman con $autoloadedCount opciones. Cada petición a WP carga estas opciones — un autoload grande ralentiza TODO el sitio.",
            $mb > 1 ? 'Identificar opciones autoload pesadas que no se necesiten en cada request y cambiar autoload=no. Usar plugins como "Autoload Options Monitor".' : '',
            'Optimizamos las opciones autoload reduciendo el peso de cada petición a WordPress.',
            ['size' => $autoloadSize, 'human' => $autoloadHuman, 'count' => $autoloadedCount]
        );
    }

    private function analyzeRevisions(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $revisions = $db['revisions_count'] ?? 0;
        $score = $revisions < 100 ? 100 : ($revisions < 500 ? 80 : ($revisions < 2000 ? 50 : 20));

        return Scoring::createMetric(
            'db_revisions', 'Revisiones en base de datos',
            $revisions, "$revisions revisiones",
            $score,
            $revisions < 100
                ? "$revisions revisiones. Cantidad normal."
                : "$revisions revisiones en la DB. Cada revisión ocupa espacio innecesario.",
            $revisions > 500 ? 'Limpiar revisiones antiguas y limitar con define("WP_POST_REVISIONS", 5) en wp-config.php.' : '',
            'Limpiamos revisiones antiguas y configuramos límites saludables.',
            ['count' => $revisions]
        );
    }

    private function analyzeTransients(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $transients = $db['transients_count'] ?? 0;
        $score = $transients < 500 ? 100 : ($transients < 2000 ? 80 : ($transients < 5000 ? 50 : 20));

        return Scoring::createMetric(
            'db_transients', 'Transients en base de datos',
            $transients, "$transients transients",
            $score,
            $transients < 500
                ? "$transients transients. Normal."
                : "$transients transients. Muchos transients (caches temporales) pueden quedarse huérfanos y acumularse.",
            $transients > 2000 ? 'Limpiar transients expirados. Considerar cache de objetos (Redis/Memcached) para reducirlos.' : '',
            'Configuramos cache de objetos y limpiamos transients huérfanos regularmente.',
            ['count' => $transients]
        );
    }

    private function analyzeOrphanedMeta(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $orphaned = $db['orphaned_postmeta'] ?? 0;
        if ($orphaned === 0) return null; // no mostrar si no hay
        $score = $orphaned < 100 ? 80 : ($orphaned < 1000 ? 50 : 20);

        return Scoring::createMetric(
            'orphaned_meta', 'Metadata huérfana',
            $orphaned, "$orphaned registros huérfanos",
            $score,
            "$orphaned registros en wp_postmeta referencian posts que ya no existen. Son datos basura acumulados.",
            'Limpiar metadata huérfana con un plugin de optimización DB o query manual.',
            'Limpiamos datos huérfanos acumulados en la base de datos.',
            ['count' => $orphaned]
        );
    }

    private function analyzeCron(): ?array {
        $cron = $this->getSection('cron');
        if (empty($cron)) return null;

        $total = $cron['total_events'] ?? 0;
        $overdue = $cron['overdue_count'] ?? 0;
        $wpCronDisabled = ($this->getSection('environment')['wp_cron_disabled'] ?? false);

        $score = $overdue === 0 ? 100 : ($overdue < 5 ? 70 : 30);

        return Scoring::createMetric(
            'cron_status', 'Tareas programadas (cron)',
            $overdue, $overdue === 0 ? "$total tareas OK" : "$overdue atrasadas de $total",
            $score,
            $overdue === 0
                ? "$total cron jobs registrados, todos ejecutándose a tiempo." . ($wpCronDisabled ? ' WP_CRON está deshabilitado (debe tener cron real del servidor).' : '')
                : "Hay $overdue cron jobs atrasados de $total totales. Tareas automáticas no se están ejecutando correctamente.",
            $overdue > 0 ? 'Verificar que WP_CRON funcione. En sitios grandes, configurar un cron real del servidor y desactivar WP_CRON.' : '',
            'Configuramos cron real del servidor y monitoreamos tareas programadas.',
            ['total' => $total, 'overdue' => $overdue, 'wpCronDisabled' => $wpCronDisabled]
        );
    }

    private function analyzeUsers(): ?array {
        $users = $this->getSection('users');
        if (empty($users)) return null;

        $total = $users['total_users'] ?? 0;
        $roleCounts = $users['role_counts'] ?? [];
        $admins = $roleCounts['administrator'] ?? 0;

        $score = $admins <= 1 ? 100 : ($admins <= 3 ? 80 : 50);

        return Scoring::createMetric(
            'users_roles', 'Usuarios y roles',
            $total, "$total usuarios · $admins administradores",
            $score,
            "$total usuarios registrados. $admins administradores." . ($admins > 3 ? ' Demasiados admins aumenta la superficie de ataque.' : ''),
            $admins > 3 ? 'Revisar si todos los admins son necesarios. Asignar roles más específicos (Editor, Autor) donde sea posible.' : '',
            'Auditamos los roles de usuario y aplicamos el principio de mínimo privilegio.',
            ['total' => $total, 'roles' => $roleCounts]
        );
    }

    private function analyzeObjectCache(): ?array {
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

    private function analyzeMysqlVersion(): ?array {
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

    private function analyzeUploadLimits(): ?array {
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

    private function analyzeExecutionLimits(): ?array {
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

    private function analyzeSiteUrlMismatch(): ?array {
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

    private function analyzeMultisite(): ?array {
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

    private function analyzeAutoUpdates(): ?array {
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

    private function analyzePluginOverload(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $active = $plugins['active_count'] ?? 0;
        if ($active < 15) return null;

        $score = $active <= 20 ? 70 : ($active <= 30 ? 50 : ($active <= 40 ? 30 : 10));

        return Scoring::createMetric(
            'plugin_overload', 'Exceso de plugins activos',
            $active, "$active plugins activos",
            $score,
            "$active plugins activos. Un número excesivo de plugins aumenta el tiempo de carga, el consumo de memoria y la superficie de ataque.",
            'Auditar qué plugins son realmente necesarios. Combinar funcionalidades y eliminar plugins redundantes.',
            'Auditamos y reducimos la cantidad de plugins necesarios optimizando funcionalidad.',
            ['activeCount' => $active]
        );
    }

    private function analyzeAbandonedPlugins(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $list = $plugins['plugins'] ?? [];
        $abandoned = [];
        $now = time();

        foreach ($list as $p) {
            $lastUpdated = $p['last_updated'] ?? ($p['last_updated_date'] ?? null);
            if (empty($lastUpdated)) continue;
            $ts = strtotime($lastUpdated);
            if ($ts === false) continue;
            $daysSince = ($now - $ts) / 86400;
            if ($daysSince > 730) {
                $abandoned[] = [
                    'name' => $p['name'] ?? '?',
                    'lastUpdated' => $lastUpdated,
                    'daysSince' => (int) $daysSince,
                ];
            }
        }

        if (empty($abandoned)) return null;

        $count = count($abandoned);
        $score = $count <= 1 ? 60 : ($count <= 3 ? 40 : 20);

        return Scoring::createMetric(
            'abandoned_plugins', 'Plugins abandonados',
            $count, "$count plugins sin actualizar en +2 años",
            $score,
            "$count plugins no han sido actualizados por sus desarrolladores en más de 2 años. Pueden tener vulnerabilidades no parcheadas.",
            'Buscar alternativas actualizadas para estos plugins o evaluar si aún son necesarios.',
            'Identificamos y reemplazamos plugins abandonados por alternativas seguras y actualizadas.',
            ['count' => $count, 'plugins' => $abandoned]
        );
    }

    private function analyzeInactiveThemes(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $total = $themes['total_themes'] ?? 0;
        $inactive = max(0, $total - 1);
        if ($inactive <= 1) return null;

        $score = $inactive <= 2 ? 80 : ($inactive <= 4 ? 60 : 30);

        return Scoring::createMetric(
            'inactive_themes', 'Temas inactivos',
            $inactive, "$inactive temas sin usar",
            $score,
            "$inactive temas inactivos instalados. Los temas inactivos pueden contener vulnerabilidades y deben eliminarse. Mantener solo el tema activo y un tema default (Twenty Twenty-Four) como fallback.",
            'Eliminar todos los temas inactivos excepto uno por defecto como respaldo.',
            'Limpiamos temas innecesarios reduciendo la superficie de ataque.',
            ['total' => $total, 'inactive' => $inactive]
        );
    }

    private function analyzeDbEngine(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $tables = $db['tables'] ?? [];
        $myisamTables = [];
        foreach ($tables as $t) {
            $engine = $t['engine'] ?? ($t['Engine'] ?? '');
            if (stripos($engine, 'myisam') !== false) {
                $myisamTables[] = $t['name'] ?? '?';
            }
        }

        if (empty($myisamTables)) return null;

        $count = count($myisamTables);

        return Scoring::createMetric(
            'db_engine', 'Motor de base de datos',
            'MyISAM', "$count tablas con MyISAM",
            $count <= 2 ? 70 : 40,
            "$count tablas usan el motor MyISAM, que no soporta transacciones, bloqueo a nivel de fila, ni foreign keys. InnoDB es superior en rendimiento y confiabilidad.",
            'Convertir las tablas MyISAM a InnoDB con: ALTER TABLE nombre ENGINE=InnoDB;',
            'Migramos tablas a InnoDB para mejor rendimiento y confiabilidad.',
            ['count' => $count, 'tables' => array_slice($myisamTables, 0, 10)]
        );
    }

    private function analyzeSpamComments(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $spam = $db['spam_comments'] ?? ($db['spam_comment_count'] ?? null);
        if ($spam === null || $spam === 0) return null;

        $score = $spam < 100 ? 80 : ($spam < 1000 ? 50 : 20);

        return Scoring::createMetric(
            'spam_comments', 'Comentarios spam',
            $spam, "$spam comentarios spam",
            $score,
            "$spam comentarios marcados como spam en la base de datos. Estos ocupan espacio y afectan el rendimiento de consultas.",
            'Vaciar la carpeta de spam desde Comentarios → Spam → Vaciar spam. Instalar Akismet o similar para prevención.',
            'Limpiamos spam acumulado y configuramos protección anti-spam efectiva.',
            ['count' => $spam]
        );
    }

    private function analyzeTrashedPosts(): ?array {
        $db = $this->getSection('database');
        $pt = $this->getSection('post_types');
        $trashed = $db['trashed_posts'] ?? ($pt['trashed_count'] ?? null);
        if ($trashed === null || $trashed === 0) return null;

        $score = $trashed < 50 ? 90 : ($trashed < 200 ? 70 : 40);

        return Scoring::createMetric(
            'trashed_posts', 'Posts en papelera',
            $trashed, "$trashed posts",
            $score,
            "$trashed posts en la papelera. Estos se mantienen en la DB ocupando espacio innecesario.",
            'Vaciar la papelera desde Posts → Papelera → Vaciar papelera. Configurar limpieza automática con EMPTY_TRASH_DAYS.',
            'Configuramos limpieza automática de la papelera para mantener la DB limpia.',
            ['count' => $trashed]
        );
    }

    private function analyzeWeakAdminUsers(): ?array {
        $users = $this->getSection('users');
        if (empty($users)) return null;

        $userList = $users['users'] ?? [];
        $weakNames = ['admin', 'administrator', 'root', 'test', 'user'];
        $found = [];

        foreach ($userList as $u) {
            $login = strtolower($u['user_login'] ?? ($u['login'] ?? ($u['username'] ?? '')));
            $role = $u['role'] ?? ($u['roles'] ?? '');
            if (is_array($role)) $role = implode(', ', $role);
            if (in_array($login, $weakNames, true)) {
                $found[] = ['login' => $login, 'role' => $role];
            }
        }

        if (empty($found)) return null;

        return Scoring::createMetric(
            'weak_admin_users', 'Usuarios con nombres predecibles',
            count($found), count($found) . ' usuarios con nombre débil',
            count($found) >= 2 ? 20 : 40,
            'Se detectaron usuarios con nombres predecibles (' . implode(', ', array_column($found, 'login')) . '). Estos son los primeros que los atacantes intentan en ataques de fuerza bruta.',
            'Crear nuevos usuarios con nombres únicos, transferir el contenido y eliminar los usuarios con nombres predecibles.',
            'Cambiamos usernames predecibles y configuramos protección contra fuerza bruta.',
            ['users' => $found]
        );
    }

    private function analyzeOpcache(): ?array {
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

    private function analyzeMediaSize(): ?array {
        $media = $this->getSection('media');
        if (empty($media)) return null;

        $totalCount = $media['total_count'] ?? ($media['total_items'] ?? 0);
        $totalSize = $media['total_size'] ?? ($media['uploads_size'] ?? 0);
        $orphaned = $media['orphaned_count'] ?? ($media['unattached'] ?? null);

        if ($totalCount === 0 && $totalSize === 0) return null;

        $humanSize = $this->formatBytes($totalSize);
        $gbSize = $totalSize / (1024 * 1024 * 1024);

        $score = 100;
        if ($gbSize > 5) $score -= 30;
        elseif ($gbSize > 2) $score -= 15;
        if ($orphaned !== null && $orphaned > 50) $score -= 20;

        $desc = "$totalCount archivos de medios ($humanSize).";
        if ($orphaned !== null && $orphaned > 0) {
            $desc .= " $orphaned archivos no están asociados a ningún contenido (huérfanos).";
        }

        return Scoring::createMetric(
            'media_size', 'Biblioteca de medios',
            $totalCount, "$totalCount archivos · $humanSize",
            Scoring::clamp($score),
            $desc . ($gbSize > 2 ? ' La biblioteca es muy pesada — considerar limpieza y optimización de imágenes.' : ''),
            ($gbSize > 2 || ($orphaned !== null && $orphaned > 50))
                ? 'Eliminar medios no usados, comprimir imágenes con ShortPixel/Imagify, y servir en formato WebP.'
                : '',
            'Optimizamos la biblioteca de medios: compresión, formato WebP y limpieza de archivos no utilizados.',
            ['totalCount' => $totalCount, 'totalSize' => $totalSize, 'humanSize' => $humanSize, 'orphaned' => $orphaned]
        );
    }

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

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . 'B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . 'KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 1) . 'MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . 'GB';
    }
}
