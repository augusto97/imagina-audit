<?php
/**
 * Checks del snapshot sobre base de datos, contenido acumulado, cron y medios.
 *
 * Sub-checker de WpSnapshotAnalyzer. Lee la estructura real de
 * sections.database.data, sections.cron.data y sections.media.data.
 */

class WpSnapshotDatabaseChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeDbSize(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $totalSize = (int) ($db['total_db_size'] ?? 0);
        $humanSize = $db['total_db_size_human'] ?? '?';
        $totalRows = (int) ($db['total_rows'] ?? 0);
        $totalTables = (int) ($db['total_tables'] ?? 0);

        if ($totalSize === 0) return null;

        $mb = $totalSize / (1024 * 1024);
        $score = $mb < 200 ? 100 : ($mb < 500 ? 85 : ($mb < 1500 ? 60 : 30));

        // Top 5 tablas por tamaño
        $tables = $db['tables'] ?? [];
        $sorted = $tables;
        usort($sorted, fn($a, $b) => ($b['total_size'] ?? 0) <=> ($a['total_size'] ?? 0));
        $topTables = array_map(fn($t) => [
            'name' => $t['name'] ?? '?',
            'rows' => (int) ($t['rows'] ?? 0),
            'sizeMb' => round(((int) ($t['total_size'] ?? 0)) / (1024 * 1024), 1),
            'engine' => $t['engine'] ?? '',
        ], array_slice($sorted, 0, 10));

        $label = $mb > 1500
            ? Translator::t('wp_snapshot.dbsize.label.critical')
            : Translator::t('wp_snapshot.dbsize.label.large');
        return Scoring::createMetric(
            'db_size',
            Translator::t('wp_snapshot.dbsize.name'),
            $humanSize,
            Translator::t('wp_snapshot.dbsize.display', ['size' => $humanSize, 'rows' => $totalRows, 'tables' => $totalTables]),
            $score,
            $mb < 200
                ? Translator::t('wp_snapshot.dbsize.desc.ok', ['size' => $humanSize, 'rows' => $totalRows, 'tables' => $totalTables])
                : Translator::t('wp_snapshot.dbsize.desc.heavy', ['size' => $humanSize, 'label' => $label]),
            $mb > 500 ? Translator::t('wp_snapshot.dbsize.recommend') : '',
            Translator::t('wp_snapshot.dbsize.solution'),
            ['totalSize' => $totalSize, 'humanSize' => $humanSize, 'totalRows' => $totalRows, 'totalTables' => $totalTables, 'topTables' => $topTables]
        );
    }

    public function analyzeAutoload(): ?array {
        $db = $this->getSection('database');
        if (empty($db)) return null;

        $autoloadSize = (int) ($db['autoload_size'] ?? 0);
        $autoloadHuman = $db['autoload_size_human'] ?? '?';
        $count = (int) ($db['autoloaded_options'] ?? 0);
        if ($autoloadSize === 0) return null;

        $mb = $autoloadSize / (1024 * 1024);
        $score = $mb < 0.5 ? 100 : ($mb < 1 ? 85 : ($mb < 3 ? 55 : 20));

        return Scoring::createMetric(
            'db_autoload',
            Translator::t('wp_snapshot.dbautoload.name'),
            $autoloadHuman,
            Translator::t('wp_snapshot.dbautoload.display', ['size' => $autoloadHuman, 'count' => $count]),
            $score,
            $mb < 0.5
                ? Translator::t('wp_snapshot.dbautoload.desc.ok', ['size' => $autoloadHuman, 'count' => $count])
                : Translator::t('wp_snapshot.dbautoload.desc.bad', ['size' => $autoloadHuman, 'count' => $count]),
            $mb > 1 ? Translator::t('wp_snapshot.dbautoload.recommend') : '',
            Translator::t('wp_snapshot.dbautoload.solution'),
            ['size' => $autoloadSize, 'human' => $autoloadHuman, 'count' => $count]
        );
    }

    public function analyzeDbEngine(): ?array {
        $db = $this->getSection('database');
        $tables = $db['tables'] ?? [];
        if (empty($tables)) return null;

        $myisam = [];
        foreach ($tables as $t) {
            $engine = strtolower((string) ($t['engine'] ?? ''));
            if ($engine === 'myisam') {
                $myisam[] = [
                    'name' => $t['name'] ?? '?',
                    'rows' => (int) ($t['rows'] ?? 0),
                    'sizeMb' => round(((int) ($t['total_size'] ?? 0)) / (1024 * 1024), 1),
                ];
            }
        }
        if (empty($myisam)) return null;

        $count = count($myisam);
        $score = $count <= 2 ? 75 : ($count <= 10 ? 55 : 35);

        return Scoring::createMetric(
            'db_engine',
            Translator::t('wp_snapshot.dbengine.name'),
            $count,
            Translator::t('wp_snapshot.dbengine.display', ['count' => $count]),
            $score,
            Translator::t('wp_snapshot.dbengine.desc', ['count' => $count]),
            Translator::t('wp_snapshot.dbengine.recommend'),
            Translator::t('wp_snapshot.dbengine.solution'),
            ['count' => $count, 'tables' => array_slice($myisam, 0, 15)]
        );
    }

    public function analyzeRevisions(): ?array {
        $db = $this->getSection('database');
        $revisions = (int) ($db['revisions_count'] ?? 0);
        if ($revisions === 0) return null;

        $score = $revisions < 100 ? 100 : ($revisions < 500 ? 90 : ($revisions < 2000 ? 65 : 30));

        return Scoring::createMetric(
            'db_revisions',
            Translator::t('wp_snapshot.dbrev.name'),
            $revisions,
            Translator::t('wp_snapshot.dbrev.display', ['count' => $revisions]),
            $score,
            $revisions < 100
                ? Translator::t('wp_snapshot.dbrev.desc.ok', ['count' => $revisions])
                : Translator::t('wp_snapshot.dbrev.desc.bad', ['count' => $revisions]),
            $revisions > 500 ? Translator::t('wp_snapshot.dbrev.recommend') : '',
            Translator::t('wp_snapshot.dbrev.solution'),
            ['count' => $revisions]
        );
    }

    public function analyzeTransients(): ?array {
        $db = $this->getSection('database');
        $t = (int) ($db['transients_count'] ?? 0);
        if ($t === 0) return null;

        $score = $t < 300 ? 100 : ($t < 1000 ? 85 : ($t < 5000 ? 50 : 25));

        return Scoring::createMetric(
            'db_transients',
            Translator::t('wp_snapshot.dbtrans.name'),
            $t,
            Translator::t('wp_snapshot.dbtrans.display', ['count' => $t]),
            $score,
            $t < 300
                ? Translator::t('wp_snapshot.dbtrans.desc.ok', ['count' => $t])
                : Translator::t('wp_snapshot.dbtrans.desc.bad', ['count' => $t]),
            $t > 1000 ? Translator::t('wp_snapshot.dbtrans.recommend') : '',
            Translator::t('wp_snapshot.dbtrans.solution'),
            ['count' => $t]
        );
    }

    public function analyzeOrphanedMeta(): ?array {
        $db = $this->getSection('database');
        $orphaned = (int) ($db['orphaned_postmeta'] ?? 0);
        if ($orphaned === 0) return null;

        $score = $orphaned < 100 ? 80 : ($orphaned < 1000 ? 55 : 25);

        return Scoring::createMetric(
            'db_orphaned_meta',
            Translator::t('wp_snapshot.dbmeta.name'),
            $orphaned,
            Translator::t('wp_snapshot.dbmeta.display', ['count' => $orphaned]),
            $score,
            Translator::t('wp_snapshot.dbmeta.desc', ['count' => $orphaned]),
            Translator::t('wp_snapshot.dbmeta.recommend'),
            Translator::t('wp_snapshot.dbmeta.solution'),
            ['count' => $orphaned]
        );
    }

    public function analyzeCron(): ?array {
        $cron = $this->getSection('cron');
        if (empty($cron)) return null;

        $total = (int) ($cron['total_events'] ?? 0);
        $overdue = (int) ($cron['overdue_count'] ?? 0);
        $wpCronDisabled = (bool) ($cron['wp_cron_disabled'] ?? false);
        $alternate = (bool) ($cron['alternate_cron'] ?? false);

        $score = $overdue === 0 ? 100 : ($overdue < 5 ? 70 : ($overdue < 20 ? 50 : 25));

        // Tareas próximas (nombres de hooks)
        $events = $cron['events'] ?? [];
        $upcomingHooks = [];
        $seen = [];
        foreach ($events as $e) {
            $hook = $e['hook'] ?? '';
            if ($hook === '' || isset($seen[$hook])) continue;
            $seen[$hook] = true;
            $upcomingHooks[] = $hook;
            if (count($upcomingHooks) >= 20) break;
        }

        $okSuffix = $wpCronDisabled ? Translator::t('wp_snapshot.cron.desc.ok_no_wpcron') : '';
        return Scoring::createMetric(
            'cron_status',
            Translator::t('wp_snapshot.cron.name'),
            $overdue,
            $overdue === 0
                ? Translator::t('wp_snapshot.cron.display.ok', ['total' => $total])
                : Translator::t('wp_snapshot.cron.display.overdue', ['overdue' => $overdue, 'total' => $total]),
            $score,
            $overdue === 0
                ? Translator::t('wp_snapshot.cron.desc.ok', ['total' => $total]) . $okSuffix
                : Translator::t('wp_snapshot.cron.desc.overdue', ['overdue' => $overdue, 'total' => $total]),
            $overdue > 0
                ? ($wpCronDisabled
                    ? Translator::t('wp_snapshot.cron.recommend.no_wpcron')
                    : Translator::t('wp_snapshot.cron.recommend.low_traf'))
                : '',
            Translator::t('wp_snapshot.cron.solution'),
            ['total' => $total, 'overdue' => $overdue, 'wpCronDisabled' => $wpCronDisabled, 'alternate' => $alternate, 'hooks' => $upcomingHooks]
        );
    }

    public function analyzeMedia(): ?array {
        $media = $this->getSection('media');
        if (empty($media)) return null;

        $count = (int) ($media['total_attachments'] ?? 0);
        $size = (int) ($media['upload_dir_size'] ?? 0);
        $humanSize = $media['upload_dir_size_human'] ?? '?';
        $mimeSummary = $media['mime_summary'] ?? [];

        if ($count === 0 && $size === 0) return null;

        $gb = $size / (1024 * 1024 * 1024);
        $score = $gb < 1 ? 100 : ($gb < 5 ? 80 : ($gb < 15 ? 55 : 25));

        // Detalles de mime types
        $mimeDetail = [];
        foreach ($mimeSummary as $group => $info) {
            if (is_array($info)) {
                $mimeDetail[] = [
                    'group' => $group,
                    'count' => (int) ($info['count'] ?? 0),
                    'sizeMb' => round(((int) ($info['size'] ?? 0)) / (1024 * 1024), 1),
                ];
            }
        }

        $badSuffix = $gb > 5
            ? Translator::t('wp_snapshot.media.desc.bad_heavy')
            : Translator::t('wp_snapshot.media.desc.bad_normal');
        return Scoring::createMetric(
            'media_library',
            Translator::t('wp_snapshot.media.name'),
            $count,
            Translator::t('wp_snapshot.media.display', ['count' => $count, 'size' => $humanSize]),
            $score,
            $gb < 1
                ? Translator::t('wp_snapshot.media.desc.ok', ['count' => $count, 'size' => $humanSize])
                : Translator::t('wp_snapshot.media.desc.bad_prefix', ['count' => $count, 'size' => $humanSize]) . $badSuffix,
            $gb > 1 ? Translator::t('wp_snapshot.media.recommend') : '',
            Translator::t('wp_snapshot.media.solution'),
            ['count' => $count, 'size' => $size, 'humanSize' => $humanSize, 'mimeSummary' => $mimeDetail]
        );
    }

    public function analyzePostTypes(): ?array {
        $pt = $this->getSection('post_types');
        if (empty($pt)) return null;

        $total = (int) ($pt['total_post_types'] ?? 0);
        $custom = (int) ($pt['custom_count'] ?? 0);
        if ($total === 0) return null;

        $list = $pt['post_types'] ?? [];
        $customList = array_values(array_filter($list, fn($p) => !($p['is_builtin'] ?? true)));
        $customSummary = array_map(fn($p) => [
            'slug' => $p['slug'] ?? '?',
            'label' => $p['label'] ?? '',
            'public' => (bool) ($p['is_public'] ?? false),
            'hasArchive' => (bool) ($p['has_archive'] ?? false),
            'inRest' => (bool) ($p['show_in_rest'] ?? false),
        ], array_slice($customList, 0, 15));

        return Scoring::createMetric(
            'custom_post_types',
            Translator::t('wp_snapshot.cpt.name'),
            $custom,
            Translator::t('wp_snapshot.cpt.display', ['custom' => $custom, 'total' => $total]),
            null,
            $custom === 0
                ? Translator::t('wp_snapshot.cpt.desc.none')
                : Translator::t('wp_snapshot.cpt.desc.custom', ['custom' => $custom]),
            '',
            Translator::t('wp_snapshot.cpt.solution'),
            ['total' => $total, 'custom' => $custom, 'customTypes' => $customSummary]
        );
    }

    public function analyzeRestApi(): ?array {
        $rest = $this->getSection('rest_api');
        if (empty($rest)) return null;

        $total = (int) ($rest['total_routes'] ?? 0);
        $namespaces = $rest['namespaces'] ?? [];
        $byNs = $rest['by_namespace'] ?? [];

        if ($total === 0) return null;

        // Muchas rutas = plugins que exponen mucho vía REST (normal)
        // Pero >1000 puede indicar bloat extremo
        $score = $total < 300 ? 100 : ($total < 800 ? 85 : ($total < 1500 ? 65 : 40));

        $topNamespaces = [];
        foreach ($byNs as $ns => $info) {
            $topNamespaces[] = ['namespace' => $ns, 'routes' => is_array($info) ? (int) ($info['count'] ?? count($info)) : (int) $info];
        }
        usort($topNamespaces, fn($a, $b) => $b['routes'] <=> $a['routes']);
        $topNamespaces = array_slice($topNamespaces, 0, 10);

        return Scoring::createMetric(
            'rest_api_routes',
            Translator::t('wp_snapshot.restroutes.name'),
            $total,
            Translator::t('wp_snapshot.restroutes.display', ['total' => $total, 'namespaces' => count($namespaces)]),
            $score,
            $total < 300
                ? Translator::t('wp_snapshot.restroutes.desc.ok', ['total' => $total])
                : Translator::t('wp_snapshot.restroutes.desc.bad', ['total' => $total]),
            $total > 800 ? Translator::t('wp_snapshot.restroutes.recommend') : '',
            Translator::t('wp_snapshot.restroutes.solution'),
            ['total' => $total, 'namespaces' => $namespaces, 'topNamespaces' => $topNamespaces]
        );
    }
}
