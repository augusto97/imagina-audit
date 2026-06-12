<?php
/**
 * Cola de auditorías con límite de concurrencia (auto-worker).
 *
 * El modelo es Pingdom-style SIN daemon:
 *
 * - Al recibir un audit nuevo: si hay slot libre (jobs 'running' <
 *   audit_max_concurrent) se marca como 'running' y se procesa. Si no,
 *   se encola con status='queued' y el cliente ve su posición.
 *
 * - Cuando un request termina de procesar su audit, intenta drenar la
 *   cola: coge el siguiente job 'queued' y lo ejecuta, repitiendo hasta
 *   que no queden jobs o se acerque al límite de tiempo PHP.
 *
 * - Un cron cada 5 min (dead-man switch):
 *     - Mata jobs 'running' que llevan > audit_stale_seconds sin terminar
 *       (asumimos que el proceso PHP murió).
 *     - Si hay jobs 'queued' y slots libres, dispara drain() para
 *       reactivar el flujo.
 *
 * Concurrencia: SQLite serializa escrituras, así que tryAcquireSlot +
 * INSERT no compiten. Dos requests simultáneos ven secuencialmente el
 * mismo count y la condición de race se resuelve por el orden de
 * commits. Con pocos workers concurrentes esto es suficiente.
 */

class QueueManager {
    /** Default si no hay override en DB. */
    private const DEFAULT_MAX_CONCURRENT = 3;
    private const DEFAULT_STALE_SECONDS = 180;

