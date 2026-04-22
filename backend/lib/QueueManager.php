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
            $row = $db->queryOne("SELECT value FROM settings WHERE key = 'audit_max_concurrent'");
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
                 AND completed_at > datetime('now', ?)
                 ORDER BY completed_at DESC LIMIT 1",
                [$url, "-$windowMin minutes"]
            );
            if ($row && !empty($row['error_message'])) {
                return $row['error_message'];
            }
        } catch (Throwable $e) {
            Logger::warning('findRecentFailure falló: ' . $e->getMessage());
        }
        return null;
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
                 AND completed_at > datetime('now', ?)",
                [$url, "-$windowMinutes minutes"]
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
            $db->execute(
                "INSERT INTO audit_jobs (audit_id, url, lead_data_json, status, ip_address, started_at) VALUES (?, ?, ?, 'running', ?, datetime('now'))",
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
            Database::getInstance()->execute(
                "UPDATE audit_jobs SET status = 'completed', completed_at = datetime('now') WHERE audit_id = ?",
                [$auditId]
            );
        } catch (Throwable $e) {
            Logger::warning('QueueManager markCompleted falló: ' . $e->getMessage());
        }
    }

    public static function markFailed(string $auditId, string $error): void {
        try {
            Database::getInstance()->execute(
                "UPDATE audit_jobs SET status = 'failed', completed_at = datetime('now'), error_message = ? WHERE audit_id = ?",
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
        $pdo = $db->getPdo();

        try {
            $pdo->beginTransaction();

            // Re-verificar slot dentro de la transacción
            $running = (int) $db->scalar("SELECT COUNT(*) FROM audit_jobs WHERE status = 'running'");
            $max = self::getMaxConcurrent();
            if ($running >= $max) {
                $pdo->rollback();
                return null;
            }

            $job = $db->queryOne(
                "SELECT * FROM audit_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1"
            );
            if (!$job) {
                $pdo->rollback();
                return null;
            }

            // Defensa: si el job ya superó max attempts, marcarlo failed y pasar.
            // Ocurre si un job quedó running, se reapeó, se re-encoló manual y
            // falla de nuevo. No queremos retry eterno de URLs problemáticas.
            $defaults = require dirname(__DIR__) . '/config/defaults.php';
            $maxAttempts = (int) ($defaults['audit_max_attempts'] ?? 3);
            if (((int) $job['attempts']) >= $maxAttempts) {
                $db->execute(
                    "UPDATE audit_jobs SET status = 'failed', error_message = 'Abandonado tras ' || attempts || ' intentos.', completed_at = datetime('now') WHERE id = ?",
                    [$job['id']]
                );
                $pdo->commit();
                AuditProgress::failed($job['audit_id'], 'El análisis falló repetidamente y fue abandonado. Contacta a soporte si persiste.');
                // Recursive call para intentar el siguiente; evita bucle porque
                // este job ya se marcó failed y no volverá a salir del query.
                return self::dequeueNext();
            }

            $db->execute(
                "UPDATE audit_jobs SET status = 'running', started_at = datetime('now'), attempts = attempts + 1 WHERE id = ?",
                [$job['id']]
            );
            $pdo->commit();
            $job['attempts'] = ((int) $job['attempts']) + 1;
            return $job;
        } catch (Throwable $e) {
            try { $pdo->rollback(); } catch (Throwable $e2) {}
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
            $count = $db->execute(
                "UPDATE audit_jobs SET status = 'failed', error_message = 'Job huérfano: proceso PHP murió antes de completar el audit.', completed_at = datetime('now') WHERE status = 'running' AND started_at IS NOT NULL AND started_at < datetime('now', ?)",
                ["-$stale seconds"]
            );
            if ($count > 0) {
                Logger::warning("Reaped $count stale audit jobs");
                // Marcar también el AuditProgress de estos jobs como 'failed'
                // para que el frontend deje de hacer polling indefinidamente
                $staleJobs = $db->query(
                    "SELECT audit_id FROM audit_jobs WHERE status = 'failed' AND error_message LIKE 'Job huérfano%' AND completed_at > datetime('now', '-1 minute')"
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

        // Notificar email si hay lead
        try {
            $leadEmail = trim($leadData['leadEmail'] ?? '');
            $leadWhatsapp = trim($leadData['leadWhatsapp'] ?? '');
            if ($leadEmail || $leadWhatsapp) {
                $db = Database::getInstance();
                $notifRow = $db->queryOne("SELECT value FROM settings WHERE key = 'lead_notification_email'");
                $notifEmail = $notifRow['value'] ?? '';
                if (!empty($notifEmail) && filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
                    $score = $result['globalScore'];
                    $leadName = trim($leadData['leadName'] ?? '') ?: 'No proporcionado';
                    $leadCompany = trim($leadData['leadCompany'] ?? '') ?: 'No proporcionado';
                    $subject = "Nuevo lead: {$result['domain']} (Score: $score/100)";
                    $body = "Nuevo lead capturado en Imagina Audit\n\n"
                        . "Sitio: {$result['url']}\n"
                        . "Score: $score/100 ({$result['globalLevel']})\n\n"
                        . "Nombre: $leadName\nEmail: " . ($leadEmail ?: 'No proporcionado') . "\n"
                        . "WhatsApp: " . ($leadWhatsapp ?: 'No proporcionado') . "\n"
                        . "Empresa: $leadCompany\nFecha: " . date('d/m/Y H:i') . "\n";
                    Mailer::send($notifEmail, $subject, $body);
                }
            }
        } catch (Throwable $e) {
            Logger::warning('Error enviando notificación de lead: ' . $e->getMessage());
        }

        self::markCompleted($auditId);
        AuditProgress::completed($auditId, 12);
    }
}
