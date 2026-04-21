<?php
/**
 * Construye el reporte estructurado a partir del JSON crudo de wp-snapshot.
 *
 * Cada builder privado lee una sección del JSON y produce un bloque con:
 *   - `summary`: agregados para KPIs/tiles
 *   - `items`:   lista detallada para tablas
 *   - `issues`:  hallazgos accionables con severidad y recomendación
 *
 * Mantenemos el formato consistente para que el frontend sólo itere sin
 * inferir nada. La lógica de scoring general queda en WpSnapshotAnalyzer
 * (se usa en el módulo del audit); aquí el objetivo es narrativa técnica.
 */

class SnapshotReportBuilder {
    public function __construct(private array $snapshot) {}

    /**
     * Devuelve el reporte completo. Cada clave es una "card" del dashboard.
     */
    public function build(): array {
        return [
            'overview'    => $this->buildOverview(),
            'environment' => $this->buildEnvironment(),
            'plugins'     => $this->buildPlugins(),
            'themes'      => $this->buildThemes(),
            'security'    => $this->buildSecurity(),
            'performance' => $this->buildPerformance(),
            'database'    => $this->buildDatabase(),
            'cron'        => $this->buildCron(),
            'media'       => $this->buildMedia(),
            'users'       => $this->buildUsers(),
            'content'     => $this->buildContent(),
        ];
    }

    // ——— Lecturas crudas ———————————————————————————————————————————

