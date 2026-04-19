<?php
/**
 * Analiza el JSON generado por el plugin wp-snapshot y produce el módulo
 * "Análisis Interno (WordPress)" con datos que no se pueden obtener
 * desde fuera del sitio.
 *
 * Orquestador delgado que delega a 4 sub-checkers:
 *   - WpSnapshotEnvironmentChecker (PHP, MySQL, límites, URL, debug, updates, caches)
 *   - WpSnapshotExtensionsChecker  (plugins, temas)
 *   - WpSnapshotDatabaseChecker    (DB, autoload, revisiones, spam, papelera, cron, medios)
 *   - WpSnapshotUsersChecker       (usuarios, detección de admins débiles)
 */

class WpSnapshotAnalyzer {
    public function __construct(private array $snapshot) {}

    public function analyze(): array {
        $env = new WpSnapshotEnvironmentChecker($this->snapshot);
        $ext = new WpSnapshotExtensionsChecker($this->snapshot);
        $db = new WpSnapshotDatabaseChecker($this->snapshot);
        $users = new WpSnapshotUsersChecker($this->snapshot);

        // Orden original preservado (mismos IDs de métricas, misma posición)
        $checks = [
            $env->analyzeEnvironment(),
            $env->analyzeMysqlVersion(),
            $env->analyzeUploadLimits(),
            $env->analyzeExecutionLimits(),
            $env->analyzeSiteUrlMismatch(),
            $env->analyzeMultisite(),
            $env->analyzeWpDebug(),
            $env->analyzeFileEditing(),
            $env->analyzeAutoUpdates(),
            $ext->analyzePlugins(),
            $ext->analyzeInactivePlugins(),
            $ext->analyzePluginOverload(),
            $ext->analyzeAbandonedPlugins(),
            $ext->analyzeThemes(),
            $ext->analyzeInactiveThemes(),
            $db->analyzeDatabase(),
            $db->analyzeDbEngine(),
            $db->analyzeAutoload(),
            $db->analyzeRevisions(),
            $db->analyzeTransients(),
            $db->analyzeOrphanedMeta(),
            $db->analyzeSpamComments(),
            $db->analyzeTrashedPosts(),
            $db->analyzeCron(),
            $users->analyzeUsers(),
            $users->analyzeWeakAdminUsers(),
            $env->analyzeObjectCache(),
            $env->analyzeOpcache(),
            $db->analyzeMediaSize(),
        ];

        $metrics = array_values(array_filter($checks, fn($m) => $m !== null));
        $score = Scoring::calculateModuleScore($metrics);

        $defaults = require dirname(__DIR__) . '/config/defaults.php';

        return [
            'id' => 'wp_internal',
            'name' => 'Análisis Interno (WordPress)',
            'icon' => 'database',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_wp_internal'] ?? 0.10,
            'metrics' => $metrics,
            'summary' => "Análisis interno del sitio basado en el snapshot: $score/100.",
            'salesMessage' => $defaults['sales_wp_internal'] ?? '',
        ];
    }
}
