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
                'title'    => Translator::t('snapshot_report.env.wp_outdated.title', ['version' => $wpVersion, 'latest' => $latestWp]),
                'action'   => Translator::t('snapshot_report.env.wp_outdated.action'),
            ];
        }
        $phpVersion = $env['php_version'] ?? '';
        if ($phpVersion !== '' && version_compare($phpVersion, '8.1', '<')) {
            $issues[] = [
                'severity' => version_compare($phpVersion, '8.0', '<') ? 'critical' : 'warning',
                'title'    => Translator::t('snapshot_report.env.php_outdated.title', ['version' => $phpVersion]),
                'action'   => Translator::t('snapshot_report.env.php_outdated.action'),
            ];
        }
        if (!empty($missingExts)) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.env.missing_exts.title', ['exts' => implode(', ', $missingExts)]),
                'action'   => Translator::t('snapshot_report.env.missing_exts.action'),
            ];
        }
        if ((bool) ($env['wp_debug'] ?? false)) {
            $severity = ($env['wp_debug_display'] ?? false) ? 'critical' : 'warning';
            $issues[] = [
                'severity' => $severity,
                'title'    => Translator::t("snapshot_report.env.debug_{$severity}.title"),
                'action'   => Translator::t("snapshot_report.env.debug_{$severity}.action"),
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
                'name'       => $v['name'] ?? Translator::t('snapshot_report.vuln.fallback_name'),
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
    // ——— Themes ——————————————————————————————————————————————————

    private function buildThemes(): array {
        $th = $this->section('themes');
        $active = $th['active_theme'] ?? [];
        $installed = $th['installed'] ?? [];

        $items = array_map(fn($t) => [
            'slug'          => $t['stylesheet'] ?? $t['template'] ?? '',
            'name'          => $t['name'] ?? '?',
            'version'       => $t['version'] ?? '',
            'author'        => $t['author'] ?? '',
            'uri'           => $t['uri'] ?? '',
            'isActive'      => (bool) ($t['is_active'] ?? false),
            'isChildTheme'  => (bool) ($t['is_child_theme'] ?? false),
            'parent'        => $t['parent_theme'] ?? null,
            'isBlockTheme'  => (bool) ($t['is_block_theme'] ?? false),
            'hasUpdate'     => (bool) ($t['has_update'] ?? false),
            'requiresWp'    => $t['requires_wp'] ?? '',
            'requiresPhp'   => $t['requires_php'] ?? '',
        ], $installed);

        usort($items, fn($a, $b) => [$b['isActive'], strtolower($a['name'])] <=> [$a['isActive'], strtolower($b['name'])]);

        $issues = [];
        if (!empty($active) && !($active['is_child_theme'] ?? false)) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.themes.no_child.title', ['name' => $active['name'] ?? '']),
                'action'   => Translator::t('snapshot_report.themes.no_child.action', ['slug' => strtolower($active['name'] ?? '')]),
            ];
        }
        if (($active['has_update'] ?? false)) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.themes.outdated.title', ['name' => $active['name'] ?? '']),
                'action'   => Translator::t('snapshot_report.themes.outdated.action'),
            ];
        }
        $inactiveCount = (int) ($th['total_themes'] ?? 0) - (empty($active) ? 0 : 1);
        if ($inactiveCount >= 3) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.themes.inactive_excess.title', ['count' => $inactiveCount]),
                'action'   => Translator::t('snapshot_report.themes.inactive_excess.action'),
            ];
        }

        return [
            'summary' => [
                'total'       => (int) ($th['total_themes'] ?? 0),
                'activeName'  => $active['name'] ?? '',
                'isChild'     => (bool) ($active['is_child_theme'] ?? false),
                'updates'     => (int) ($th['update_available'] ?? 0),
            ],
            'items'  => $items,
            'issues' => $issues,
        ];
    }

    // ——— Security: 11 checks del plugin + hallazgos ——————————————

    private function buildSecurity(): array {
        $sec = $this->section('security');
        $checks = $sec['checks'] ?? [];

        $items = [];
        $issues = [];
        foreach ($checks as $key => $c) {
            $status = $c['status'] ?? 'info';          // good|warning|critical|info
            $items[] = [
                'id'     => $key,
                'label'  => $c['label'] ?? $key,
                'value'  => $c['value'] ?? null,
                'status' => $status,
                'note'   => $c['note'] ?? '',
            ];
            if ($status === 'critical' || $status === 'warning') {
                $issues[] = [
                    'severity' => $status,
                    'title'    => $c['label'] ?? $key,
                    'action'   => $this->securityActionFor($key, $c),
                ];
            }
        }

        // Orden: critical → warning → info → good
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2, 'good' => 3];
        usort($items, fn($a, $b) => ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9));

        return [
            'summary' => [
                'critical' => (int) ($sec['critical_count'] ?? 0),
                'warning'  => (int) ($sec['warning_count'] ?? 0),
                'good'     => (int) ($sec['good_count'] ?? 0),
            ],
            'items'  => $items,
            'issues' => $issues,
        ];
    }

    private function securityActionFor(string $key, array $c): string {
        $value = $c['value'] ?? null;
        // Algunos checks solo dan recomendación cuando la configuración es
        // "insegura" (ej. ssl=false, db_prefix=wp_, xmlrpc=true).
        $conditionallySilent = match ($key) {
            'file_editing', 'db_prefix', 'ssl' => (bool) $value,
            'xmlrpc'                           => !$value,
            default                            => false,
        };
        if ($conditionallySilent) return '';

        $tKey = "snapshot_report.security.action.$key";
        if (Translator::has($tKey)) return Translator::t($tKey);
        return (string) ($c['note'] ?? '');
    }

    // ——— Database: tamaño, tablas top, autoload, cleanup ——————————

    private function buildDatabase(): array {
        $db = $this->section('database');
        $tables = $db['tables'] ?? [];

        // Top 15 tablas por tamaño
        usort($tables, fn($a, $b) => ((int) ($b['total_size'] ?? 0)) <=> ((int) ($a['total_size'] ?? 0)));
        $top = array_slice($tables, 0, 15);
        $topOut = array_map(fn($t) => [
            'name'    => $t['name'] ?? '',
            'engine'  => $t['engine'] ?? '',
            'rows'    => (int) ($t['rows'] ?? 0),
            'sizeBytes' => (int) ($t['total_size'] ?? 0),
            'sizeMb'  => round(((int) ($t['total_size'] ?? 0)) / (1024 * 1024), 1),
            'collation' => $t['collation'] ?? '',
        ], $top);

        $myisam = array_values(array_filter($tables, fn($t) => strtolower((string) ($t['engine'] ?? '')) === 'myisam'));
        $myisamList = array_map(fn($t) => ['name' => $t['name'] ?? '', 'rows' => (int) ($t['rows'] ?? 0)], $myisam);

        $autoloadBytes = (int) ($db['autoload_size'] ?? 0);
        $autoloadMb = $autoloadBytes / (1024 * 1024);
        $revisions = (int) ($db['revisions_count'] ?? 0);
        $transients = (int) ($db['transients_count'] ?? 0);
        $orphaned = (int) ($db['orphaned_postmeta'] ?? 0);

        $issues = [];
        if ($autoloadMb > 1) {
            $issues[] = [
                'severity' => $autoloadMb > 3 ? 'critical' : 'warning',
                'title'    => Translator::t('snapshot_report.db.autoload.title', [
                    'size'  => $db['autoload_size_human'] ?? '?',
                    'count' => (int) ($db['autoloaded_options'] ?? 0),
                ]),
                'action'   => Translator::t('snapshot_report.db.autoload.action'),
            ];
        }
        if ($revisions >= 500) {
            $issues[] = [
                'severity' => $revisions >= 2000 ? 'warning' : 'info',
                'title'    => Translator::t('snapshot_report.db.revisions.title', ['count' => $revisions]),
                'action'   => Translator::t('snapshot_report.db.revisions.action'),
            ];
        }
        if (!empty($myisam)) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.db.myisam.title', ['count' => count($myisam)]),
                'action'   => Translator::t('snapshot_report.db.myisam.action'),
            ];
        }
        if ($orphaned > 100) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.db.orphaned.title', ['count' => $orphaned]),
                'action'   => Translator::t('snapshot_report.db.orphaned.action'),
            ];
        }

        return [
            'summary' => [
                'sizeBytes'    => (int) ($db['total_db_size'] ?? 0),
                'sizeHuman'    => $db['total_db_size_human'] ?? '',
                'tables'       => (int) ($db['total_tables'] ?? 0),
                'rows'         => (int) ($db['total_rows'] ?? 0),
                'prefix'       => $db['db_prefix'] ?? '',
                'charset'      => $db['db_charset'] ?? '',
                'collation'    => $db['db_collate'] ?? '',
                'autoloadHuman' => $db['autoload_size_human'] ?? '',
                'autoloadBytes' => $autoloadBytes,
                'autoloadOptions' => (int) ($db['autoloaded_options'] ?? 0),
                'totalOptions' => (int) ($db['total_options'] ?? 0),
                'transients'   => $transients,
                'revisions'    => $revisions,
                'trashed'      => (int) ($db['trashed_count'] ?? 0),
                'orphanedMeta' => $orphaned,
                'myisamCount'  => count($myisam),
            ],
            'topTables'  => $topOut,
            'myisamTables' => $myisamList,
            'postCounts' => $db['post_counts'] ?? [],
            'issues'     => $issues,
        ];
    }

    // ——— Performance: cache stack + image editor + opcache ———————

    private function buildPerformance(): array {
        $perf = $this->section('performance');
        $env  = $this->section('environment');

        $pageCache = (bool) ($perf['page_cache_likely'] ?? false);
        $objectCache = (bool) ($perf['object_cache_active'] ?? false);
        $objectCacheType = $perf['object_cache_type'] ?? 'None';
        $opcache = (bool) ($perf['opcache_enabled'] ?? $env['opcache_enabled'] ?? false);
        $imageEditor = $perf['image_editor'] ?? '';

        $issues = [];
        if (!$opcache) {
            $issues[] = [
                'severity' => 'critical',
                'title'    => Translator::t('snapshot_report.perf.opcache.title'),
                'action'   => Translator::t('snapshot_report.perf.opcache.action'),
            ];
        }
        if (!$objectCache) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.perf.object_cache.title'),
                'action'   => Translator::t('snapshot_report.perf.object_cache.action'),
            ];
        }
        if (!$pageCache) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.perf.page_cache.title'),
                'action'   => Translator::t('snapshot_report.perf.page_cache.action'),
            ];
        }
        if ($imageEditor && stripos($imageEditor, 'imagick') === false) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.perf.image_editor.title', ['editor' => $imageEditor]),
                'action'   => Translator::t('snapshot_report.perf.image_editor.action'),
            ];
        }

        return [
            'summary' => [
                'pageCache'        => $pageCache,
                'objectCache'      => $objectCache,
                'objectCacheType'  => $objectCacheType,
                'objectCacheDropin' => (bool) ($perf['object_cache_dropin'] ?? false),
                'opcache'          => $opcache,
                'imageEditor'      => $imageEditor,
                'permalinks'       => $perf['permalink_structure'] ?? '',
                'wpOrgReachable'   => (bool) ($perf['wp_org_reachable'] ?? true),
            ],
            'issues' => $issues,
        ];
    }
    // ——— Cron: eventos y schedules ——————————————————————————————

    private function buildCron(): array {
        $cron = $this->section('cron');
        $events = $cron['events'] ?? [];

        // Top hooks por recurrencia (detectar plugins que abusan del cron)
        $byHook = [];
        foreach ($events as $e) {
            $hook = $e['hook'] ?? '';
            if ($hook === '') continue;
            $byHook[$hook] = ($byHook[$hook] ?? 0) + 1;
        }
        arsort($byHook);
        $topHooks = [];
        foreach (array_slice($byHook, 0, 15, true) as $hook => $count) {
            $topHooks[] = ['hook' => $hook, 'count' => $count];
        }

        // Eventos atrasados
        $overdueList = array_values(array_filter($events, fn($e) => !empty($e['overdue'])));
        $overdueItems = array_map(fn($e) => [
            'hook'     => $e['hook'] ?? '',
            'nextRun'  => $e['next_run_human'] ?? '',
            'diff'     => $e['next_run_diff'] ?? '',
            'schedule' => $e['schedule'] ?? '',
        ], array_slice($overdueList, 0, 30));

        // Próximos 20 (para dar contexto)
        usort($events, fn($a, $b) => ((int) ($a['next_run'] ?? 0)) <=> ((int) ($b['next_run'] ?? 0)));
        $upcoming = array_map(fn($e) => [
            'hook'     => $e['hook'] ?? '',
            'nextRun'  => $e['next_run_human'] ?? '',
            'diff'     => $e['next_run_diff'] ?? '',
            'schedule' => $e['schedule_label'] ?? ($e['schedule'] ?? ''),
            'interval' => (int) ($e['interval'] ?? 0),
        ], array_slice($events, 0, 20));

        $issues = [];
        $wpCronDisabled = (bool) ($cron['wp_cron_disabled'] ?? false);
        if (!empty($overdueList)) {
            $issues[] = [
                'severity' => count($overdueList) > 10 ? 'critical' : 'warning',
                'title'    => Translator::t('snapshot_report.cron.overdue.title', ['count' => count($overdueList)]),
                'action'   => $wpCronDisabled
                    ? Translator::t('snapshot_report.cron.overdue.action_disabled')
                    : Translator::t('snapshot_report.cron.overdue.action_low_traffic'),
            ];
        }
        // Detectar hooks con abuso (misma tarea registrada muchas veces)
        foreach ($byHook as $hook => $count) {
            if ($count >= 10) {
                $issues[] = [
                    'severity' => 'info',
                    'title'    => Translator::t('snapshot_report.cron.hook_abuse.title', ['hook' => $hook, 'count' => $count]),
                    'action'   => Translator::t('snapshot_report.cron.hook_abuse.action'),
                ];
                break; // solo reportar el peor
            }
        }

        return [
            'summary' => [
                'total'           => (int) ($cron['total_events'] ?? count($events)),
                'overdue'         => count($overdueList),
                'wpCronDisabled'  => $wpCronDisabled,
                'alternateCron'   => (bool) ($cron['alternate_cron'] ?? false),
                'uniqueHooks'     => count($byHook),
            ],
            'topHooks' => $topHooks,
            'overdue'  => $overdueItems,
            'upcoming' => $upcoming,
            'issues'   => $issues,
        ];
    }

    // ——— Media: tamaño + mime breakdown ——————————————————————————

    private function buildMedia(): array {
        $media = $this->section('media');
        // El plugin expone mime_summary como {group: count} (enteros), no objetos
        $summary = $media['mime_summary'] ?? [];
        $byType = [];
        foreach ($summary as $group => $value) {
            $count = is_array($value) ? (int) ($value['count'] ?? 0) : (int) $value;
            if ($count === 0) continue;
            $byType[] = ['group' => $group, 'count' => $count];
        }
        usort($byType, fn($a, $b) => $b['count'] <=> $a['count']);

        // mime_breakdown es {mime: count} (enteros)
        $breakdown = $media['mime_breakdown'] ?? [];
        $mimeDetail = [];
        foreach ($breakdown as $mime => $value) {
            $count = is_array($value) ? (int) ($value['count'] ?? 0) : (int) $value;
            if ($count === 0) continue;
            $mimeDetail[] = ['mime' => $mime, 'count' => $count];
        }
        usort($mimeDetail, fn($a, $b) => $b['count'] <=> $a['count']);

        $size = (int) ($media['upload_dir_size'] ?? 0);
        $gb = $size / (1024 * 1024 * 1024);

        $issues = [];
        // WebP adoption
        $hasWebP = false;
        foreach ($mimeDetail as $m) {
            if (stripos($m['mime'], 'webp') !== false) { $hasWebP = true; break; }
        }
        $hasJpegOrPng = false;
        foreach ($mimeDetail as $m) {
            if (preg_match('#image/(jpeg|jpg|png)#i', $m['mime'])) { $hasJpegOrPng = true; break; }
        }
        if ($hasJpegOrPng && !$hasWebP) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.media.no_webp.title'),
                'action'   => Translator::t('snapshot_report.media.no_webp.action'),
            ];
        }
        if ($gb > 3) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.media.heavy.title', ['size' => $media['upload_dir_size_human'] ?? '?']),
                'action'   => Translator::t('snapshot_report.media.heavy.action'),
            ];
        }

        return [
            'summary' => [
                'totalAttachments' => (int) ($media['total_attachments'] ?? 0),
                'sizeBytes'        => $size,
                'sizeHuman'        => $media['upload_dir_size_human'] ?? '',
                'uploadPath'       => $media['upload_path'] ?? '',
                'uploadUrl'        => $media['upload_url'] ?? '',
            ],
            'byType'     => $byType,          // group (image/video/audio/document...)
            'mimeDetail' => $mimeDetail,      // mime específico
            'issues'     => $issues,
        ];
    }

    // ——— Users: roles y admins ——————————————————————————————————

    private function buildUsers(): array {
        $users = $this->section('users');
        $roles = $users['roles'] ?? [];

        $roleList = [];
        $admins = 0;
        foreach ($roles as $r) {
            $count = (int) ($r['user_count'] ?? 0);
            if ($count === 0) continue;
            $slug = $r['slug'] ?? '';
            if ($slug === 'administrator') $admins = $count;
            $roleList[] = [
                'slug'      => $slug,
                'name'      => $r['name'] ?? $slug,
                'userCount' => $count,
                'capCount'  => (int) ($r['cap_count'] ?? 0),
            ];
        }
        usort($roleList, fn($a, $b) => $b['userCount'] <=> $a['userCount']);

        $issues = [];
        if ($admins > 3) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => Translator::t('snapshot_report.users.too_many_admins.title', ['count' => $admins]),
                'action'   => Translator::t('snapshot_report.users.too_many_admins.action'),
            ];
        } elseif ($admins === 0) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.users.no_admins.title'),
                'action'   => Translator::t('snapshot_report.users.no_admins.action'),
            ];
        }

        $totalUsers = (int) ($users['total_users'] ?? 0);
        if ($totalUsers > 1000) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.users.many_users.title', ['count' => $totalUsers]),
                'action'   => Translator::t('snapshot_report.users.many_users.action'),
            ];
        }

        return [
            'summary' => [
                'totalUsers'     => $totalUsers,
                'administrators' => $admins,
                'uniqueRoles'    => count($roleList),
            ],
            'roles'  => $roleList,
            'issues' => $issues,
        ];
    }

    // ——— Content: post types, taxonomies, REST API ———————————————

    private function buildContent(): array {
        $pt = $this->section('post_types');
        $tx = $this->section('taxonomies');
        $rest = $this->section('rest_api');

        $postTypes = $pt['post_types'] ?? [];
        $customTypes = array_values(array_filter($postTypes, fn($p) => !($p['is_builtin'] ?? true)));
        $ptOut = array_map(fn($p) => [
            'slug'        => $p['slug'] ?? '',
            'label'       => $p['label'] ?? '',
            'isBuiltin'   => (bool) ($p['is_builtin'] ?? false),
            'isPublic'    => (bool) ($p['is_public'] ?? false),
            'hasArchive'  => (bool) ($p['has_archive'] ?? false),
            'hierarchical' => (bool) ($p['hierarchical'] ?? false),
            'showInRest'  => (bool) ($p['show_in_rest'] ?? false),
            'restBase'    => $p['rest_base'] ?? '',
        ], $postTypes);
        // Non-builtin primero
        usort($ptOut, fn($a, $b) => [$a['isBuiltin'], strtolower($a['label'])] <=> [$b['isBuiltin'], strtolower($b['label'])]);

        $taxonomies = $tx['taxonomies'] ?? [];
        $txOut = array_map(fn($t) => [
            'slug'        => $t['slug'] ?? '',
            'label'       => $t['label'] ?? '',
            'isBuiltin'   => (bool) ($t['is_builtin'] ?? false),
            'isPublic'    => (bool) ($t['is_public'] ?? false),
            'hierarchical' => (bool) ($t['hierarchical'] ?? false),
            'showInRest'  => (bool) ($t['show_in_rest'] ?? false),
        ], $taxonomies);
        usort($txOut, fn($a, $b) => [$a['isBuiltin'], strtolower($a['label'])] <=> [$b['isBuiltin'], strtolower($b['label'])]);

        // REST API: top namespaces por nº rutas
        $byNs = $rest['by_namespace'] ?? [];
        $topNs = [];
        foreach ($byNs as $ns => $info) {
            $routes = is_array($info) ? (int) ($info['count'] ?? count($info)) : (int) $info;
            $topNs[] = ['namespace' => $ns, 'routes' => $routes];
        }
        usort($topNs, fn($a, $b) => $b['routes'] <=> $a['routes']);
        $topNs = array_slice($topNs, 0, 15);

        $issues = [];
        $totalRestRoutes = (int) ($rest['total_routes'] ?? 0);
        if ($totalRestRoutes > 800) {
            $issues[] = [
                'severity' => 'info',
                'title'    => Translator::t('snapshot_report.content.rest_bloat.title', ['count' => $totalRestRoutes]),
                'action'   => Translator::t('snapshot_report.content.rest_bloat.action'),
            ];
        }
        // Exposed-in-REST + public CPTs (potencial leak de datos)
        foreach ($customTypes as $p) {
            if (($p['show_in_rest'] ?? false) && ($p['is_public'] ?? false) && !($p['hierarchical'] ?? false)) {
                // Solo uno de ejemplo, no spammear
                $issues[] = [
                    'severity' => 'info',
                    'title'    => Translator::t('snapshot_report.content.cpt_rest_exposed.title', ['slug' => $p['slug'] ?? '']),
                    'action'   => Translator::t('snapshot_report.content.cpt_rest_exposed.action'),
                ];
                break;
            }
        }

        return [
            'summary' => [
                'totalPostTypes'   => (int) ($pt['total_post_types'] ?? count($postTypes)),
                'customPostTypes'  => (int) ($pt['custom_count'] ?? count($customTypes)),
                'totalTaxonomies'  => (int) ($tx['total_taxonomies'] ?? count($taxonomies)),
                'customTaxonomies' => (int) ($tx['custom_count'] ?? 0),
                'totalRestRoutes'  => $totalRestRoutes,
                'restNamespaces'   => count($rest['namespaces'] ?? []),
            ],
            'postTypes'    => $ptOut,
            'taxonomies'   => $txOut,
            'topRestNs'    => $topNs,
            'issues'       => $issues,
        ];
    }
}
