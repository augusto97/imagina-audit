<?php
/**
 * Cálculo de scores y niveles de semáforo.
 *
 * Modelo (v2.1+): scoring honesto, no inflado.
 *
 *   1. Umbrales más exigentes: "good" ahora exige ≥80 (antes 70). Un sitio
 *      con problemas reales cae a warning/deficient y el cliente lo percibe.
 *
 *   2. Per-metric weights: las métricas críticas para el negocio (SSL,
 *      LCP, vulnerabilidades) pesan más que cosméticas (X-Powered-By).
 *      Sin esto el promedio simple distorsiona el módulo.
 *
 *   3. Critical-cap por módulo: si hay AL MENOS UNA métrica crítica, el
 *      módulo no puede sacar más de X puntos (configurable por módulo).
 *      Cierra el hueco de "9 buenas + 1 crítica = 85".
 *
 *   4. Exponential penalty al score global: cada crítico ADICIONAL resta
 *      más que el anterior. Un sitio con muchos críticos NO está al 60%.
 *
 *   5. Disabled metrics/modules: el admin puede excluir métricas/módulos
 *      del cálculo desde /admin/scoring (siguen apareciendo en el informe
 *      como info).
 *
 * Todo configurable desde `settings` (overrides DB) → `defaults.php`.
 * El admin ajusta sin tocar código.
 */
class Scoring {

    private static ?array $configCache = null;

