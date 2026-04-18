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

        $metrics[] = $this->analyzeEnvironment();
        $metrics[] = $this->analyzeWpDebug();
        $metrics[] = $this->analyzeFileEditing();

        $pluginsMetric = $this->analyzePlugins();
        if ($pluginsMetric !== null) $metrics[] = $pluginsMetric;

        $inactivePluginsMetric = $this->analyzeInactivePlugins();
        if ($inactivePluginsMetric !== null) $metrics[] = $inactivePluginsMetric;

        $themesMetric = $this->analyzeThemes();
        if ($themesMetric !== null) $metrics[] = $themesMetric;

        $dbMetric = $this->analyzeDatabase();
        if ($dbMetric !== null) $metrics[] = $dbMetric;

        $autoloadMetric = $this->analyzeAutoload();
        if ($autoloadMetric !== null) $metrics[] = $autoloadMetric;

        $revisionsMetric = $this->analyzeRevisions();
        if ($revisionsMetric !== null) $metrics[] = $revisionsMetric;

        $transientsMetric = $this->analyzeTransients();
        if ($transientsMetric !== null) $metrics[] = $transientsMetric;

        $orphanedMetric = $this->analyzeOrphanedMeta();
        if ($orphanedMetric !== null) $metrics[] = $orphanedMetric;

        $cronMetric = $this->analyzeCron();
        if ($cronMetric !== null) $metrics[] = $cronMetric;

        $usersMetric = $this->analyzeUsers();
        if ($usersMetric !== null) $metrics[] = $usersMetric;

        $cacheMetric = $this->analyzeObjectCache();
        if ($cacheMetric !== null) $metrics[] = $cacheMetric;

        // Filtrar métricas null (las que no aplican)
        $metrics = array_values(array_filter($metrics, fn($m) => $m !== null));

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
