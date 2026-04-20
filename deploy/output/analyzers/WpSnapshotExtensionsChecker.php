<?php
/**
 * Checks del snapshot sobre plugins y temas: actualizaciones, inactivos,
 * exceso, abandonados.
 *
 * Sub-checker de WpSnapshotAnalyzer.
 */

class WpSnapshotExtensionsChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzePlugins(): ?array {
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

    public function analyzeInactivePlugins(): ?array {
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

    public function analyzePluginOverload(): ?array {
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

    public function analyzeAbandonedPlugins(): ?array {
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

    public function analyzeThemes(): ?array {
        $themes = $this->getSection('themes');
        if (empty($themes)) return null;

        $total = $themes['total_themes'] ?? 0;
        $active = $themes['active_theme'] ?? [];
        $activeName = $active['name'] ?? 'Desconocido';
        $updateAvailable = 0;
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

    public function analyzeInactiveThemes(): ?array {
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
}