    /**
     * Máxima concurrencia actual. Lee de settings, fallback a defaults.
     */
    public static function getMaxConcurrent(): int {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $max = (int) ($defaults['audit_max_concurrent'] ?? self::DEFAULT_MAX_CONCURRENT);
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'audit_max_concurrent'");
            if ($row && is_numeric($row['value'])) {
                $max = max(1, (int) $row['value']);
            }
        } catch (Throwable $e) {}
        return $max;
    }

    public static function getStaleSeconds(): int {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        return (int) ($defaults['audit_stale_seconds'] ?? self::DEFAULT_STALE_SECONDS);
    }

    /**
     * Busca el último fallo reciente para una URL dentro de la ventana
     * configurada (`audit_failure_cache_minutes`). Si existe, retorna el
     * mensaje de error — el caller puede devolverlo sin reprocesar.
     *
     * Esto protege contra:
     *   - Usuarios que hacen clic repetidamente sobre un sitio caído.
     *   - Widget embebido en un sitio de cliente que genera loops si falla.
     *   - Ataques que intentan abrumar la cola con URLs inválidas.
     */
    public static function findRecentFailure(string $url): ?string {
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $windowMin = (int) ($defaults['audit_failure_cache_minutes'] ?? 10);
        if ($windowMin <= 0) return null;

        try {
            $db = Database::getInstance();
            $row = $db->queryOne(
                "SELECT error_message FROM audit_jobs
                 WHERE url = ? AND status = 'failed'
                 AND completed_at > ?
                 ORDER BY completed_at DESC LIMIT 1",
                [$url, $db->nowMinus($windowMin * 60)]
            );
            if ($row && !empty($row['error_message'])) {
                $msg = $row['error_message'];
                // No envenenamos el cache con errores de infraestructura.
                // Causa raíz del bug v2.2.1: un "Job huérfano…" o "Error
                // guardando el resultado" hacía que la URL apareciera
                // "blacklisteada" 10 min, aunque el sitio estaba ok. La
                // mitigación de aquel commit (panel de rescate manual) era
                // el síntoma — esto es la causa.
                if (self::isInfrastructureError($msg)) return null;
                return $msg;
            }
        } catch (Throwable $e) {
            Logger::warning('findRecentFailure falló: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Decide si un error_message representa un fallo de infraestructura
     * (cosa nuestra) en vez de un fallo del sitio auditado. Para los
     * primeros, no envenenamos el failure-cache: que la próxima request
     * lo intente de nuevo.
     */
    public static function isInfrastructureError(string $msg): bool {
        $needles = [
            'Job huérfano',
            'Error guardando',
            'Abandonado tras',
            'proceso PHP murió',
        ];
        foreach ($needles as $n) {
            if (stripos($msg, $n) !== false) return true;
        }
        return false;
    }

    /**
     * Cuenta cuántas veces una URL ha fallado dentro de la ventana. Útil
     * para detectar URLs "tóxicas" que conviene bloquear temporalmente.
     */
    public static function recentFailureCount(string $url, int $windowMinutes = 30): int {
        try {
            $db = Database::getInstance();
            return (int) $db->scalar(
                "SELECT COUNT(*) FROM audit_jobs
                 WHERE url = ? AND status = 'failed'
                 AND completed_at > ?",
                [$url, $db->nowMinus($windowMinutes * 60)]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Cuántos jobs están corriendo ahora mismo.
     */
    public static function runningCount(): int {
        $db = Database::getInstance();
        return (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'running'");
    }

    /**
     * Intenta reservar un slot y, si lo consigue, inserta el job
     * directamente como 'running'. Si no hay slot, encola como 'queued'.
     *
     * Retorna:
     *   - ['status' => 'running', 'position' => 0]   si consiguió slot
     *   - ['status' => 'queued',  'position' => N]   si quedó en cola
     */
    public static function enqueueOrStart(string $auditId, string $url, array $leadData, string $ip): array {
        $db = Database::getInstance();
        $leadJson = json_encode($leadData, JSON_UNESCAPED_UNICODE);

        // Cleanup previo: matar huérfanos antes de evaluar concurrencia
        self::reapStaleRunning();

        $running = self::runningCount();
        $max = self::getMaxConcurrent();

        if ($running < $max) {
            $now = $db->now();
            $db->execute(
                "INSERT INTO audit_jobs (audit_id, url, lead_data_json, status, ip_address, started_at) VALUES (?, ?, ?, 'running', ?, $now)",
                [$auditId, $url, $leadJson, $ip]
            );
            return ['status' => 'running', 'position' => 0];
        }

        $db->execute(
            "INSERT INTO audit_jobs (audit_id, url, lead_data_json, status, ip_address) VALUES (?, ?, ?, 'queued', ?)",
            [$auditId, $url, $leadJson, $ip]
        );
        $position = self::getPosition($auditId);
        return ['status' => 'queued', 'position' => $position];
    }

    /**
     * Encola el job SIEMPRE como 'queued' (nunca 'running'), sin intentar
     * arrancarlo inline. Pensado para arquitectura queue-only donde el
     * scan corre exclusivamente en un drain worker (cron o kick async),
     * NO en el mismo proceso PHP que recibió el POST /audit.
     *
     * Beneficio: el worker que atiende /audit responde en milisegundos y
     * queda libre. El admin / dashboard / otros endpoints no compiten con
     * un scan de 30-45s por la misma sesión PHP o por workers escasos.
     */
    public static function enqueue(string $auditId, string $url, array $leadData, string $ip): array {
        $db = Database::getInstance();
        $leadJson = json_encode($leadData, JSON_UNESCAPED_UNICODE);

        self::reapStaleRunning();

        $db->execute(
            "INSERT INTO audit_jobs (audit_id, url, lead_data_json, status, ip_address) VALUES (?, ?, ?, 'queued', ?)",
            [$auditId, $url, $leadJson, $ip]
        );
        $position = self::getPosition($auditId);
        return ['status' => 'queued', 'position' => $position];
    }

    /**
     * Dispara el drain de la cola en background — fire-and-forget. Lo
     * llama /api/audit después de encolar para que el scan arranque casi
     * al instante, sin esperar al cron de fallback (~1 min de latencia).
     *
     * Estrategia:
     *   1. Si shell_exec está habilitado → spawn `php cron/drain-queue.php`
     *      en background con redirect a /dev/null. Es lo más rápido y
     *      barato (no usa worker web).
     *   2. Fallback: curl HTTP a /cron/drain-queue.php?token=X con timeout
     *      muy bajo. La conexión se cierra rápido pero el server-side
     *      mantiene el script vivo gracias a `ignore_user_abort(true)`.
     *   3. Si todo falla, el cron seguirá drenando — solo perdemos
     *      inmediatez.
     */
    public static function kickDrain(): void {
        // Intento 1: shell_exec en background. El más limpio.
        if (function_exists('shell_exec') && !self::isFunctionDisabled('shell_exec')) {
            $script = dirname(__DIR__) . '/cron/drain-queue.php';
            if (is_file($script)) {
                $cmd = sprintf('php %s > /dev/null 2>&1 &', escapeshellarg($script));
                @shell_exec($cmd);
                return;
            }
        }

        // Intento 2: curl HTTP self-call con timeout 1s.
        //
        // Resolución de URL base (en orden):
        //   1. Setting `app_url` (lo correcto en producción — el admin lo
        //      configura una vez y no depende de cómo llegó el request).
        //   2. HTTP_HOST del request entrante. Es spoofeable, pero:
        //        - El token se valida en drain-queue.php con hash_equals,
        //          un Host forjado solo enrutaría a OTRO server, no nos
        //          permitiría procesar nada.
        //        - El attacker no tiene forma de hacer que el response
        //          venga de vuelta a su lado de la red (fire-and-forget).
        //      El riesgo real es bajo y este fallback es lo que hace que
        //      la cola arranque sin configuración previa.
        //   3. localhost como último recurso.
        if (function_exists('curl_init')) {
            $token = self::ensureCronToken(); // auto-genera si no existe
            if ($token === '') return;

            $base = '';
            try {
                // CUIDADO: Database::setting($key, $value) es un SETTER
                // (upsert), no un getter. Usar scalar() para leer settings.
                // El bug previo pasaba '' como value, sobrescribía app_url
                // en cada kick, devolvía rowCount como int, y al castear a
                // string daba "0"/"1" — la URL del kick quedaba inválida
                // ("1/cron/drain-queue.php") y el self-call siempre fallaba.
                $base = (string) Database::getInstance()->scalar(
                    "SELECT value FROM settings WHERE `key` = 'app_url'"
                );
                $base = trim($base);
                // Auto-clean: el bug previo pudo haber dejado "0"/"1" en el
                // setting. Si vemos basura no-URL la ignoramos.
                if ($base !== '' && !preg_match('#^https?://#i', $base)) {
                    $base = '';
                }
            } catch (Throwable $e) { /* DB no disponible o tabla no existe */ }
            $base = rtrim($base, '/');
            if ($base === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $base = "$scheme://$host";
            }

            $url = "$base/cron/drain-queue.php?token=" . urlencode($token);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 500,
                CURLOPT_TIMEOUT_MS => 1000,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            @curl_exec($ch);
            @curl_close($ch);
        }
    }

    /**
     * Resuelve el token de cron — del .env si está, si no de la tabla
     * settings, si no genera uno aleatorio y lo persiste en settings.
     * Esto desbloquea instalaciones donde el admin no configuró
     * CRON_SECRET_TOKEN: la cola se procesa igual.
     *
     * El drain-queue.php usa el mismo helper para validar el token.
     */
    public static function ensureCronToken(): string {
        $envToken = function_exists('env') ? env('CRON_SECRET_TOKEN', '') : '';
        if ($envToken !== '' && $envToken !== 'cambiar-este-token') {
            return $envToken;
        }
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'cron_secret_token'");
            if ($row && !empty($row['value'])) return (string) $row['value'];

            // Generar uno nuevo + persistir en settings
            $token = bin2hex(random_bytes(16));
            $db->setting('cron_secret_token', $token);
            return $token;
        } catch (Throwable $e) {
            return '';
        }
    }

    /** Detecta si una función PHP está en `disable_functions`. */
    private static function isFunctionDisabled(string $name): bool {
        $disabled = explode(',', (string) ini_get('disable_functions'));
        return in_array($name, array_map('trim', $disabled), true);
    }

    /**
     * Posición FIFO del job en la cola (1-indexed). 0 si ya está running.
     */
    public static function getPosition(string $auditId): int {
        $db = Database::getInstance();
        $job = $db->queryOne("SELECT status, created_at FROM audit_jobs WHERE audit_id = ?", [$auditId]);
        if (!$job) return 0;
        if ($job['status'] !== 'queued') return 0;

        $before = (int) $db->scalar(
            "SELECT COUNT(*) FROM audit_jobs WHERE status = 'queued' AND created_at < ?",
            [$job['created_at']]
        );
        return $before + 1;
    }

    /**
     * Cuántos jobs hay encolados ahora mismo.
     */
    public static function queuedCount(): int {
        $db = Database::getInstance();
        return (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'queued'");
    }

    public static function markCompleted(string $auditId): void {
        try {
            $db = Database::getInstance();
            $now = $db->now();
            $db->execute(
                "UPDATE audit_jobs SET status = 'completed', completed_at = $now WHERE audit_id = ?",
                [$auditId]
            );
        } catch (Throwable $e) {
            Logger::warning('QueueManager markCompleted falló: ' . $e->getMessage());
        }
    }

    public static function markFailed(string $auditId, string $error): void {
        try {
            $db = Database::getInstance();
            $now = $db->now();
            $db->execute(
                "UPDATE audit_jobs SET status = 'failed', completed_at = $now, error_message = ? WHERE audit_id = ?",
                [mb_substr($error, 0, 500), $auditId]
            );
        } catch (Throwable $e) {
            Logger::warning('QueueManager markFailed falló: ' . $e->getMessage());
        }
    }

    /**
     * Toma el siguiente job 'queued' y lo promueve a 'running' atómicamente.
     * Retorna null si no hay jobs o si la cola no debería avanzar (límite).
     */
    public static function dequeueNext(): ?array {
        $db = Database::getInstance();

        try {
            // Check de capacidad fuera de transacción — es solo una pista
            // (race-y por naturaleza); la atomicidad real del claim vive en
            // el UPDATE condicional de abajo.
            $running = (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'running'");
            $max = self::getMaxConcurrent();
            if ($running >= $max) return null;

            // Loop: leemos el primer 'queued', intentamos claim atómico con
            // UPDATE condicional (`AND status='queued'`). Si rowCount() es 0,
            // otro worker se lo llevó — probamos con el siguiente. Sin esto,
            // dos drainers (cron + kickDrain) toman el mismo job, lo procesan
            // doble, y el segundo INSERT en `audits` falla con PK duplicada
            // → envenena el failure-cache. Causa raíz del bug v2.2.1 que
            // mitigamos con el panel de rescate.
            $defaults = require dirname(__DIR__) . '/config/defaults.php';
            $maxAttempts = (int) ($defaults['audit_max_attempts'] ?? 3);
            $maxProbes = 20;  // defensa anti loop infinito
            for ($i = 0; $i < $maxProbes; $i++) {
                $job = $db->queryOne(
                    "SELECT * FROM audit_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1"
                );
                if (!$job) return null;

                $now = $db->now();
                if (((int) $job['attempts']) >= $maxAttempts) {
                    // El job ya gastó sus intentos — claim condicional para
                    // marcarlo failed y seguir.
                    $msg = 'Abandonado tras ' . (int) $job['attempts'] . ' intentos.';
                    $marked = $db->execute(
                        "UPDATE audit_jobs SET status = 'failed', error_message = ?, completed_at = $now WHERE id = ? AND status = 'queued'",
                        [$msg, $job['id']]
                    );
                    if ($marked > 0) {
                        AuditProgress::failed($job['audit_id'], 'El análisis falló repetidamente y fue abandonado. Contacta a soporte si persiste.');
                    }
                    continue;
                }

                // Claim atómico: solo gana el primer worker que ejecute el
                // UPDATE. rowCount=1 → es nuestro; rowCount=0 → otro worker
                // se adelantó, probar siguiente.
                $claimed = $db->execute(
                    "UPDATE audit_jobs SET status = 'running', started_at = $now, attempts = attempts + 1 WHERE id = ? AND status = 'queued'",
                    [$job['id']]
                );
                if ($claimed === 0) continue;

                $job['status'] = 'running';
                $job['attempts'] = ((int) $job['attempts']) + 1;
                $job['started_at'] = date('Y-m-d H:i:s');
                return $job;
            }
            // Si tras 20 probes no logramos claim, probablemente hay
            // contention extrema o jobs corruptos — el cron volverá a probar.
            return null;
        } catch (Throwable $e) {
            Logger::error('dequeueNext falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Marca como 'failed' los jobs 'running' que llevan demasiado tiempo
     * (proceso muerto). Retorna cuántos mató.
     */
    public static function reapStaleRunning(): int {
        try {
            $db = Database::getInstance();
            $stale = self::getStaleSeconds();
            $now = $db->now();
            // Threshold computado en PHP — evita dialect-specific date math
            // (SQLite: datetime('now', '-N seconds'); MySQL: DATE_SUB(NOW(), INTERVAL N SECOND)).
            $threshold = date('Y-m-d H:i:s', time() - $stale);
            $count = $db->execute(
                "UPDATE audit_jobs SET status = 'failed', error_message = 'Job huérfano: proceso PHP murió antes de completar el audit.', completed_at = $now WHERE status = 'running' AND started_at IS NOT NULL AND started_at < ?",
                [$threshold]
            );
            if ($count > 0) {
                Logger::warning("Reaped $count stale audit jobs");
                // Marcar también el AuditProgress de estos jobs como 'failed'
                // para que el frontend deje de hacer polling indefinidamente
                $recentThreshold = date('Y-m-d H:i:s', time() - 60);
                $staleJobs = $db->query(
                    "SELECT audit_id FROM audit_jobs WHERE status = 'failed' AND error_message LIKE 'Job huérfano%' AND completed_at > ?",
                    [$recentThreshold]
                );
                foreach ($staleJobs as $j) {
                    AuditProgress::failed($j['audit_id'], 'El análisis tardó demasiado y fue cancelado. Intenta nuevamente.');
                }
            }
            return $count;
        } catch (Throwable $e) {
            Logger::warning('reapStaleRunning falló: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Drena la cola procesando jobs 'queued' uno tras otro mientras:
     *  - haya jobs en cola
     *  - haya slots libres
     *  - no se pase del tiempo máximo
     *
     * El caller (audit.php al terminar un audit o el cron) invoca esto
     * para mantener la cola fluyendo sin necesidad de daemon.
     *
     * NOTA: cada job ejecuta un AuditOrchestrator completo (~40s). Por
     * eso maxSeconds debe dejar margen al set_time_limit del caller.
     */
    public static function drain(int $maxSeconds = 90): int {
        $processed = 0;
        $deadline = time() + $maxSeconds;

        while (time() < $deadline) {
            self::reapStaleRunning();
            $job = self::dequeueNext();
            if (!$job) break; // cola vacía o límite alcanzado

            try {
                self::processJob($job);
                $processed++;
            } catch (Throwable $e) {
                Logger::error('drain processJob falló: ' . $e->getMessage(), ['audit_id' => $job['audit_id']]);
                self::markFailed($job['audit_id'], 'Error interno procesando el audit.');
                AuditProgress::failed($job['audit_id'], 'Ocurrió un error al analizar el sitio.');
            }
        }
        return $processed;
    }

    /**
     * Ejecuta un job completo: AuditOrchestrator + guardado en DB +
     * notificación email. Actualiza AuditProgress y audit_jobs.
     */
    public static function processJob(array $job): void {
        $auditId = $job['audit_id'];
        $url = $job['url'];
        $leadData = json_decode($job['lead_data_json'] ?? '[]', true) ?: [];
        $ip = $job['ip_address'] ?? 'unknown';

        // Restaurar el idioma del request original. Sin esto, el worker
        // (CLI o HTTP kick) cae al DEFAULT_LANG ('en') y todos los strings
        // del result_json (nombre de métrica, descripción, recomendación,
        // imaginaSolution) acaban en inglés aunque el visitante pidió el
        // scan en español. Ver audit.php donde se persiste `lang` dentro
        // de lead_data_json justo para este momento.
        $jobLang = $leadData['lang'] ?? null;
        if (is_string($jobLang) && $jobLang !== '') {
            Translator::setLang($jobLang);
        }

        // Si otro audit con la misma URL falló mientras este esperaba en cola,
        // no lo re-ejecutamos — devolvemos el mismo error.
        $recentError = self::findRecentFailure($url);
        if ($recentError !== null) {
            self::markFailed($auditId, $recentError);
            AuditProgress::failed($auditId, $recentError);
            return;
        }

        // Límite de attempts — defensa ante loops (hoy attempts solo crece en
        // dequeueNext, pero si en el futuro agregamos retry automático, esto
        // evita que un job problemático se quede dando vueltas para siempre).
        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $maxAttempts = (int) ($defaults['audit_max_attempts'] ?? 3);
        if (((int) ($job['attempts'] ?? 0)) > $maxAttempts) {
            $msg = 'El análisis se abandonó tras varios intentos fallidos.';
            self::markFailed($auditId, $msg);
            AuditProgress::failed($auditId, $msg);
            return;
        }

        AuditProgress::update($auditId, [
            'status' => 'running',
            'currentStep' => 'init',
            'completedSteps' => 0,
            'totalSteps' => 12,
            'startedAt' => time(),
        ]);

        try {
            $orchestrator = new AuditOrchestrator($url, $leadData, null, $auditId);
            $result = $orchestrator->run();
        } catch (RuntimeException $e) {
            self::markFailed($auditId, $e->getMessage());
            AuditProgress::failed($auditId, $e->getMessage());
            return;
        } catch (Throwable $e) {
            Logger::error('processJob audit error: ' . $e->getMessage(), ['audit_id' => $auditId, 'url' => $url]);
            self::markFailed($auditId, 'Error al analizar el sitio.');
            AuditProgress::failed($auditId, 'Ocurrió un error al analizar el sitio. Intenta nuevamente.');
            return;
        }

        // Guardar en tabla audits
        try {
            $db = Database::getInstance();
            $waterfallData = $result['waterfall'] ?? [];
            $extendedPerf = $result['extendedPerf'] ?? [];
            $resultForStorage = $result;
            unset($resultForStorage['waterfall'], $resultForStorage['extendedPerf']);
            $resultJson = JsonStore::encode($resultForStorage);
            $perfData = [
                'waterfall' => $waterfallData,
                'crux' => $extendedPerf['crux'] ?? null,
                'resourceBreakdown' => $extendedPerf['resourceBreakdown'] ?? [],
                'lighthouseAudits' => $extendedPerf['lighthouseAudits'] ?? [],
                'lcpElement' => $extendedPerf['lcpElement'] ?? null,
                'clsElements' => $extendedPerf['clsElements'] ?? [],
                'mainThreadWork' => $extendedPerf['mainThreadWork'] ?? [],
            ];
            $waterfallJson = JsonStore::encode($perfData);

            $db->execute(
                "INSERT INTO audits (id, url, domain, lead_name, lead_email, lead_whatsapp, lead_company, global_score, global_level, is_wordpress, scan_duration_ms, result_json, waterfall_json, ip_address, user_id, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $result['id'], $result['url'], $result['domain'],
                    $leadData['leadName'] ?? null, $leadData['leadEmail'] ?? null,
                    $leadData['leadWhatsapp'] ?? null, $leadData['leadCompany'] ?? null,
                    $result['globalScore'], $result['globalLevel'],
                    $result['isWordPress'] ? 1 : 0, $result['scanDurationMs'],
                    $resultJson, $waterfallJson, $ip,
                    $leadData['userId'] ?? null,
                    $leadData['projectId'] ?? null,
                ]
            );

            // Reconciliar checklist vivo también desde el drain worker. Mismo
            // comportamiento que audit.php para que un audit encolado resulte
            // en el mismo estado final del checklist que uno ejecutado inline.
            if (!empty($leadData['projectId'])) {
                try {
                    Project::reconcileChecklist(
                        $db,
                        (int) $leadData['projectId'],
                        Project::flattenMetrics($resultForStorage)
                    );
                } catch (Throwable $e) {
                    Logger::warning('Project::reconcileChecklist falló en queue: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Logger::error('processJob error guardando: ' . $e->getMessage());
            self::markFailed($auditId, 'Error guardando el resultado.');
            AuditProgress::failed($auditId, 'Error guardando el resultado. Intenta nuevamente.');
            return;
        }

        // Notificar email si hay lead (solo aplica en modo 'upfront' — en
        // 'gated' el lead se captura después vía capture-lead.php, que
        // dispara su propia notificación).
        self::notifyLead([
            'domain' => $result['domain'],
            'url' => $result['url'],
            'globalScore' => $result['globalScore'],
            'globalLevel' => $result['globalLevel'],
        ], $leadData);

        self::markCompleted($auditId);
        AuditProgress::completed($auditId, 12);
    }

    /**
     * Envía al prospecto un email con el link a su informe completo.
     * Lo usa el modo gated del bloque embed y el modo gated en general:
     * tras dejar su email, recibe en su correo el acceso directo al audit.
     * Refuerza el funnel (necesita poner un email real) y le da una razón
     * para abrir el correo en la bandeja de entrada.
     *
     * Lee la URL base de settings.app_url; si no, falla en silencio sin
     * bloquear el flujo (la notificación al admin ya se hizo).
     */
    public static function sendLeadAuditEmail(string $auditId, string $toEmail, array $audit): void {
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return;

        try {
            $db = Database::getInstance();

            $base = (string) $db->scalar("SELECT value FROM settings WHERE `key` = 'app_url'");
            $base = trim($base);
            if ($base === '' || !preg_match('#^https?://#i', $base)) {
                // Sin URL base no podemos armar un link absoluto que el
                // cliente de email pueda hacer clickable. Mejor abortar
                // que mandar un link relativo inservible.
                Logger::warning('sendLeadAuditEmail saltado: settings.app_url vacío o inválido');
                return;
            }
            $base = rtrim($base, '/');
            $link = "$base/results/" . $auditId;

            $domain = $audit['domain'] ?? '';
            $score = $audit['globalScore'] ?? '?';
            $companyName = (string) $db->scalar("SELECT value FROM settings WHERE `key` = 'company_name'") ?: 'Imagina Audit';

            $subject = "Tu informe de $domain está listo (Score: $score/100)";
            $body = "Hola,\n\n"
                . "Gracias por usar $companyName. El informe completo de $domain ya está listo.\n\n"
                . "Score global: $score/100\n\n"
                . "Puedes ver el informe completo aquí:\n$link\n\n"
                . "El informe incluye: análisis de seguridad, rendimiento, SEO, WordPress, móvil, infraestructura y conversión, con el plan de soluciones recomendado.\n\n"
                . "Saludos,\nEl equipo de $companyName\n";

            Mailer::send($toEmail, $subject, $body);
        } catch (Throwable $e) {
            Logger::warning('sendLeadAuditEmail error: ' . $e->getMessage());
        }
    }

    /**
     * Envía la notificación de "nuevo lead" al email del admin configurado
     * en settings. No-op si no hay email/WhatsApp en el lead o si no hay
     * destino configurado. Reutilizado por processJob (modo upfront) y
     * capture-lead.php (modo gated / widget inline).
     */
    public static function notifyLead(array $audit, array $leadData): void {
        try {
            $leadEmail = trim($leadData['leadEmail'] ?? '');
            $leadWhatsapp = trim($leadData['leadWhatsapp'] ?? '');
            if (!$leadEmail && !$leadWhatsapp) return;

            $db = Database::getInstance();
            $notifRow = $db->queryOne("SELECT value FROM settings WHERE `key` = 'lead_notification_email'");
            $notifEmail = $notifRow['value'] ?? '';
            if (empty($notifEmail) || !filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) return;

            $score = $audit['globalScore'] ?? '?';
            $leadName = trim($leadData['leadName'] ?? '') ?: 'No proporcionado';
            $leadCompany = trim($leadData['leadCompany'] ?? '') ?: 'No proporcionado';
            $subject = "Nuevo lead: {$audit['domain']} (Score: $score/100)";
            $body = "Nuevo lead capturado en Imagina Audit\n\n"
                . "Sitio: {$audit['url']}\n"
                . "Score: $score/100 ({$audit['globalLevel']})\n\n"
                . "Nombre: $leadName\nEmail: " . ($leadEmail ?: 'No proporcionado') . "\n"
                . "WhatsApp: " . ($leadWhatsapp ?: 'No proporcionado') . "\n"
                . "Empresa: $leadCompany\nFecha: " . date('d/m/Y H:i') . "\n";
            Mailer::send($notifEmail, $subject, $body);
        } catch (Throwable $e) {
            Logger::warning('Error enviando notificación de lead: ' . $e->getMessage());
        }
    }
}
