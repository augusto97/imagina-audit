<?php
/**
 * Checks del snapshot sobre plugins y temas.
 *
 * Sub-checker de WpSnapshotAnalyzer. Lee la estructura real del plugin
 * wp-snapshot: cada plugin tiene { file, name, version, is_active,
 * has_update, update_version, auto_update, requires_wp, requires_php,
 * network_active, ... }.
 */

class WpSnapshotExtensionsChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzePluginsOutdated(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $total = $plugins['total_plugins'] ?? 0;
        $updateAvailable = $plugins['update_available'] ?? 0;
        $list = $plugins['plugins'] ?? [];

        $outdated = [];
        foreach ($list as $p) {
            if (!($p['has_update'] ?? false)) continue;
            $outdated[] = [
                'name' => $p['name'] ?? $p['file'] ?? '?',
                'slug' => $this->slugFromFile($p['file'] ?? ''),
                'current' => $p['version'] ?? '?',
                'update' => $p['update_version'] ?? '?',
                'active' => (bool) ($p['is_active'] ?? false),
                'autoUpdate' => (bool) ($p['auto_update'] ?? false),
                'author' => $p['author'] ?? '',
            ];
        }

        // Score: cada plugin pendiente resta. Los activos pesan más que inactivos.
        $activeOutdated = count(array_filter($outdated, fn($p) => $p['active']));
        $score = Scoring::clamp(100 - ($activeOutdated * 10) - ((count($outdated) - $activeOutdated) * 4));

        $outdatedCount = count($outdated);
        return Scoring::createMetric(
            'plugins_outdated',
            Translator::t('wp_snapshot.pluginsout.name'),
            $outdatedCount,
            $outdatedCount === 0
                ? Translator::t('wp_snapshot.pluginsout.display.ok', ['total' => $total])
                : Translator::t('wp_snapshot.pluginsout.display.bad', ['outdated' => $outdatedCount, 'total' => $total]),
            $score,
            $outdatedCount === 0
                ? Translator::t('wp_snapshot.pluginsout.desc.ok', ['total' => $total])
                : Translator::t('wp_snapshot.pluginsout.desc.bad', ['outdated' => $outdatedCount, 'active' => $activeOutdated]),
            $outdatedCount > 0 ? Translator::t('wp_snapshot.pluginsout.recommend') : '',
            Translator::t('wp_snapshot.pluginsout.solution'),
            ['total' => $total, 'updateAvailable' => $updateAvailable, 'outdated' => $outdated]
        );
    }

    public function analyzePluginsInactive(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $list = $plugins['plugins'] ?? [];
        $inactive = array_values(array_filter($list, fn($p) => !($p['is_active'] ?? false)));
        $count = count($inactive);

        $inactiveDetails = array_map(fn($p) => [
            'name' => $p['name'] ?? $p['file'] ?? '?',
            'slug' => $this->slugFromFile($p['file'] ?? ''),
            'version' => $p['version'] ?? '?',
            'author' => $p['author'] ?? '',
            'hasUpdate' => (bool) ($p['has_update'] ?? false),
        ], $inactive);

        $score = $count === 0 ? 100 : ($count <= 2 ? 85 : ($count <= 5 ? 60 : 30));

        return Scoring::createMetric(
            'plugins_inactive',
            Translator::t('wp_snapshot.pluginsinact.name'),
            $count,
            $count === 0 ? Translator::t('wp_snapshot.pluginsinact.display.ok') : Translator::t('wp_snapshot.pluginsinact.display.bad', ['count' => $count]),
            $score,
            $count === 0
                ? Translator::t('wp_snapshot.pluginsinact.desc.ok')
                : Translator::t('wp_snapshot.pluginsinact.desc.bad', ['count' => $count]),
            $count > 0 ? Translator::t('wp_snapshot.pluginsinact.recommend') : '',
            Translator::t('wp_snapshot.pluginsinact.solution'),
            ['count' => $count, 'list' => $inactiveDetails]
        );
    }

    public function analyzePluginOverload(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $active = $plugins['active_count'] ?? 0;
        if ($active < 15) return null;

        $score = $active <= 20 ? 80 : ($active <= 30 ? 60 : ($active <= 40 ? 40 : 20));

        return Scoring::createMetric(
            'plugin_overload',
            Translator::t('wp_snapshot.overload.name'),
            $active,
            Translator::t('wp_snapshot.overload.display', ['count' => $active]),
            $score,
            Translator::t('wp_snapshot.overload.desc', ['count' => $active]),
            $active > 30
                ? Translator::t('wp_snapshot.overload.recommend.heavy')
                : Translator::t('wp_snapshot.overload.recommend.normal'),
            Translator::t('wp_snapshot.overload.solution'),
            ['activeCount' => $active, 'total' => $plugins['total_plugins'] ?? 0]
        );
    }

    public function analyzeAutoUpdatePlugins(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $list = $plugins['plugins'] ?? [];
        $activeList = array_values(array_filter($list, fn($p) => $p['is_active'] ?? false));
        $total = count($activeList);
        if ($total === 0) return null;

        $withAutoUpdate = count(array_filter($activeList, fn($p) => $p['auto_update'] ?? false));
        $pct = $total > 0 ? ($withAutoUpdate / $total) * 100 : 0;

        $score = $pct >= 80 ? 100 : ($pct >= 50 ? 75 : ($pct >= 20 ? 55 : 35));

        $withoutAutoUpdate = array_values(array_filter($activeList, fn($p) => !($p['auto_update'] ?? false)));
        $withoutAutoUpdateList = array_map(fn($p) => $p['name'] ?? '?', $withoutAutoUpdate);

        $params = ['withAuto' => $withAutoUpdate, 'total' => $total, 'pct' => round($pct)];
        return Scoring::createMetric(
            'plugins_auto_update',
            Translator::t('wp_snapshot.pluginsauto.name'),
            $withAutoUpdate,
            Translator::t('wp_snapshot.pluginsauto.display', $params),
            $score,
            Translator::t('wp_snapshot.pluginsauto.desc.prefix', $params)
                . ($pct >= 80 ? Translator::t('wp_snapshot.pluginsauto.desc.good') : Translator::t('wp_snapshot.pluginsauto.desc.bad')),
            $pct < 80 ? Translator::t('wp_snapshot.pluginsauto.recommend') : '',
            Translator::t('wp_snapshot.pluginsauto.solution'),
            ['withAutoUpdate' => $withAutoUpdate, 'total' => $total, 'withoutAutoUpdate' => array_slice($withoutAutoUpdateList, 0, 15)]
        );
    }

    public function analyzeMuPluginsDropins(): ?array {
        $plugins = $this->getSection('plugins');
        if (empty($plugins)) return null;

        $mu = $plugins['mu_plugins'] ?? [];
        $dropins = $plugins['dropins'] ?? [];
        $total = count($mu) + count($dropins);
        if ($total === 0) return null;

        // Los mu-plugins son interesantes para el admin pero no afectan score
        $items = [];
        foreach ($mu as $m) $items[] = ['type' => 'mu-plugin', 'name' => $m['name'] ?? $m['file'] ?? '?', 'version' => $m['version'] ?? '', 'author' => $m['author'] ?? ''];
        foreach ($dropins as $d) $items[] = ['type' => 'dropin', 'name' => $d['name'] ?? $d['file'] ?? '?', 'version' => $d['version'] ?? '', 'author' => $d['author'] ?? ''];

        return Scoring::createMetric(
            'mu_plugins_dropins',
            Translator::t('wp_snapshot.mudrop.name'),
            $total,
            Translator::t('wp_snapshot.mudrop.display', ['mu' => count($mu), 'drop' => count($dropins)]),
            null,
            Translator::t('wp_snapshot.mudrop.desc', ['total' => $total]),
            Translator::t('wp_snapshot.mudrop.recommend'),
            Translator::t('wp_snapshot.mudrop.solution'),
            ['mu' => $mu, 'dropins' => $dropins, 'items' => $items]
        );
    }

    public function analyzeTheme(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $active = $themes['active_theme'] ?? [];
        if (empty($active)) return null;

        $name = $active['name'] ?? Translator::t('wp_snapshot.theme.unknown');
        $version = $active['version'] ?? '';
        $hasUpdate = (bool) ($active['has_update'] ?? false);
        $isChild = (bool) ($active['is_child_theme'] ?? false);
        $isBlock = (bool) ($active['is_block_theme'] ?? false);
        $author = $active['author'] ?? '';
        $parent = $active['parent_theme'] ?? null;

        $score = 100;
        if ($hasUpdate) { $score -= 30; }
        if (!$isChild)  { $score -= 20; }

        $childSuffix = $isChild ? Translator::t('wp_snapshot.theme.display.child') : '';
        $updateNote = $hasUpdate ? Translator::t('wp_snapshot.theme.desc.update_note') : '';

        return Scoring::createMetric(
            'theme_active',
            Translator::t('wp_snapshot.theme.name'),
            $name,
            Translator::t('wp_snapshot.theme.display', ['name' => $name, 'version' => $version, 'childSuffix' => $childSuffix]),
            Scoring::clamp($score),
            $isChild
                ? Translator::t('wp_snapshot.theme.desc.child', ['parent' => $parent])
                : Translator::t('wp_snapshot.theme.desc.no_child', ['name' => $name, 'updateNote' => $updateNote]),
            !$isChild
                ? Translator::t('wp_snapshot.theme.recommend.no_child', ['slug' => strtolower($name)])
                : ($hasUpdate ? Translator::t('wp_snapshot.theme.recommend.update') : ''),
            Translator::t('wp_snapshot.theme.solution'),
            ['name' => $name, 'version' => $version, 'author' => $author, 'isChild' => $isChild, 'parent' => $parent, 'isBlockTheme' => $isBlock, 'hasUpdate' => $hasUpdate]
        );
    }

    public function analyzeInactiveThemes(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $total = $themes['total_themes'] ?? 0;
        $installed = $themes['installed'] ?? [];
        $inactiveList = array_values(array_filter($installed, fn($t) => empty($t['is_active'])));
        $count = count($inactiveList);

        if ($count <= 1) return null; // 1 inactivo como fallback está bien

        $score = $count <= 2 ? 80 : ($count <= 4 ? 60 : 30);

        $inactiveNames = array_map(fn($t) => [
            'name' => $t['name'] ?? '?',
            'version' => $t['version'] ?? '',
            'hasUpdate' => (bool) ($t['has_update'] ?? false),
        ], $inactiveList);

        return Scoring::createMetric(
            'themes_inactive',
            Translator::t('wp_snapshot.themesinact.name'),
            $count,
            Translator::t('wp_snapshot.themesinact.display', ['count' => $count, 'total' => $total]),
            $score,
            Translator::t('wp_snapshot.themesinact.desc', ['count' => $count]),
            Translator::t('wp_snapshot.themesinact.recommend'),
            Translator::t('wp_snapshot.themesinact.solution'),
            ['count' => $count, 'inactive' => $inactiveNames]
        );
    }

    private function slugFromFile(string $file): string {
        if ($file === '') return '';
        $parts = explode('/', $file);
        return $parts[0] ?? '';
    }
}