    /**
     * Lee el config de scoring fusionado: settings DB > defaults.php.
     * Se cachea por request porque puede leerse varias veces.
     */
    public static function config(): array {
        if (self::$configCache !== null) return self::$configCache;

        $defaults = require dirname(__DIR__) . '/config/defaults.php';

        // Overrides de DB. Cada key se intenta leer; fallback al default.
        $overrides = [];
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance();
                $rows = $db->query(
                    "SELECT `key`, value FROM settings WHERE `key` IN (
                        'threshold_excellent','threshold_good','threshold_warning','threshold_critical',
                        'scoring_critical_cap_enabled','scoring_critical_cap_per_module',
                        'scoring_critical_penalty_enabled','scoring_critical_penalties',
                        'scoring_disabled_metrics','scoring_disabled_modules',
                        'scoring_metric_weights'
                    )"
                );
                foreach ($rows as $row) {
                    $overrides[$row['key']] = $row['value'];
                }
            }
        } catch (Throwable $e) { /* tabla aún no existe en setup */ }

        // Decode JSON donde aplica
        $jsonKeys = [
            'scoring_critical_cap_per_module',
            'scoring_critical_penalties',
            'scoring_disabled_metrics',
            'scoring_disabled_modules',
            'scoring_metric_weights',
        ];
        foreach ($jsonKeys as $k) {
            if (isset($overrides[$k]) && is_string($overrides[$k])) {
                $decoded = json_decode($overrides[$k], true);
                if (is_array($decoded)) $overrides[$k] = $decoded;
            }
        }

        // Cast bools/ints
        foreach (['threshold_excellent','threshold_good','threshold_warning','threshold_critical'] as $k) {
            if (isset($overrides[$k])) $overrides[$k] = (int) $overrides[$k];
        }
        foreach (['scoring_critical_cap_enabled','scoring_critical_penalty_enabled'] as $k) {
            if (isset($overrides[$k])) $overrides[$k] = in_array((string) $overrides[$k], ['1','true','yes','on'], true);
        }

        return self::$configCache = array_merge($defaults, $overrides);
    }

    /** Reset del cache de config (tests + después de cambiar settings). */
    public static function resetConfig(): void {
        self::$configCache = null;
    }

    /**
     * Determina el nivel de semáforo según el score.
     */
    public static function getLevel(int $score, array $thresholds = []): string {
        $cfg = self::config();
        $excellent = $thresholds['excellent'] ?? $cfg['threshold_excellent'];
        $good = $thresholds['good'] ?? $cfg['threshold_good'];
        $warning = $thresholds['warning'] ?? $cfg['threshold_warning'];

        if ($score >= $excellent) return 'excellent';
        if ($score >= $good) return 'good';
        if ($score >= $warning) return 'warning';
        return 'critical';
    }

    /**
     * Calcula el score global a partir de los módulos. Aplica:
     *   - skip de módulos disabled (siguen en el array pero no entran al avg)
     *   - exponential penalty según críticos totales del audit
     */
    public static function calculateGlobalScore(array $modules): int {
        $cfg = self::config();
        $disabledModules = (array) ($cfg['scoring_disabled_modules'] ?? []);

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($modules as $module) {
            $moduleId = $module['id'] ?? '';
            if (in_array($moduleId, $disabledModules, true)) continue;
            if (($module['score'] ?? null) === null) continue;

            $weight = $module['weight'] ?? 0;
            $weightedSum += $module['score'] * $weight;
            $totalWeight += $weight;
        }
        // Comparación con <= 0 (no === 0): los weights vienen del config admin
        // como float; 0.0 === 0 es false en PHP estricto, y un comparator falso
        // dispara DivisionByZeroError. También cubre el caso de weights
        // negativos accidentales en config malformado.
        if ($totalWeight <= 0) return 0;
        $base = (int) round($weightedSum / $totalWeight);

        // Penalty exponencial por críticos totales
        if (!empty($cfg['scoring_critical_penalty_enabled'])) {
            $criticalCount = self::countCriticals($modules, $disabledModules, (array) ($cfg['scoring_disabled_metrics'] ?? []));
            $penalties = (array) ($cfg['scoring_critical_penalties'] ?? [0, 3, 8, 15, 25]);
            $idx = min($criticalCount, count($penalties) - 1);
            $penalty = (int) ($penalties[$idx] ?? 0);
            $base = max(0, $base - $penalty);
        }

        return $base;
    }

    /**
     * Calcula el score de un módulo. Aplica:
     *   - skip de métricas disabled
     *   - per-metric weights (defaults + overrides admin)
     *   - critical-cap si hay al menos una métrica crítica en el módulo
     */
    public static function calculateModuleScore(array $metrics, array $weights = [], string $moduleId = ''): int {
        if (empty($metrics)) return 0;
        $cfg = self::config();
        $disabledMetrics = (array) ($cfg['scoring_disabled_metrics'] ?? []);
        $weightMap = (array) ($cfg['scoring_metric_weights'] ?? []);

        $totalWeight = 0;
        $weightedSum = 0;
        $hasCritical = false;

        foreach ($metrics as $metric) {
            $id = $metric['id'] ?? '';
            $fullId = $moduleId !== '' ? "$moduleId.$id" : $id;
            if (in_array($fullId, $disabledMetrics, true)) continue;
            if (!isset($metric['score']) || $metric['score'] === null) continue;

            // Resolve weight: legacy $weights param > admin override > default 1.0
            $w = $weights[$id] ?? $weightMap[$fullId] ?? 1.0;
            $weightedSum += $metric['score'] * $w;
            $totalWeight += $w;

            if (($metric['level'] ?? '') === 'critical') {
                $hasCritical = true;
            }
        }

        if ($totalWeight <= 0) return 0;
        $score = (int) round($weightedSum / $totalWeight);

        // Critical-cap: si hay al menos un crítico, capear el módulo.
        if ($hasCritical && !empty($cfg['scoring_critical_cap_enabled'])) {
            $caps = (array) ($cfg['scoring_critical_cap_per_module'] ?? []);
            $cap = (int) ($caps[$moduleId] ?? 60);
            $score = min($score, $cap);
        }

        return $score;
    }

    /**
     * Cuenta métricas críticas en todos los módulos, respetando los
     * filtros de disabled. Usado para la penalty exponencial.
     */
    private static function countCriticals(array $modules, array $disabledModules, array $disabledMetrics): int {
        $count = 0;
        foreach ($modules as $module) {
            $moduleId = $module['id'] ?? '';
            if (in_array($moduleId, $disabledModules, true)) continue;
            foreach ($module['metrics'] ?? [] as $metric) {
                $id = $metric['id'] ?? '';
                $fullId = "$moduleId.$id";
                if (in_array($fullId, $disabledMetrics, true)) continue;
                if (($metric['level'] ?? '') === 'critical') $count++;
            }
        }
        return $count;
    }

    /** Limita un score entre 0 y 100. */
    public static function clamp(int $score): int {
        return max(0, min(100, $score));
    }

    /** Crea un resultado de métrica estandarizado. */
    public static function createMetric(
        string $id,
        string $name,
        mixed $value,
        string $displayValue,
        int|null $score,
        string $description,
        string $recommendation,
        string $imaginaSolution,
        array $details = []
    ): array {
        if ($score !== null) {
            $score = self::clamp($score);
        }
        return [
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'displayValue' => $displayValue,
            'score' => $score,
            'level' => $score !== null ? self::getLevel($score) : 'info',
            'description' => $description,
            'recommendation' => $recommendation,
            'imaginaSolution' => $imaginaSolution,
            'details' => $details,
        ];
    }

    /** Cuenta issues por nivel. */
    public static function countIssues(array $modules): array {
        $counts = ['critical' => 0, 'warning' => 0, 'good' => 0];

        foreach ($modules as $module) {
            foreach ($module['metrics'] ?? [] as $metric) {
                $level = $metric['level'] ?? 'unknown';
                if ($level === 'critical') {
                    $counts['critical']++;
                } elseif ($level === 'warning') {
                    $counts['warning']++;
                } elseif ($level === 'good' || $level === 'excellent') {
                    $counts['good']++;
                }
            }
        }

        return $counts;
    }

    /** Genera el mapa de soluciones a partir de los módulos. */
    public static function generateSolutionMap(array $modules): array {
        $solutions = [];

        foreach ($modules as $module) {
            foreach ($module['metrics'] ?? [] as $metric) {
                $level = $metric['level'] ?? 'unknown';
                if ($level === 'critical' || $level === 'warning') {
                    $solutions[] = [
                        'problem' => $metric['name'] . ': ' . $metric['description'],
                        'level' => $level,
                        'solution' => $metric['imaginaSolution'],
                        'includedInPlan' => $level === 'critical' ? 'Basic' : 'Pro',
                    ];
                }
            }
        }

        return $solutions;
    }

    /**
     * Recalcula scores y niveles de un AuditResult ya guardado, usando la
     * config de scoring ACTUAL. Pensado para audits viejos: el cliente ve
     * el score reflejando los estándares actuales de la herramienta,
     * no los que estaban vigentes cuando se hizo el scan.
     *
     * No persiste — devuelve el array modificado para que el endpoint
     * lo sirva al frontend.
     */
    public static function recalculate(array $audit): array {
        if (empty($audit['modules'])) return $audit;
        self::resetConfig(); // por si el config cambió mid-request

        foreach ($audit['modules'] as &$module) {
            // Re-asignar level por métrica con los thresholds actuales
            foreach ($module['metrics'] ?? [] as &$metric) {
                if (isset($metric['score']) && $metric['score'] !== null) {
                    $metric['level'] = self::getLevel((int) $metric['score']);
                }
            }
            unset($metric);
            // Re-score del módulo respetando filtros + cap
            $module['score'] = self::calculateModuleScore(
                $module['metrics'] ?? [],
                [],
                $module['id'] ?? ''
            );
            $module['level'] = self::getLevel($module['score']);
        }
        unset($module);

        // Re-score global
        $audit['globalScore'] = self::calculateGlobalScore($audit['modules']);
        $audit['globalLevel'] = self::getLevel($audit['globalScore']);
        $audit['totalIssues'] = self::countIssues($audit['modules']);

        return $audit;
    }

    /** Calcula el impacto económico estimado basado en el rendimiento. */
    public static function calculateEconomicImpact(array $modules): array {
        $lcp = 4000;

        foreach ($modules as $module) {
            if ($module['id'] === 'performance') {
                foreach ($module['metrics'] ?? [] as $metric) {
                    if ($metric['id'] === 'lcp' && is_numeric($metric['value'])) {
                        $lcp = (float) $metric['value'];
                        break;
                    }
                }
                break;
            }
        }

        $loadTimeSeconds = $lcp / 1000;
        $excessSeconds = max(0, $loadTimeSeconds - 2.5);
        $conversionLossPercent = $excessSeconds * 7;
        $estimatedMonthlyVisits = 3000;
        $baseConversionRate = 0.02;
        $avgConversionValue = 50;
        $lostConversions = $estimatedMonthlyVisits * $baseConversionRate * ($conversionLossPercent / 100);
        $monthlyLoss = (int) round($lostConversions * $avgConversionValue);

        $explanation = $monthlyLoss > 0
            ? Translator::t('scoring.economic.explanation.loss', ['seconds' => $loadTimeSeconds, 'loss' => $monthlyLoss])
            : Translator::t('scoring.economic.explanation.ok');

        return [
            'estimatedMonthlyLoss' => $monthlyLoss,
            'currency' => 'USD',
            'explanation' => $explanation,
        ];
    }
}
