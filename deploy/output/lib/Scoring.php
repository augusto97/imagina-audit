<?php
/**
 * Funciones de cálculo de scores y niveles de semáforo
 */

class Scoring {
    /**
     * Determina el nivel de semáforo según el score
     */
    public static function getLevel(int $score, array $thresholds = []): string {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $excellent = $thresholds['excellent'] ?? $defaults['threshold_excellent'];
        $good = $thresholds['good'] ?? $defaults['threshold_good'];
        $warning = $thresholds['warning'] ?? $defaults['threshold_warning'];
        $critical = $thresholds['critical'] ?? $defaults['threshold_critical'];

        if ($score >= $excellent) return 'excellent';
        if ($score >= $good) return 'good';
        if ($score >= $warning) return 'warning';
        return 'critical';
    }

    /**
     * Calcula el score global a partir de los scores de módulos y sus pesos
     */
    public static function calculateGlobalScore(array $modules): int {
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($modules as $module) {
            if ($module['score'] === null) {
                continue; // Módulo que falló
            }
            $weight = $module['weight'] ?? 0;
            $weightedSum += $module['score'] * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) return 0;

        return (int) round($weightedSum / $totalWeight);
    }

    /**
     * Calcula el promedio ponderado de métricas dentro de un módulo
     */
    public static function calculateModuleScore(array $metrics, array $weights = []): int {
        if (empty($metrics)) return 0;

        // Si no hay pesos, promedio simple
        if (empty($weights)) {
            $sum = 0;
            $count = 0;
            foreach ($metrics as $metric) {
                if (isset($metric['score'])) {
                    $sum += $metric['score'];
                    $count++;
                }
            }
            return $count > 0 ? (int) round($sum / $count) : 0;
        }

        // Promedio ponderado
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($metrics as $metric) {
            $id = $metric['id'] ?? '';
            $weight = $weights[$id] ?? 1;
            if (isset($metric['score'])) {
                $weightedSum += $metric['score'] * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : 0;
    }

    /**
     * Limita un score entre 0 y 100
     */
    public static function clamp(int $score): int {
        return max(0, min(100, $score));
    }

    /**
     * Crea un resultado de métrica estandarizado
     */
    public static function createMetric(
        string $id,
        string $name,
        mixed $value,
        string $displayValue,
        int $score,
        string $description,
        string $recommendation,
        string $imaginaSolution,
        array $details = []
    ): array {
        $score = self::clamp($score);
        return [
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'displayValue' => $displayValue,
            'score' => $score,
            'level' => self::getLevel($score),
            'description' => $description,
            'recommendation' => $recommendation,
            'imaginaSolution' => $imaginaSolution,
            'details' => $details,
        ];
    }

    /**
     * Cuenta issues por nivel
     */
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

    /**
     * Genera el mapa de soluciones a partir de los módulos
     */
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
     * Calcula el impacto económico estimado basado en el rendimiento
     */
    public static function calculateEconomicImpact(array $modules): array {
        $lcp = 4000; // Default 4 segundos

        // Buscar LCP en el módulo de performance
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
            ? "Tu sitio carga en {$loadTimeSeconds}s. Cada segundo extra sobre 2.5s reduce las conversiones ~7%. Estimamos una pérdida de ~\${$monthlyLoss} USD/mes basado en promedios de la industria."
            : 'Tu sitio tiene buenos tiempos de carga. No se estima pérdida significativa por velocidad.';

        return [
            'estimatedMonthlyLoss' => $monthlyLoss,
            'currency' => 'USD',
            'explanation' => $explanation,
        ];
    }
}
