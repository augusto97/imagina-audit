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

        return Scoring::createMetric(
            'plugins_outdated', 'Plugins desactualizados',
            count($outdated),
            count($outdated) === 0
                ? "Todos los $total plugins al día"
                : count($outdated) . " de $total con actualización pendiente",
            $score,
            count($outdated) === 0
                ? "Todos los $total plugins están en su última versión."
                : count($outdated) . " plugins tienen updates disponibles ($activeOutdated activos). Los plugins desactualizados son la principal causa de sitios WordPress hackeados.",
            count($outdated) > 0 ? 'Actualizar desde WP Admin → Plugins. Hacer backup antes de actualizar plugins críticos (WooCommerce, Elementor, etc.).' : '',
            'Actualizamos todos los plugins semanalmente con testing previo de compatibilidad.',
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
            'plugins_inactive', 'Plugins inactivos',
            $count,
            $count === 0 ? 'Ninguno' : "$count plugins",
            $score,
            $count === 0
                ? 'No hay plugins inactivos. Correcto.'
                : "$count plugins inactivos instalados. Aunque estén desactivados, sus archivos siguen en el servidor y pueden ser explotados si contienen vulnerabilidades.",
            $count > 0 ? 'Eliminar los plugins que no se usan desde Plugins → Desactivados → Eliminar. Conservar solo los activos.' : '',
            'Limpiamos plugins inactivos reduciendo superficie de ataque y tamaño del sitio.',
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
            'plugin_overload', 'Cantidad de plugins activos',
            $active,
            "$active plugins activos",
            $score,
            "$active plugins activos. Cada plugin añade consultas a DB, código PHP y potencial de conflictos. La regla práctica: <20 plugins en la mayoría de sitios.",
            $active > 30
                ? 'Auditar qué plugins son realmente necesarios. Combinar funcionalidades (muchos builders incluyen lo de varios plugins). Eliminar redundantes.'
                : 'Revisar periódicamente si algún plugin se puede reemplazar por código en el tema o combinar con otros.',
            'Auditamos el stack de plugins y recomendamos consolidación.',
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

        return Scoring::createMetric(
            'plugins_auto_update', 'Auto-update de plugins activos',
            $withAutoUpdate,
            "$withAutoUpdate/$total con auto-update (" . round($pct) . "%)",
            $score,
            "$withAutoUpdate de $total plugins activos tienen actualización automática habilitada. " . ($pct >= 80 ? 'Buena práctica.' : 'Los que no tienen auto-update solo se actualizan manualmente.'),
            $pct < 80
                ? 'En Plugins → habilitar "Actualizaciones automáticas" para los plugins en los que confíes (Yoast, Elementor, WooCommerce, etc.).'
                : '',
            'Configuramos auto-updates selectivas con rollback automático si algo falla.',
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
            'mu_plugins_dropins', 'MU-plugins y drop-ins',
            $total,
            count($mu) . ' MU + ' . count($dropins) . ' drop-ins',
            null,
            "$total componentes instalados silenciosamente (MU-plugins y drop-ins). Estos se cargan automáticamente y pueden ser inyectados por hosting/backup plugins/ManageWP/etc. Merece la pena revisarlos uno a uno.",
            'Revisar wp-content/mu-plugins/ y wp-content/*.php (drop-ins como object-cache.php, advanced-cache.php, db.php). Asegurarse de que cada uno es legítimo.',
            'Auditamos MU-plugins y drop-ins para detectar malware y backdoors.',
            ['mu' => $mu, 'dropins' => $dropins, 'items' => $items]
        );
    }

    public function analyzeTheme(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $active = $themes['active_theme'] ?? [];
        if (empty($active)) return null;

        $name = $active['name'] ?? 'Desconocido';
        $version = $active['version'] ?? '';
        $hasUpdate = (bool) ($active['has_update'] ?? false);
        $isChild = (bool) ($active['is_child_theme'] ?? false);
        $isBlock = (bool) ($active['is_block_theme'] ?? false);
        $author = $active['author'] ?? '';
        $parent = $active['parent_theme'] ?? null;

        $issues = [];
        $score = 100;
        if ($hasUpdate) { $score -= 30; $issues[] = 'actualización disponible'; }
        if (!$isChild) { $score -= 20; $issues[] = 'modificaciones directas al tema padre se perderán en updates'; }

        return Scoring::createMetric(
            'theme_active', 'Tema activo',
            $name,
            "$name $version" . ($isChild ? ' (child)' : ''),
            Scoring::clamp($score),
            $isChild
                ? "Usas child theme de $parent. Puedes personalizar sin perder cambios al actualizar el parent."
                : "Usas tema $name directamente. Cualquier modificación al código se perderá al actualizar. " . ($hasUpdate ? "Además hay actualización disponible." : ''),
            !$isChild ? 'Crear un child theme para personalizaciones sin riesgo de perderlas. En wp-content/themes crear carpeta con style.css (Template: ' . strtolower($name) . ') y functions.php.' : ($hasUpdate ? 'Actualizar el tema a la última versión.' : ''),
            'Creamos child themes para personalizaciones seguras y actualizamos el tema semanalmente.',
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
            'themes_inactive', 'Temas inactivos',
            $count,
            "$count temas sin usar de $total",
            $score,
            "$count temas inactivos en disco. Aunque no se usen, su código sigue en el servidor y puede contener vulnerabilidades. Mantener solo el activo (+ su padre si es child + uno default como fallback).",
            'Eliminar temas inactivos desde Apariencia → Temas → Detalles → Eliminar.',
            'Limpiamos temas innecesarios reduciendo superficie de ataque.',
            ['count' => $count, 'inactive' => $inactiveNames]
        );
    }

    private function slugFromFile(string $file): string {
        if ($file === '') return '';
        $parts = explode('/', $file);
        return $parts[0] ?? '';
    }
}