    private function section(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    // ——— Builders (stubs, se implementan en Fase 1b/1c) ————————————

    // ——— Overview: KPIs principales ———————————————————————————————

    private function buildOverview(): array {
        $env  = $this->section('environment');
        $pl   = $this->section('plugins');
        $th   = $this->section('themes');
        $db   = $this->section('database');
        $perf = $this->section('performance');
        $usr  = $this->section('users');
        $sec  = $this->section('security');

        $cacheStack = [
            'page'   => (bool) ($perf['page_cache_likely'] ?? false),
            'object' => (bool) ($perf['object_cache_active'] ?? false),
            'opcache' => (bool) ($perf['opcache_enabled'] ?? false),
        ];
        $cacheActive = (int) array_sum($cacheStack);

        return [
            'site' => [
                'name'     => $this->snapshot['site_name'] ?? '',
                'url'      => $this->snapshot['site_url'] ?? '',
                'wpVersion' => $env['wp_version'] ?? '',
                'phpVersion' => $env['php_version'] ?? '',
                'server'   => $env['server_software'] ?? '',
                'db'       => trim(($env['db_type'] ?? '') . ' ' . ($env['db_version'] ?? '')),
                'isHttps'  => (bool) ($env['is_https'] ?? false),
            ],
            'kpis' => [
                'pluginsTotal'       => (int) ($pl['total_plugins'] ?? 0),
                'pluginsActive'      => (int) ($pl['active_count'] ?? 0),
                'pluginsInactive'    => (int) ($pl['inactive_count'] ?? 0),
                'pluginsOutdated'    => (int) ($pl['update_available'] ?? 0),
                'themesTotal'        => (int) ($th['total_themes'] ?? 0),
                'activeTheme'        => $th['active_theme']['name'] ?? '',
                'activeThemeHasUpdate' => (bool) ($th['active_theme']['has_update'] ?? false),
                'dbSizeHuman'        => $db['total_db_size_human'] ?? '',
                'dbTables'           => (int) ($db['total_tables'] ?? 0),
                'users'              => (int) ($usr['total_users'] ?? 0),
                'administrators'     => $this->countAdministrators($usr),
                'cacheActive'        => $cacheActive,           // 0-3
                'cacheStack'         => $cacheStack,
                'securityGood'       => (int) ($sec['good_count'] ?? 0),
                'securityWarning'    => (int) ($sec['warning_count'] ?? 0),
                'securityCritical'   => (int) ($sec['critical_count'] ?? 0),
            ],
        ];
    }

    // ——— Environment: stack y límites del servidor ————————————————

    private function buildEnvironment(): array {
        $env = $this->section('environment');
        $perf = $this->section('performance');

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $latestWp = $defaults['latest_wp_version'] ?? '';

        $wpVersion = $env['wp_version'] ?? '';
        $wpIsLatest = $wpVersion !== '' && $latestWp !== '' && version_compare($wpVersion, $latestWp, '>=');

        $extensions = $env['php_extensions'] ?? [];
        $missingExts = [];
        foreach (['curl', 'gd', 'mbstring', 'openssl', 'xml', 'zip', 'intl', 'imagick', 'fileinfo'] as $e) {
            if (isset($extensions[$e]) && !$extensions[$e]) $missingExts[] = $e;
        }

        $issues = [];
        if ($wpVersion !== '' && !$wpIsLatest) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => "WordPress $wpVersion — disponible $latestWp",
                'action'   => "Actualizar core desde Escritorio → Actualizaciones (backup previo obligatorio).",
            ];
        }
        $phpVersion = $env['php_version'] ?? '';
        if ($phpVersion !== '' && version_compare($phpVersion, '8.1', '<')) {
            $issues[] = [
                'severity' => version_compare($phpVersion, '8.0', '<') ? 'critical' : 'warning',
                'title'    => "PHP $phpVersion quedará sin soporte",
                'action'   => "Actualizar a PHP 8.2 o superior. Verificar compatibilidad con todos los plugins activos primero.",
            ];
        }
        if (!empty($missingExts)) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => 'Extensiones PHP recomendadas ausentes: ' . implode(', ', $missingExts),
                'action'   => 'Instalar vía panel de PHP del hosting. Imagick es especialmente importante para WebP/compresión.',
            ];
        }
        if ((bool) ($env['wp_debug'] ?? false)) {
            $severity = ($env['wp_debug_display'] ?? false) ? 'critical' : 'warning';
            $issues[] = [
                'severity' => $severity,
                'title'    => $severity === 'critical' ? 'WP_DEBUG + WP_DEBUG_DISPLAY activos en producción' : 'WP_DEBUG activo',
                'action'   => $severity === 'critical'
                    ? 'Los errores PHP se imprimen a visitantes (leak de paths, versiones). Desactivar WP_DEBUG_DISPLAY en wp-config.php ya.'
                    : 'Aceptable si es solo para log interno. En producción lo ideal es apagar debug completamente.',
            ];
        }

        return [
            'wordpress' => [
                'version'  => $wpVersion,
                'latest'   => $latestWp,
                'isLatest' => $wpIsLatest,
                'locale'   => $env['wp_locale'] ?? '',
                'timezone' => $env['wp_timezone'] ?? '',
                'multisite' => (bool) ($env['is_multisite'] ?? false),
                'debug'    => (bool) ($env['wp_debug'] ?? false),
                'debugDisplay' => (bool) ($env['wp_debug_display'] ?? false),
                'debugLog' => (bool) ($env['wp_debug_log'] ?? false),
                'memoryLimit'  => $env['wp_memory_limit'] ?? '',
                'maxMemoryLimit' => $env['wp_max_memory_limit'] ?? '',
                'permalinks'   => $perf['permalink_structure'] ?? '',
            ],
            'php' => [
                'version'        => $phpVersion,
                'memoryLimit'    => $env['php_memory_limit'] ?? '',
                'maxExecution'   => (int) ($env['php_max_execution'] ?? 0),
                'maxUpload'      => $env['php_max_upload'] ?? '',
                'postMaxSize'    => $env['php_post_max_size'] ?? '',
                'opcacheEnabled' => (bool) ($env['opcache_enabled'] ?? $perf['opcache_enabled'] ?? false),
                'extensions'     => $extensions,
                'missingExtensions' => $missingExts,
            ],
            'database' => [
                'type'       => $env['db_type'] ?? '',
                'version'    => $env['db_version'] ?? '',
                'serverInfo' => $env['db_server_info'] ?? '',
            ],
            'server' => [
                'software' => $env['server_software'] ?? '',
                'os'       => $env['server_os'] ?? '',
                'isHttps'  => (bool) ($env['is_https'] ?? false),
                'htaccessWritable' => (bool) ($env['htaccess_writable'] ?? false),
            ],
            'issues' => $issues,
        ];
    }

    // ——— Plugins con vuln lookup paralelizado ——————————————————

    private function buildPlugins(): array {
        $pl = $this->section('plugins');
        $list = $pl['plugins'] ?? [];
        $muPlugins = $pl['mu_plugins'] ?? [];
        $dropins = $pl['dropins'] ?? [];

        // Resolver vulnerabilidades en paralelo usando cache 24h por slug
        $vulnMap = $this->fetchPluginVulnerabilities($list);

        $items = [];
        foreach ($list as $p) {
            $slug = $this->slugFromFile($p['file'] ?? '');
            $version = $p['version'] ?? '';
            $vulns = $this->applicableVulns($vulnMap[$slug] ?? null, $version);

            $items[] = [
                'slug'          => $slug,
                'name'          => $p['name'] ?? $slug,
                'version'       => $version,
                'author'        => $p['author'] ?? '',
                'uri'           => $p['uri'] ?? '',
                'description'   => $p['description'] ?? '',
                'isActive'      => (bool) ($p['is_active'] ?? false),
                'hasUpdate'     => (bool) ($p['has_update'] ?? false),
                'updateVersion' => $p['update_version'] ?? null,
                'autoUpdate'    => (bool) ($p['auto_update'] ?? false),
                'networkActive' => (bool) ($p['network_active'] ?? false),
                'requiresWp'    => $p['requires_wp'] ?? '',
                'requiresPhp'   => $p['requires_php'] ?? '',
                'vulnerabilities' => $vulns,          // [] si seguro
                'vulnerabilityStatus' => $this->vulnStatus($vulns, $p['has_update'] ?? false),
            ];
        }

        // Ordenar: vulnerables primero, luego outdated, luego resto
        usort($items, function ($a, $b) {
            $rank = fn($x) => match ($x['vulnerabilityStatus']) {
                'vulnerable' => 0, 'outdated_vulnerable' => 0, 'outdated' => 1, default => 2,
            };
            return [$rank($a), strtolower($a['name'])] <=> [$rank($b), strtolower($b['name'])];
        });

        $vulnerableCount = count(array_filter($items, fn($i) => !empty($i['vulnerabilities'])));

        return [
            'summary' => [
                'total'           => (int) ($pl['total_plugins'] ?? count($list)),
                'active'          => (int) ($pl['active_count'] ?? 0),
                'inactive'        => (int) ($pl['inactive_count'] ?? 0),
                'outdated'        => (int) ($pl['update_available'] ?? 0),
                'vulnerable'      => $vulnerableCount,
                'muPluginsCount'  => count($muPlugins),
                'dropinsCount'    => count($dropins),
            ],
            'items'    => $items,
            'muPlugins' => array_map(fn($m) => [
                'name' => $m['name'] ?? $m['file'] ?? '?',
                'version' => $m['version'] ?? '',
                'author'  => $m['author'] ?? '',
                'file'    => $m['file'] ?? '',
            ], $muPlugins),
            'dropins' => array_map(fn($d) => [
                'name' => $d['name'] ?? $d['file'] ?? '?',
                'version' => $d['version'] ?? '',
                'author'  => $d['author'] ?? '',
                'file'    => $d['file'] ?? '',
            ], $dropins),
        ];
    }

    // ——— Helpers ————————————————————————————————————————————————

    private function countAdministrators(array $users): int {
        foreach ($users['roles'] ?? [] as $r) {
            if (($r['slug'] ?? '') === 'administrator') return (int) ($r['user_count'] ?? 0);
        }
        return 0;
    }

    private function slugFromFile(string $file): string {
        if ($file === '') return '';
        $parts = explode('/', $file);
        return $parts[0] ?? '';
    }

    /**
     * Cruza una lista de plugins contra WPVulnerability API en paralelo.
     * Devuelve [slug => apiData|null] con cache 24h por slug.
     */
    private function fetchPluginVulnerabilities(array $plugins): array {
        $cache = new Cache();
        $result = [];
        $urlsToFetch = [];

        foreach ($plugins as $p) {
            $slug = $this->slugFromFile($p['file'] ?? '');
            if ($slug === '' || isset($result[$slug])) continue;
            $cached = $cache->getByName("vuln_plugin_$slug");
            if ($cached !== null) {
                $result[$slug] = ($cached['error'] ?? 0) ? null : $cached;
            } else {
                $urlsToFetch[$slug] = "https://www.wpvulnerability.net/plugin/$slug/";
            }
        }

        if (!empty($urlsToFetch)) {
            $responses = Fetcher::multiGet($urlsToFetch, 5);
            foreach ($responses as $slug => $resp) {
                $apiData = null;
                if (($resp['statusCode'] ?? 0) === 200) {
                    $decoded = json_decode($resp['body'] ?? '', true);
                    if (is_array($decoded) && isset($decoded['data'])) $apiData = $decoded;
                }
                // Cachear incluso los errores para no reintentar en loop
                $cache->setByName("vuln_plugin_$slug", $apiData ?? ['error' => 1, 'data' => null], 86400);
                $result[$slug] = $apiData;
            }
        }
        return $result;
    }

    /**
     * Filtra las vulnerabilidades del API aplicables a la versión detectada.
     * Reutiliza el formato del SecurityVulnerabilityChecker para consistencia.
     */
    private function applicableVulns(?array $apiData, ?string $version): array {
        if ($apiData === null || empty($apiData['data']['vulnerability'])) return [];
        $out = [];
        foreach ($apiData['data']['vulnerability'] as $v) {
            $op = $v['operator'] ?? [];
            $maxV = $op['max_version'] ?? null;
            $maxOp = $op['max_operator'] ?? 'le';
            $unfixed = (string) ($op['unfixed'] ?? '0') === '1';

            $applies = false;
            if ($unfixed) {
                $applies = true;
            } elseif ($version !== null && $maxV !== null) {
                $applies = $maxOp === 'lt'
                    ? version_compare($version, $maxV, '<')
                    : version_compare($version, $maxV, '<=');
            }
            if (!$applies) continue;

            $cve = '';
            foreach ($v['source'] ?? [] as $s) {
                if (str_starts_with($s['id'] ?? '', 'CVE-')) { $cve = $s['id']; break; }
            }
            $impact = $v['impact']['cvss'] ?? [];
            $out[] = [
                'name'       => $v['name'] ?? 'Vulnerabilidad',
                'cve'        => $cve,
                'severity'   => strtolower($impact['severity'] ?? 'medium'),
                'cvssScore'  => $impact['score'] ?? null,
                'fixedIn'    => $unfixed ? null : $maxV,
                'unfixed'    => $unfixed,
            ];
        }
        return $out;
    }

    private function vulnStatus(array $vulns, bool $hasUpdate): string {
        if (!empty($vulns)) return $hasUpdate ? 'outdated_vulnerable' : 'vulnerable';
        return $hasUpdate ? 'outdated' : 'safe';
    }
    private function buildThemes(): array      { return []; }
    private function buildSecurity(): array    { return []; }
    private function buildPerformance(): array { return []; }
    private function buildDatabase(): array    { return []; }
    private function buildCron(): array        { return []; }
    private function buildMedia(): array       { return []; }
    private function buildUsers(): array       { return []; }
    private function buildContent(): array     { return []; }
}
