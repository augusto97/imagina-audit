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

    private function buildOverview(): array    { return []; }
    private function buildEnvironment(): array { return []; }
    private function buildPlugins(): array     { return []; }
    private function buildThemes(): array      { return []; }
    private function buildSecurity(): array    { return []; }
    private function buildPerformance(): array { return []; }
    private function buildDatabase(): array    { return []; }
    private function buildCron(): array        { return []; }
    private function buildMedia(): array       { return []; }
    private function buildUsers(): array       { return []; }
    private function buildContent(): array     { return []; }
}
