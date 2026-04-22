<?php
/**
 * Health check de crons del sistema.
 *
 * Cada script cron llama a CronHealth::markRun('name') al terminar con
 * éxito. Aquí declaramos los crons esperados con su periodicidad y
 * evaluamos si están corriendo a tiempo o si se quedaron colgados.
 *
 * Expuesto en /api/diag.php (y la página /admin/health) para que el
 * operador vea en qué estado está cada tarea automática.
 */

class CronHealth {
    /**
     * Catálogo de crons esperados. `interval_seconds` es el tiempo entre
     * ejecuciones programadas; `grace_factor` cuánto toleramos de retraso
     * antes de marcar como warning (típicamente 1.5x).
     */
    public static function catalog(): array {
        return [
            'drain-queue' => [
                'label'            => Translator::t('cron.drain_queue.label'),
                'description'      => Translator::t('cron.drain_queue.description'),
                'interval_seconds' => 60,
                'grace_factor'     => 10,       // puede tardar más si no hay jobs
                'critical_factor'  => 60,       // 1 hora sin correr = crítico
            ],
            'cleanup' => [
                'label'            => Translator::t('cron.cleanup.label'),
                'description'      => Translator::t('cron.cleanup.description'),
                'interval_seconds' => 86400,
                'grace_factor'     => 1.5,      // ok hasta 36h
                'critical_factor'  => 3,        // 3 días sin correr = crítico
            ],
            'vacuum' => [
                'label'            => Translator::t('cron.vacuum.label'),
                'description'      => Translator::t('cron.vacuum.description'),
                'interval_seconds' => 604800,   // 7 días
                'grace_factor'     => 1.5,
                'critical_factor'  => 3,
            ],
            'update-vulnerabilities' => [
                'label'            => Translator::t('cron.update_vulnerabilities.label'),
                'description'      => Translator::t('cron.update_vulnerabilities.description'),
                'interval_seconds' => 86400,
                'grace_factor'     => 2,
                'critical_factor'  => 7,
            ],
            'refresh-plugin-vault' => [
                'label'            => Translator::t('cron.refresh_plugin_vault.label'),
                'description'      => Translator::t('cron.refresh_plugin_vault.description'),
                'interval_seconds' => 2592000,  // 30 días
                'grace_factor'     => 1.5,
                'critical_factor'  => 3,
            ],
        ];
    }

    public static function markRun(string $name, ?int $durationSec = null, ?string $note = null): void {
        try {
            $db = Database::getInstance();
            $payload = ['at' => date('c'), 'duration' => $durationSec, 'note' => $note];
            $db->execute(
                "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                ["cron_last_run_$name", json_encode($payload, JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::warning("CronHealth.markRun($name) falló: " . $e->getMessage());
            }
        }
    }

    public static function getLastRun(string $name): ?array {
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE key = ?", ["cron_last_run_$name"]);
            if (!$row) return null;
            $decoded = json_decode((string) $row['value'], true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Status por cron: ok / warning / critical / never.
     */
    public static function status(): array {
        $out = [];
        foreach (self::catalog() as $name => $info) {
            $last = self::getLastRun($name);
            $status = 'never';
            $ageSec = null;
            $message = Translator::t('cron.msg.never');

            if ($last !== null && !empty($last['at'])) {
                $lastTs = strtotime($last['at']);
                if ($lastTs !== false) {
                    $ageSec = time() - $lastTs;
                    $expected = $info['interval_seconds'];
                    $graceThreshold   = $expected * ($info['grace_factor'] ?? 1.5);
                    $criticalThreshold = $expected * ($info['critical_factor'] ?? 5);

                    if ($ageSec <= $graceThreshold) {
                        $status = 'ok';
                        $message = Translator::t('cron.msg.ok');
                    } elseif ($ageSec <= $criticalThreshold) {
                        $status = 'warning';
                        $message = Translator::t('cron.msg.warning');
                    } else {
                        $status = 'critical';
                        $message = Translator::t('cron.msg.critical');
                    }
                }
            }

            $out[] = [
                'name'             => $name,
                'label'            => $info['label'],
                'description'      => $info['description'],
                'intervalSeconds'  => $info['interval_seconds'],
                'intervalHuman'    => self::humanInterval($info['interval_seconds']),
                'lastRunAt'        => $last['at'] ?? null,
                'lastDurationSec'  => $last['duration'] ?? null,
                'ageSeconds'       => $ageSec,
                'ageHuman'         => $ageSec !== null ? self::humanInterval($ageSec) : null,
                'status'           => $status,
                'message'          => $message,
            ];
        }
        return $out;
    }

    public static function summary(): array {
        $status = self::status();
        $counts = ['ok' => 0, 'warning' => 0, 'critical' => 0, 'never' => 0];
        foreach ($status as $s) $counts[$s['status']]++;
        $overallOk = ($counts['critical'] + $counts['warning'] + $counts['never']) === 0;
        return ['overallOk' => $overallOk, 'counts' => $counts, 'items' => $status];
    }

    private static function humanInterval(int $sec): string {
        if ($sec < 60)     return Translator::t('cron.unit.seconds', ['count' => $sec]);
        if ($sec < 3600)   return Translator::t('cron.unit.minutes', ['count' => round($sec / 60)]);
        if ($sec < 86400)  return Translator::t('cron.unit.hours',   ['count' => round($sec / 3600, 1)]);
        if ($sec < 604800) return Translator::t('cron.unit.days',    ['count' => round($sec / 86400, 1)]);
        return Translator::t('cron.unit.weeks', ['count' => round($sec / 604800, 1)]);
    }
}
