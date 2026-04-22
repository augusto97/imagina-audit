<?php
/**
 * Analiza el JSON del plugin wp-snapshot y produce el módulo "Análisis Interno
 * (WordPress)" con datos que NO se pueden obtener desde fuera del sitio:
 * versiones de PHP/DB reales, objeto cache, opcache, plugins inactivos,
 * tamaño de DB, revisiones acumuladas, roles de usuario, cron overdue, etc.
 *
 * Orquesta 4 sub-checkers. Cada check retorna un ModuleResult o null (si la
 * sección no aplica al snapshot). Los que retornan null se filtran del array
 * final para no ensuciar el informe con "no disponible".
 */

class WpSnapshotAnalyzer {
    public function __construct(private array $snapshot) {}

    public function analyze(): array {
        $env   = new WpSnapshotEnvironmentChecker($this->snapshot);
        $ext   = new WpSnapshotExtensionsChecker($this->snapshot);
        $db    = new WpSnapshotDatabaseChecker($this->snapshot);
        $users = new WpSnapshotUsersChecker($this->snapshot);

        // Orden pensado para el informe: primero lo más crítico
        // (actualizaciones, seguridad), luego rendimiento, luego estructura.
        $checks = [
            // --- Actualizaciones ---
            $env->analyzeWpVersion(),
            $ext->analyzePluginsOutdated(),
            $ext->analyzeTheme(),

            // --- Seguridad ---
            $env->analyzeWpDebug(),
            $env->analyzeFileEditing(),
            $env->analyzeXmlRpc(),
            $env->analyzeSsl(),
            $env->analyzeDbPrefix(),
            $env->analyzeAutoUpdates(),
            $users->analyzeRoleCounts(),

            // --- Limpieza / superficie de ataque ---
            $ext->analyzePluginsInactive(),
            $ext->analyzePluginOverload(),
            $ext->analyzeAutoUpdatePlugins(),
            $ext->analyzeInactiveThemes(),
            $ext->analyzeMuPluginsDropins(),

            // --- Rendimiento (cache stack) ---
            $env->analyzePageCache(),
            $env->analyzeObjectCache(),
            $env->analyzeOpcache(),
            $env->analyzeImageEditor(),
            $env->analyzePermalinks(),

            // --- Configuración servidor ---
            $env->analyzePhp(),
            $env->analyzeDatabase(),
            $env->analyzeUploadLimits(),

            // --- Salud de datos acumulados ---
            $db->analyzeDbSize(),
            $db->analyzeAutoload(),
            $db->analyzeDbEngine(),
            $db->analyzeRevisions(),
            $db->analyzeTransients(),
            $db->analyzeOrphanedMeta(),
            $db->analyzeCron(),
            $db->analyzeMedia(),

            // --- Estructura del contenido ---
            $db->analyzePostTypes(),
            $db->analyzeRestApi(),
            $users->analyzeSecurityChecks(),
        ];

        $metrics = array_values(array_filter($checks, fn($m) => $m !== null));
        $score = Scoring::calculateModuleScore($metrics);

        $defaults = require dirname(__DIR__) . '/config/defaults.php';

        // Resumen útil con los datos más importantes
        $plugins = $this->snapshot['sections']['plugins']['data'] ?? [];
        $totalPlugins = (int) ($plugins['total_plugins'] ?? 0);
        $outdatedPlugins = (int) ($plugins['update_available'] ?? 0);
        $siteName = $this->snapshot['site_name'] ?? '';
        $generatedAt = $this->snapshot['generated_at'] ?? '';

        $summary = Translator::t('wp_snapshot.summary.prefix', ['score' => $score]);
        if ($outdatedPlugins > 0) $summary .= Translator::t('wp_snapshot.summary.outdated', ['outdated' => $outdatedPlugins, 'total' => $totalPlugins]);
        if ($siteName) $summary .= Translator::t('wp_snapshot.summary.site', ['name' => $siteName]);

        return [
            'id' => 'wp_internal',
            'name' => Translator::t('modules.wp_internal.name'),
            'icon' => 'database',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_wp_internal'] ?? 0.10,
            'metrics' => $metrics,
            'summary' => $summary,
            'salesMessage' => !empty($defaults['sales_wp_internal']) ? $defaults['sales_wp_internal'] : Translator::t('modules.sales.wp_internal'),
            'snapshotMeta' => [
                'siteName' => $siteName,
                'generatedAt' => $generatedAt,
                'generatorVersion' => $this->snapshot['generator_version'] ?? '',
            ],
        ];
    }
}
