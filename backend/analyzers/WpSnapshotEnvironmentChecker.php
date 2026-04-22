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
            $issues[] = Translator::t('wp_snapshot.php.issue.eol', ['version' => $phpVersion]);
            $score -= 40;
        } elseif (version_compare($phpVersion, '8.1', '<')) {
            $issues[] = Translator::t('wp_snapshot.php.issue.outdated', ['version' => $phpVersion]);
            $score -= 10;
        }

        $memBytes = $this->parseSize($memLimit);
        if ($memBytes > 0 && $memBytes < 256 * 1024 * 1024) {
            $issues[] = Translator::t('wp_snapshot.php.issue.low_memory', ['value' => $memLimit]);
            $score -= 15;
        }

        $wpMemBytes = $this->parseSize($wpMemory);
        if ($wpMemBytes > 0 && $wpMemBytes < 64 * 1024 * 1024) {
            $issues[] = Translator::t('wp_snapshot.php.issue.low_wpmem', ['value' => $wpMemory]);
            $score -= 10;
        }

        if ($maxExec > 0 && $maxExec < 60) {
            $issues[] = Translator::t('wp_snapshot.php.issue.low_exec', ['value' => $maxExec]);
            $score -= 5;
        }

        $requiredExts = ['curl', 'gd', 'mbstring', 'openssl', 'xml', 'zip', 'intl', 'imagick', 'fileinfo'];
        $missing = array_values(array_filter($requiredExts, fn($e) => isset($exts[$e]) && !$exts[$e]));
        if (!empty($missing)) {
            $issues[] = Translator::t('wp_snapshot.php.issue.missing_ext', ['list' => implode(', ', $missing)]);
            $score -= count($missing) * 4;
        }

        return Scoring::createMetric(
            'env_php',
            Translator::t('wp_snapshot.php.name'),
            $phpVersion,
            $phpVersion
                ? Translator::t('wp_snapshot.php.display.ok', ['version' => $phpVersion, 'memory' => $memLimit, 'exec' => $maxExec])
                : Translator::t('wp_snapshot.php.display.none'),
            Scoring::clamp($score),
            empty($issues)
                ? Translator::t('wp_snapshot.php.desc.ok', ['version' => $phpVersion, 'memory' => $memLimit, 'exec' => $maxExec])
                : Translator::t('wp_snapshot.php.desc.bad', ['issues' => implode('; ', $issues)]),
            !empty($issues) ? Translator::t('wp_snapshot.php.recommend') : '',
            Translator::t('wp_snapshot.php.solution'),
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
            if (version_compare($cleanVer, '10.3', '<'))       { $score = 30; $recommend = Translator::t('wp_snapshot.envdb.recommend.maria_eol'); }
            elseif (version_compare($cleanVer, '10.6', '<'))   { $score = 70; $recommend = Translator::t('wp_snapshot.envdb.recommend.maria_old'); }
        } else {
            if (version_compare($cleanVer, '5.7', '<'))        { $score = 20; $recommend = Translator::t('wp_snapshot.envdb.recommend.mysql_eol'); }
            elseif (version_compare($cleanVer, '8.0', '<'))    { $score = 60; $recommend = Translator::t('wp_snapshot.envdb.recommend.mysql_old'); }
        }

        return Scoring::createMetric(
            'env_database',
            Translator::t('wp_snapshot.envdb.name'),
            $dbVersion,
            Translator::t('wp_snapshot.envdb.display', ['type' => $dbType, 'version' => $dbVersion]),
            $score,
            $score >= 100
                ? Translator::t('wp_snapshot.envdb.desc.ok', ['type' => $dbType, 'version' => $dbVersion])
                : Translator::t('wp_snapshot.envdb.desc.bad', ['type' => $dbType, 'version' => $dbVersion, 'recommend' => $recommend]),
            $recommend,
            Translator::t('wp_snapshot.envdb.solution'),
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
            'wp_version_internal',
            Translator::t('wp_snapshot.wpver.name'),
            $wpVersion,
            $cmp >= 0
                ? Translator::t('wp_snapshot.wpver.display.current', ['version' => $wpVersion])
                : Translator::t('wp_snapshot.wpver.display.old', ['version' => $wpVersion, 'latest' => $latest]),
            $score,
            $cmp >= 0
                ? Translator::t('wp_snapshot.wpver.desc.current', ['version' => $wpVersion])
                : Translator::t('wp_snapshot.wpver.desc.old', ['version' => $wpVersion, 'latest' => $latest]),
            $cmp < 0 ? Translator::t('wp_snapshot.wpver.recommend', ['latest' => $latest]) : '',
            Translator::t('wp_snapshot.wpver.solution'),
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
            'env_upload',
            Translator::t('wp_snapshot.upload.name'),
            $upload,
            Translator::t('wp_snapshot.upload.display', ['upload' => $upload, 'post' => $post]),
            $score,
            $mb < 32
                ? Translator::t('wp_snapshot.upload.desc.bad', ['upload' => $upload, 'post' => $post])
                : Translator::t('wp_snapshot.upload.desc.ok', ['upload' => $upload, 'post' => $post]),
            $mb < 32 ? Translator::t('wp_snapshot.upload.recommend') : '',
            Translator::t('wp_snapshot.upload.solution'),
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
            'wp_debug',
            Translator::t('wp_snapshot.wpdebug.name'),
            $debug,
            $isCritical
                ? Translator::t('wp_snapshot.wpdebug.display.critical')
                : ($debug ? Translator::t('wp_snapshot.wpdebug.display.warning') : Translator::t('wp_snapshot.wpdebug.display.off')),
            $isCritical ? 10 : ($isWarning ? 70 : 100),
            $isCritical
                ? Translator::t('wp_snapshot.wpdebug.desc.critical')
                : ($isWarning
                    ? Translator::t('wp_snapshot.wpdebug.desc.warning')
                    : Translator::t('wp_snapshot.wpdebug.desc.off')),
            $isCritical
                ? Translator::t('wp_snapshot.wpdebug.recommend.critical')
                : ($isWarning ? Translator::t('wp_snapshot.wpdebug.recommend.warning') : ''),
            Translator::t('wp_snapshot.wpdebug.solution'),
            ['debug' => $debug, 'display' => $display, 'log' => $log]
        );
    }

    public function analyzeFileEditing(): ?array {
        $check = $this->secCheck('file_editing');
        $disallow = $check['value'] ?? false;

        return Scoring::createMetric(
            'file_editing',
            Translator::t('wp_snapshot.fileedit.name'),
            $disallow,
            $disallow ? Translator::t('wp_snapshot.fileedit.display.blocked') : Translator::t('wp_snapshot.fileedit.display.enabled'),
            $disallow ? 100 : 60,
            $disallow ? Translator::t('wp_snapshot.fileedit.desc.blocked') : Translator::t('wp_snapshot.fileedit.desc.enabled'),
            !$disallow ? Translator::t('wp_snapshot.fileedit.recommend') : '',
            Translator::t('wp_snapshot.fileedit.solution'),
            ['disallow_file_edit' => $disallow]
        );
    }

    public function analyzeXmlRpc(): ?array {
        $check = $this->secCheck('xmlrpc');
        if ($check === null) return null;

        $xmlEnabled = $check['value'] ?? false;
        return Scoring::createMetric(
            'xmlrpc_status',
            Translator::t('wp_snapshot.xmlrpc.name'),
            $xmlEnabled,
            $xmlEnabled ? Translator::t('wp_snapshot.xmlrpc.display.active') : Translator::t('wp_snapshot.xmlrpc.display.inactive'),
            $xmlEnabled ? 50 : 100,
            $xmlEnabled ? Translator::t('wp_snapshot.xmlrpc.desc.active') : Translator::t('wp_snapshot.xmlrpc.desc.inactive'),
            $xmlEnabled ? Translator::t('wp_snapshot.xmlrpc.recommend') : '',
            Translator::t('wp_snapshot.xmlrpc.solution'),
            ['enabled' => $xmlEnabled]
        );
    }

    public function analyzeAutoUpdates(): ?array {
        $check = $this->secCheck('auto_updates_core');
        if ($check === null) return null;

        $enabled = $check['value'] ?? false;
        return Scoring::createMetric(
            'core_auto_updates',
            Translator::t('wp_snapshot.autoupd.name'),
            $enabled,
            $enabled ? Translator::t('wp_snapshot.autoupd.display.enabled') : Translator::t('wp_snapshot.autoupd.display.manual'),
            $enabled ? 100 : 70,
            $enabled ? Translator::t('wp_snapshot.autoupd.desc.enabled') : Translator::t('wp_snapshot.autoupd.desc.manual'),
            !$enabled ? Translator::t('wp_snapshot.autoupd.recommend') : '',
            Translator::t('wp_snapshot.autoupd.solution'),
            ['enabled' => $enabled]
        );
    }

    public function analyzeDbPrefix(): ?array {
        $check = $this->secCheck('db_prefix');
        if ($check === null) return null;

        $isCustom = $check['value'] ?? false;
        $note = $check['note'] ?? '';

        return Scoring::createMetric(
            'db_prefix_status',
            Translator::t('wp_snapshot.dbprefix.name'),
            $isCustom,
            $isCustom ? Translator::t('wp_snapshot.dbprefix.display.custom') : Translator::t('wp_snapshot.dbprefix.display.default'),
            $isCustom ? 100 : 70,
            $isCustom
                ? Translator::t('wp_snapshot.dbprefix.desc.custom', ['note' => $note])
                : Translator::t('wp_snapshot.dbprefix.desc.default'),
            !$isCustom ? Translator::t('wp_snapshot.dbprefix.recommend') : '',
            Translator::t('wp_snapshot.dbprefix.solution'),
            ['isCustom' => $isCustom]
        );
    }

    public function analyzeSsl(): ?array {
        $check = $this->secCheck('ssl');
        if ($check === null) return null;

        $active = $check['value'] ?? false;
        return Scoring::createMetric(
            'ssl_internal',
            Translator::t('wp_snapshot.sslint.name'),
            $active,
            $active ? Translator::t('wp_snapshot.sslint.display.on') : Translator::t('wp_snapshot.sslint.display.off'),
            $active ? 100 : 20,
            $active ? Translator::t('wp_snapshot.sslint.desc.on') : Translator::t('wp_snapshot.sslint.desc.off'),
            !$active ? Translator::t('wp_snapshot.sslint.recommend') : '',
            Translator::t('wp_snapshot.sslint.solution'),
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
            'object_cache',
            Translator::t('wp_snapshot.objcache.name'),
            $active,
            $active ? Translator::t('wp_snapshot.objcache.display.on', ['type' => $type]) : Translator::t('wp_snapshot.objcache.display.off'),
            $score,
            $active
                ? Translator::t('wp_snapshot.objcache.desc.on', ['type' => $type])
                : Translator::t('wp_snapshot.objcache.desc.off'),
            !$active ? Translator::t('wp_snapshot.objcache.recommend') : '',
            Translator::t('wp_snapshot.objcache.solution'),
            ['active' => $active, 'type' => $type, 'dropin' => $dropin]
        );
    }

    public function analyzePageCache(): array {
        $perf = $this->getSection('performance');
        $pageCache = $perf['page_cache_likely'] ?? false;

        return Scoring::createMetric(
            'page_cache',
            Translator::t('wp_snapshot.pagecache.name'),
            $pageCache,
            $pageCache ? Translator::t('wp_snapshot.pagecache.display.on') : Translator::t('wp_snapshot.pagecache.display.off'),
            $pageCache ? 100 : 50,
            $pageCache ? Translator::t('wp_snapshot.pagecache.desc.on') : Translator::t('wp_snapshot.pagecache.desc.off'),
            !$pageCache ? Translator::t('wp_snapshot.pagecache.recommend') : '',
            Translator::t('wp_snapshot.pagecache.solution'),
            ['detected' => $pageCache]
        );
    }

    public function analyzeOpcache(): array {
        $perf = $this->getSection('performance');
        $env = $this->getSection('environment');
        $enabled = $perf['opcache_enabled'] ?? ($env['opcache_enabled'] ?? false);

        return Scoring::createMetric(
            'opcache',
            Translator::t('wp_snapshot.opcache.name'),
            $enabled,
            $enabled ? Translator::t('wp_snapshot.opcache.display.on') : Translator::t('wp_snapshot.opcache.display.off'),
            $enabled ? 100 : 40,
            $enabled ? Translator::t('wp_snapshot.opcache.desc.on') : Translator::t('wp_snapshot.opcache.desc.off'),
            !$enabled ? Translator::t('wp_snapshot.opcache.recommend') : '',
            Translator::t('wp_snapshot.opcache.solution'),
            ['enabled' => $enabled]
        );
    }

    public function analyzeImageEditor(): array {
        $perf = $this->getSection('performance');
        $editor = $perf['image_editor'] ?? 'default';

        $isImagick = stripos($editor, 'imagick') !== false;
        $score = $isImagick ? 100 : 70;

        return Scoring::createMetric(
            'image_editor',
            Translator::t('wp_snapshot.imgedit.name'),
            $editor,
            Translator::t('wp_snapshot.imgedit.display', ['editor' => $editor]),
            $score,
            $isImagick
                ? Translator::t('wp_snapshot.imgedit.desc.imagick', ['editor' => $editor])
                : Translator::t('wp_snapshot.imgedit.desc.gd', ['editor' => $editor]),
            !$isImagick ? Translator::t('wp_snapshot.imgedit.recommend') : '',
            Translator::t('wp_snapshot.imgedit.solution'),
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
            'permalinks',
            Translator::t('wp_snapshot.perm.name'),
            $permalink,
            $isDefault ? Translator::t('wp_snapshot.perm.display.default') : Translator::t('wp_snapshot.perm.display.custom', ['structure' => $permalink]),
            $isDefault ? 40 : 100,
            $isDefault
                ? Translator::t('wp_snapshot.perm.desc.default')
                : Translator::t('wp_snapshot.perm.desc.custom', ['structure' => $permalink]),
            $isDefault ? Translator::t('wp_snapshot.perm.recommend') : '',
            Translator::t('wp_snapshot.perm.solution'),
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
