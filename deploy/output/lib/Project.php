<?php
/**
 * Helpers compartidos del módulo de proyectos (P5).
 *
 * Centraliza la lógica que se consume desde varios endpoints para no
 * duplicar queries ni rules — en particular:
 *   - extracción de dominio a partir de URL
 *   - auto-attach de un audit recién guardado al proyecto del user
 *   - reconciliación del checklist vivo tras cada audit (🔴→🟢 automático,
 *     reabre si el user lo había cerrado a mano)
 *   - diff evolutivo (WP version, plugins) entre los últimos 2 audits
 *   - generación y rotación del share_token
 */

class Project {
    /**
     * Extrae el dominio canónico de una URL. Baja a minúsculas, quita `www.`
     * y cualquier puerto/credencial. Si la URL es basura, devuelve ''.
     */
    public static function domainFromUrl(string $url): string {
        $parsed = @parse_url($url);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') return '';
        if (str_starts_with($host, 'www.')) $host = substr($host, 4);
        return $host;
    }

    /**
     * Busca un proyecto del user cuya URL matchee exactamente la URL del audit.
     * Retorna el project_id o null. El match es por URL exacta (case-insensitive
     * en el host + path). No caemos al dominio general porque el modelo A que
     * elegimos es 1 proyecto = 1 URL.
     */
    public static function findMatchingProject(Database $db, int $userId, string $url): ?int {
        $normalizedUrl = self::normalizeUrl($url);
        try {
            $row = $db->queryOne(
                "SELECT id FROM projects WHERE user_id = ? AND LOWER(url) = ? LIMIT 1",
                [$userId, $normalizedUrl]
            );
            return $row ? (int) $row['id'] : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Normaliza una URL para compararla con project.url (ambos se almacenan
     * ya lowercased por domainFromUrl del lado de insert; la comparación acá
     * aplica la misma transformación al input).
     */
    public static function normalizeUrl(string $url): string {
        $parsed = @parse_url($url);
        if (!$parsed || empty($parsed['host'])) return strtolower(trim($url));
        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);
        if (str_starts_with($host, 'www.')) $host = substr($host, 4);
        $path = rtrim($parsed['path'] ?? '', '/');
        if ($path === '') $path = '';
        return "$scheme://$host$path";
    }

    /**
     * Reconcilia el checklist vivo contra el último audit del proyecto.
     *
     * Reglas:
     *   - Para cada métrica del último audit:
     *     * si level ∈ {critical, warning}: debe existir como 'open' salvo que
     *       el user la haya cerrado a mano (user_modified=1 && status='done').
     *       Si estaba 'done' pero sin user_modified, volver a 'open'.
     *     * si level ∈ {good, excellent}: marcar 'done' solo si el item no
     *       estaba tocado por el user (respetar `ignored` también).
     *   - Métricas que ya no aparecen en el nuevo audit: dejarlas como estaban
     *     (no borrar — puede ser que el audit falló parcialmente y queremos
     *     conservar histórico). El user decide.
     */
    public static function reconcileChecklist(Database $db, int $projectId, array $metrics): void {
        // metrics viene como array de ModuleResult.metrics combinados:
        //   [{ id, level, name, ... }, ...]
        // Cargar estado actual del checklist
        try {
            $rows = $db->query(
                "SELECT metric_id, status, user_modified FROM project_checklist_items WHERE project_id = ?",
                [$projectId]
            );
        } catch (Throwable $e) {
            return;
        }
        $current = [];
        foreach ($rows as $r) {
            $current[$r['metric_id']] = [
                'status' => $r['status'],
                'userModified' => (int) $r['user_modified'] === 1,
            ];
        }

        foreach ($metrics as $m) {
            $metricId = (string) ($m['id'] ?? '');
            if ($metricId === '') continue;
            $level = (string) ($m['level'] ?? 'info');
            $needsAction = in_array($level, ['critical', 'warning'], true);
            $isOk = in_array($level, ['good', 'excellent'], true);
            $existing = $current[$metricId] ?? null;

            if ($existing === null) {
                // Nueva métrica en el radar. Solo insertamos si requiere acción —
                // no poblamos todo el catálogo en el checklist.
                if ($needsAction) {
                    try {
                        $db->execute(
                            "INSERT INTO project_checklist_items (project_id, metric_id, status, severity, user_modified, updated_at) VALUES (?, ?, 'open', ?, 0, datetime('now'))",
                            [$projectId, $metricId, $level]
                        );
                    } catch (Throwable $e) { /* UNIQUE race, ignorar */ }
                }
                continue;
            }

            // Ya existe en el checklist
            if ($isOk) {
                // Métrica resuelta. Solo auto-marcamos done si el user no tocó
                // (preserva 'ignored' manual y 'done' manual).
                if (!$existing['userModified'] && $existing['status'] !== 'ignored') {
                    $db->execute(
                        "UPDATE project_checklist_items SET status = 'done', severity = ?, completed_at = datetime('now'), updated_at = datetime('now') WHERE project_id = ? AND metric_id = ?",
                        [$level, $projectId, $metricId]
                    );
                } else {
                    // Solo actualizar severity (última conocida)
                    $db->execute(
                        "UPDATE project_checklist_items SET severity = ?, updated_at = datetime('now') WHERE project_id = ? AND metric_id = ?",
                        [$level, $projectId, $metricId]
                    );
                }
            } elseif ($needsAction) {
                // Métrica que volvió a fallar
                if ($existing['status'] === 'done' && !$existing['userModified']) {
                    // La habíamos marcado done automáticamente antes — re-abrir.
                    $db->execute(
                        "UPDATE project_checklist_items SET status = 'open', severity = ?, completed_at = NULL, updated_at = datetime('now') WHERE project_id = ? AND metric_id = ?",
                        [$level, $projectId, $metricId]
                    );
                } else {
                    // Sea 'open' o 'done'/'ignored' manual del user, solo refrescar severity
                    $db->execute(
                        "UPDATE project_checklist_items SET severity = ?, updated_at = datetime('now') WHERE project_id = ? AND metric_id = ?",
                        [$level, $projectId, $metricId]
                    );
                }
            }
        }
    }

    /**
     * Aplana los metrics de todos los módulos del result en un solo array
     * — útil para alimentar reconcileChecklist() desde audit.php.
     */
    public static function flattenMetrics(array $result): array {
        $out = [];
        foreach ($result['modules'] ?? [] as $mod) {
            foreach ($mod['metrics'] ?? [] as $m) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /**
     * Diff evolutivo entre los 2 últimos audits de un proyecto.
     * Retorna null si hay menos de 2 audits o si el result_json está corrupto.
     *
     * El diff enfoca en:
     *   - delta del global_score
     *   - cambios en el count de críticos/warnings
     *   - cambios en metadata de WordPress (versión, tema activo)
     *   - plugins nuevos/quitados entre audits (si hay snapshot, si no usa lo
     *     detectado públicamente por el analyzer)
     */
    public static function lastAuditsDiff(Database $db, int $projectId): ?array {
        try {
            $rows = $db->query(
                "SELECT id, result_json, global_score, created_at FROM audits WHERE project_id = ? ORDER BY created_at DESC LIMIT 2",
                [$projectId]
            );
        } catch (Throwable $e) {
            return null;
        }
        if (count($rows) < 2) return null;

        $latest = JsonStore::decode($rows[0]['result_json']);
        $previous = JsonStore::decode($rows[1]['result_json']);
        if (!is_array($latest) || !is_array($previous)) return null;

        return [
            'latestAuditId' => $rows[0]['id'],
            'previousAuditId' => $rows[1]['id'],
            'scoreDelta' => (int) $latest['globalScore'] - (int) $previous['globalScore'],
            'latestScore' => (int) $latest['globalScore'],
            'previousScore' => (int) $previous['globalScore'],
            'issuesDelta' => [
                'critical' => (int) ($latest['totalIssues']['critical'] ?? 0) - (int) ($previous['totalIssues']['critical'] ?? 0),
                'warning'  => (int) ($latest['totalIssues']['warning']  ?? 0) - (int) ($previous['totalIssues']['warning']  ?? 0),
            ],
            'wordpress' => self::wpMetadataDiff($latest, $previous),
            'plugins'   => self::pluginsDiff($latest, $previous),
        ];
    }

    /**
     * Extrae info de WP de un module result (nombre del módulo = 'wordpress').
     * Retorna diff { previousVersion, latestVersion, changed, theme } o null.
     */
    private static function wpMetadataDiff(array $latest, array $previous): ?array {
        $getVersion = function (array $result): ?string {
            foreach ($result['modules'] ?? [] as $mod) {
                if (($mod['id'] ?? '') !== 'wordpress') continue;
                foreach ($mod['metrics'] ?? [] as $m) {
                    if (($m['id'] ?? '') === 'wp_version') {
                        $v = $m['value'] ?? null;
                        return is_string($v) && $v !== '' ? $v : null;
                    }
                }
            }
            return null;
        };
        $prevV = $getVersion($previous);
        $latV  = $getVersion($latest);
        if ($prevV === null && $latV === null) return null;
        return [
            'previousVersion' => $prevV,
            'latestVersion'   => $latV,
            'changed'         => $prevV !== $latV,
        ];
    }

    /**
     * Diff simple de plugins detectados. Usa el details.list del metric
     * `plugins` del módulo wordpress cuando está disponible. Si el user no
     * subió snapshot, igual capturamos los plugins que el analyzer público
     * detectó por footprint.
     */
    private static function pluginsDiff(array $latest, array $previous): array {
        $getPlugins = function (array $result): array {
            foreach ($result['modules'] ?? [] as $mod) {
                if (($mod['id'] ?? '') !== 'wordpress') continue;
                foreach ($mod['metrics'] ?? [] as $m) {
                    if (($m['id'] ?? '') === 'plugins') {
                        $list = $m['details']['list'] ?? $m['details']['plugins'] ?? [];
                        if (!is_array($list)) continue;
                        $slugs = [];
                        foreach ($list as $p) {
                            $slug = is_array($p) ? (string) ($p['slug'] ?? $p['name'] ?? '') : (string) $p;
                            if ($slug !== '') $slugs[] = strtolower($slug);
                        }
                        return array_values(array_unique($slugs));
                    }
                }
            }
            return [];
        };
        $prevSet = $getPlugins($previous);
        $latSet  = $getPlugins($latest);
        return [
            'added'   => array_values(array_diff($latSet, $prevSet)),
            'removed' => array_values(array_diff($prevSet, $latSet)),
            'kept'    => array_values(array_intersect($prevSet, $latSet)),
        ];
    }

    /**
     * Genera un share_token único (32 hex chars = 128 bits). Loopea por si
     * hay colisión en el índice único (extremadamente improbable).
     */
    public static function generateShareToken(Database $db): string {
        for ($i = 0; $i < 5; $i++) {
            $token = bin2hex(random_bytes(16));
            $exists = $db->scalar("SELECT 1 FROM projects WHERE share_token = ?", [$token]);
            if (!$exists) return $token;
        }
        // Si llegamos acá, hay un problema serio con el RNG
        throw new RuntimeException('Could not allocate a unique share token');
    }

    /**
     * Cuenta los proyectos actuales del user. Usado para enforcement
     * de plans.max_projects (0=ilimitado) al crear nuevos.
     */
    public static function countForUser(Database $db, int $userId): int {
        try {
            return (int) $db->scalar("SELECT COUNT(*) FROM projects WHERE user_id = ?", [$userId]);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
