<?php
/**
 * Reporte y lectura del progreso de auditorías en curso.
 *
 * Cada audit que arranca escribe su estado periódicamente (antes de cada
 * analyzer) en el cache de archivos. El endpoint /api/scan-progress.php
 * lee ese estado y el frontend lo sondea para mostrar progreso real.
 *
 * TTL corto (10 min) — una vez que el audit termina se guarda el resultado
 * en la tabla `audits` y este progreso ya no hace falta.
 */

class AuditProgress {
    private const TTL_SECONDS = 600;

    /**
     * Steps del orquestador en el orden que se ejecutan. Mapeado a labels
     * legibles para mostrar al usuario mientras corre el audit.
     */
    public const STEPS = [
        'init'           => 'Iniciando análisis...',
        'fetch'          => 'Descargando página...',
        'wordpress'      => 'Detectando WordPress...',
        'security'       => 'Analizando seguridad...',
        'performance'    => 'Consultando Google PageSpeed...',
        'seo'            => 'Verificando SEO...',
        'mobile'         => 'Evaluando experiencia móvil...',
        'infrastructure' => 'Analizando infraestructura...',
        'conversion'     => 'Detectando herramientas de marketing...',
        'page_health'    => 'Verificando salud de página...',
        'wp_internal'    => 'Analizando datos internos...',
        'techstack'      => 'Detectando stack tecnológico...',
        'compile'        => 'Compilando resultados...',
    ];

    /**
     * Actualiza el estado de progreso de un audit.
     *
     * @param string $auditId UUID del audit
     * @param array $state {
     *   status: 'queued' | 'running' | 'completed' | 'failed',
     *   currentStep: key de STEPS,
     *   completedSteps: int,
     *   totalSteps: int,
     *   startedAt: int timestamp,
     *   position?: int,        // solo si status=queued
     *   totalInQueue?: int,    // solo si status=queued
     *   error?: string,        // solo si status=failed
     * }
     */
    public static function update(string $auditId, array $state): void {
        $cache = new Cache();
        // Derivar label legible. Si el caller ya lo especificó (p. ej. queued),
        // respetarlo; si no, lo derivamos del step.
        if (!isset($state['currentLabel'])) {
            $step = $state['currentStep'] ?? 'init';
            $state['currentLabel'] = self::STEPS[$step] ?? $step;
        }
        $completed = (int) ($state['completedSteps'] ?? 0);
        $total = max(1, (int) ($state['totalSteps'] ?? count(self::STEPS)));
        $state['progress'] = min(100, (int) round(($completed / $total) * 100));

        $cache->setByName(self::cacheKey($auditId), $state, self::TTL_SECONDS);
    }

    /**
     * Marca el audit como encolado con su posición.
     */
    public static function queued(string $auditId, int $position, int $totalInQueue): void {
        self::update($auditId, [
            'status' => 'queued',
            'currentStep' => 'init',
            'currentLabel' => 'En cola de procesamiento',
            'completedSteps' => 0,
            'totalSteps' => count(self::STEPS),
            'startedAt' => time(),
            'position' => $position,
            'totalInQueue' => $totalInQueue,
        ]);
    }

    /**
     * Lee el estado actual. Retorna null si no existe o expiró.
     */
    public static function get(string $auditId): ?array {
        $cache = new Cache();
        return $cache->getByName(self::cacheKey($auditId));
    }

    /**
     * Marca el audit como completado.
     */
    public static function completed(string $auditId, int $totalSteps): void {
        self::update($auditId, [
            'status' => 'completed',
            'auditId' => $auditId,
            'currentStep' => 'compile',
            'completedSteps' => $totalSteps,
            'totalSteps' => $totalSteps,
            'startedAt' => (int) (self::get($auditId)['startedAt'] ?? time()),
        ]);
    }

    /**
     * Marca el audit como fallido con un mensaje para el usuario.
     */
    public static function failed(string $auditId, string $userMessage): void {
        self::update($auditId, [
            'status' => 'failed',
            'currentStep' => 'init',
            'completedSteps' => 0,
            'totalSteps' => count(self::STEPS),
            'startedAt' => (int) (self::get($auditId)['startedAt'] ?? time()),
            'error' => $userMessage,
        ]);
    }

    private static function cacheKey(string $auditId): string {
        return 'progress_' . $auditId;
    }
}
