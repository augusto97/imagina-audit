<?php
/**
 * PerfLab — Laboratorio de rendimiento.
 *
 * Mediciones repetidas SOLO de PageSpeed Insights sobre una URL, para
 * iterar optimizaciones (cambio algo en el sitio → mido → comparo) sin
 * generar auditorías completas, leads, emails ni pasar por la caché de 24h.
 *
 * Modelo de ejecución "por rondas guiadas por el cliente":
 *   - create() inserta la prueba en estado `pending`.
 *   - El navegador llama step() en bucle. Cada step reclama la prueba de
 *     forma atómica y ejecuta UNA ronda (mobile y/o desktop en paralelo).
 *   - Tras la última ronda se calcula la mediana y la prueba queda
 *     `completed`.
 *
 * Así no dependemos de procesos en background (fastcgi_finish_request,
 * cron, exec): el progreso es real y si el admin cierra la pestaña la
 * prueba queda `pending` y se puede reanudar. Si el proxy (nginx) corta la
 * petición a los 60s, PHP sigue (ignore_user_abort) y el siguiente step ve
 * la prueba `measuring` y espera.
 */
class PerfLab {
    public const STRATEGIES = ['mobile', 'desktop', 'both'];
    public const MAX_RUNS = 3;

    /** Timeout de cada llamada a PSI. Sitios pesados tardan 40-60s. */
    private const PSI_TIMEOUT = 90;

    /** Una ronda `measuring` sin actualizarse en este tiempo se considera muerta. */
    private const STALE_SECONDS = 150;

    /** Las pruebas más viejas que esto se purgan al crear una nueva. */
    private const RETENTION_DAYS = 90;

    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public static function hasApiKey(): bool {
        return PageSpeed::resolveApiKey() !== '';
    }

    /**
     * Crea una prueba. $url ya validada (UrlValidator). Devuelve la fila pública.
     */
    public function create(string $url, string $strategy, int $runs, string $note = ''): array {
        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new InvalidArgumentException('strategy');
        }
        $runs = max(1, min(self::MAX_RUNS, $runs));
        $note = mb_substr(trim($note), 0, 500);
        $now = date('Y-m-d H:i:s');

        // Purga de pruebas viejas (barata, por índice de created_at).
        try {
            $this->db->execute("DELETE FROM perf_runs WHERE created_at < ?", [$this->db->nowMinus(self::RETENTION_DAYS * 86400)]);
        } catch (Throwable $e) { /* no bloquea la creación */ }

        $this->db->execute(
            "INSERT INTO perf_runs (url, url_hash, strategy, runs_requested, runs_done, attempts, status, note, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 0, 'pending', ?, ?, ?)",
            [$url, sha1($url), $strategy, $runs, $note !== '' ? $note : null, $now, $now]
        );
        $id = (int) $this->db->lastInsertId();
        return $this->publicRow($this->fetchRow($id));
    }

    /**
     * Ejecuta la siguiente ronda de la prueba si nadie más la está
     * ejecutando. Devuelve la fila pública (o null si no existe).
     */
    public function step(int $id): ?array {
        $row = $this->fetchRow($id);
        if (!$row) return null;
        if (in_array($row['status'], ['completed', 'failed'], true)) {
            return $this->publicRow($row);
        }

        // Reclamo atómico: solo una petición ejecuta cada ronda.
        $now = date('Y-m-d H:i:s');
        $claimed = $this->db->execute(
            "UPDATE perf_runs SET status = 'measuring', attempts = attempts + 1, updated_at = ?
             WHERE id = ? AND (status = 'pending' OR (status = 'measuring' AND (updated_at IS NULL OR updated_at < ?)))",
            [$now, $id, $this->db->nowMinus(self::STALE_SECONDS)]
        );
        if ($claimed < 1) {
            // Otra petición está midiendo esta ronda — el cliente reintenta.
            return $this->publicRow($this->fetchRow($id) ?? $row);
        }

        $row = $this->fetchRow($id);
        $requested = (int) $row['runs_requested'];
        $done = (int) $row['runs_done'];

        // Si el servidor mata el proceso en cada ronda (timeout del hosting),
        // los reintentos se acumulan: cortamos para no quedar en bucle.
        if ((int) $row['attempts'] > $requested + 2) {
            $this->fail($id, Translator::t('admin_api.perf_lab.server_timeout'));
            return $this->publicRow($this->fetchRow($id));
        }

        @set_time_limit(self::PSI_TIMEOUT * 2);
        @ignore_user_abort(true);

        $strategies = $row['strategy'] === 'both' ? ['mobile', 'desktop'] : [$row['strategy']];
        $raw = JsonStore::decode($row['raw_json']) ?? ['runs' => [], 'errors' => []];
        $round = $done + 1;

        $roundResults = $this->measureRound($row['url'], $strategies);
        $okInRound = 0;
        foreach ($roundResults as $strategy => $res) {
            if ($res['ok']) {
                $raw['runs'][$strategy][] = $res['run'];
                $okInRound++;
            } else {
                $raw['errors'][] = ['round' => $round, 'strategy' => $strategy, 'message' => $res['error']];
            }
        }

        // Primera ronda sin ningún resultado: el error de PSI suele ser
        // determinista (URL inaccesible, cuota agotada) → fallar ya.
        if ($okInRound === 0 && $round === 1) {
            $msg = $raw['errors'][0]['message'] ?? 'PageSpeed error';
            $this->fail($id, $msg);
            return $this->publicRow($this->fetchRow($id));
        }

        $done = $round;
        if ($done >= $requested) {
            $this->finalize($id, $row, $raw);
        } else {
            $this->db->execute(
                "UPDATE perf_runs SET status = 'pending', runs_done = ?, raw_json = ?, updated_at = ? WHERE id = ?",
                [$done, JsonStore::encode($raw), date('Y-m-d H:i:s'), $id]
            );
        }
        return $this->publicRow($this->fetchRow($id));
    }

    /**
     * Una ronda: todas las estrategias en paralelo.
     * @return array<string, array{ok: bool, run?: array, error?: string}>
     */
    private function measureRound(string $url, array $strategies): array {
        $apiKey = PageSpeed::resolveApiKey();
        $locale = Translator::getLang() ?: 'es';
        $urls = [];
        foreach ($strategies as $s) {
            $urls[$s] = PageSpeed::buildUrl($url, $s, $apiKey, $locale);
        }
        $t0 = microtime(true);
        $responses = $this->fetchPsi($urls);
        $elapsed = (int) round((microtime(true) - $t0) * 1000);

        $out = [];
        foreach ($strategies as $s) {
            $response = $responses[$s] ?? [];
            $err = PageSpeed::errorMessage($response);
            if ($err !== null) {
                $out[$s] = ['ok' => false, 'error' => $err === 'timeout' ? Translator::t('admin_api.perf_lab.psi_timeout') : $err];
                continue;
            }
            $parsed = PageSpeed::parse($response, $s, $url);
            if ($parsed['score'] === null) {
                $out[$s] = ['ok' => false, 'error' => Translator::t('admin_api.perf_lab.psi_no_score')];
                continue;
            }
            // Metadatos que parse() no extrae (versión, URL final, avisos).
            $data = json_decode($response['body'] ?? '', true) ?: [];
            $lh = $data['lighthouseResult'] ?? [];
            $parsed['meta'] = [
                'finalUrl' => $lh['finalDisplayedUrl'] ?? ($lh['finalUrl'] ?? $url),
                'lighthouseVersion' => $lh['lighthouseVersion'] ?? null,
                'warnings' => array_values(array_filter(array_map('strval', $lh['runWarnings'] ?? []))),
                'measuredAt' => date('c'),
                'apiMs' => $elapsed,
            ];
            unset($data);
            $out[$s] = ['ok' => true, 'run' => $parsed];
        }
        return $out;
    }

    /**
     * Llamada HTTP a PSI (todas las estrategias en paralelo). Aislada en su
     * propio método para poder sustituirla en tests sin red.
     */
    protected function fetchPsi(array $urls): array {
        return Fetcher::multiGet($urls, self::PSI_TIMEOUT);
    }

    /**
     * Elige la mediana por score de rendimiento (metodología recomendada por
     * Google para PSI) y guarda el resultado compacto.
     */
    private function finalize(int $id, array $row, array $raw): void {
        $result = [
            'url' => $row['url'],
            'strategy' => $row['strategy'],
            'runsRequested' => (int) $row['runs_requested'],
            'strategies' => [],
            'errors' => $raw['errors'] ?? [],
        ];
        $scores = ['mobile' => null, 'desktop' => null];

        $strategies = $row['strategy'] === 'both' ? ['mobile', 'desktop'] : [$row['strategy']];
        foreach ($strategies as $s) {
            $runs = $raw['runs'][$s] ?? [];
            if (empty($runs)) {
                $result['strategies'][$s] = ['error' => $this->firstErrorFor($raw, $s)];
                continue;
            }
            $order = array_keys($runs);
            usort($order, fn($a, $b) => ($runs[$a]['score'] <=> $runs[$b]['score']));
            // n=3 → índice 1 (mediana real); n=2 → el menor (conservador); n=1 → ese.
            $medianKey = $order[intdiv(count($order) - 1, 2)];
            $median = $runs[$medianKey];

            $summaries = [];
            foreach ($runs as $k => $r) {
                $summaries[] = self::summarize($r) + ['isMedian' => $k === $medianKey];
            }

            $result['strategies'][$s] = [
                'summary' => self::summarize($median),
                'runs' => $summaries,
                'meta' => $median['meta'] ?? null,
                'data' => [
                    'networkRequests' => $median['networkRequests'] ?? [],
                    'resourceBreakdown' => $median['resourceBreakdown'] ?? [],
                    'lighthouseAudits' => $median['lighthouseAudits'] ?? [],
                    'opportunities' => $median['opportunities'] ?? [],
                    'lcpElement' => $median['lcpElement'] ?? null,
                    'clsElements' => $median['clsElements'] ?? [],
                    'mainThreadWork' => $median['mainThreadWork'] ?? [],
                    'crux' => $median['crux'] ?? null,
                ],
            ];
            $scores[$s] = $median['score'];
        }

        if ($scores['mobile'] === null && $scores['desktop'] === null) {
            $this->fail($id, $result['errors'][0]['message'] ?? 'PageSpeed error');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            "UPDATE perf_runs SET status = 'completed', runs_done = runs_requested, score_mobile = ?, score_desktop = ?,
                result_json = ?, raw_json = NULL, error = NULL, updated_at = ?, completed_at = ? WHERE id = ?",
            [$scores['mobile'], $scores['desktop'], JsonStore::encode($result), $now, $now, $id]
        );
    }

    private function firstErrorFor(array $raw, string $strategy): string {
        foreach ($raw['errors'] ?? [] as $e) {
            if (($e['strategy'] ?? '') === $strategy) return (string) $e['message'];
        }
        return 'PageSpeed error';
    }

    private function fail(int $id, string $message): void {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            "UPDATE perf_runs SET status = 'failed', error = ?, raw_json = NULL, updated_at = ?, completed_at = ? WHERE id = ?",
            [mb_substr($message, 0, 1000), $now, $now, $id]
        );
    }

    /** Métricas clave de una medición (lo que se compara entre pruebas). */
    public static function summarize(array $run): array {
        $requests = $run['networkRequests'] ?? [];
        $bytes = 0;
        foreach ($requests as $r) $bytes += (int) ($r['transferSize'] ?? 0);
        $num = fn($v) => $v === null ? null : round((float) $v, 4);
        return [
            'score' => $run['score'] ?? null,
            'fcp' => $num($run['fcp'] ?? null),
            'lcp' => $num($run['lcp'] ?? null),
            'cls' => $num($run['cls'] ?? null),
            'tbt' => $num($run['tbt'] ?? null),
            'si' => $num($run['si'] ?? null),
            'tti' => $num($run['tti'] ?? null),
            'ttfb' => $num($run['ttfb'] ?? null),
            'requests' => count($requests),
            'bytes' => $bytes,
        ];
    }

    // ─── Lectura ─────────────────────────────────────────────────────

    /** Detalle completo + resumen de la prueba anterior de la misma URL. */
    public function get(int $id): ?array {
        $row = $this->fetchRow($id);
        if (!$row) return null;
        $out = $this->publicRow($row);
        $out['result'] = $row['status'] === 'completed' ? JsonStore::decode($row['result_json']) : null;

        $out['previous'] = null;
        if ($row['status'] === 'completed') {
            $prev = $this->db->queryOne(
                "SELECT * FROM perf_runs WHERE url_hash = ? AND status = 'completed' AND id < ? ORDER BY id DESC LIMIT 1",
                [$row['url_hash'], $id]
            );
            if ($prev) {
                $prevResult = JsonStore::decode($prev['result_json']) ?? [];
                $summaries = [];
                foreach (($prevResult['strategies'] ?? []) as $s => $data) {
                    if (isset($data['summary'])) $summaries[$s] = $data['summary'];
                }
                $out['previous'] = $this->publicRow($prev) + ['summaries' => $summaries];
            }
        }
        return $out;
    }

    /** Lista de pruebas (más recientes primero), opcionalmente de una URL. */
    public function listRuns(?string $url = null, int $limit = 100): array {
        $limit = max(1, min(300, $limit));
        $cols = "id, url, url_hash, strategy, runs_requested, runs_done, attempts, status, note, score_mobile, score_desktop, error, created_at, updated_at, completed_at";
        $rows = $url !== null && $url !== ''
            ? $this->db->query("SELECT $cols FROM perf_runs WHERE url_hash = ? ORDER BY id DESC LIMIT $limit", [sha1($url)])
            : $this->db->query("SELECT $cols FROM perf_runs ORDER BY id DESC LIMIT $limit");
        return array_map(fn($r) => $this->publicRow($r), $rows);
    }

    /** URLs probadas recientemente (para el selector rápido). */
    public function recentUrls(int $limit = 20): array {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->query(
            "SELECT url, url_hash, COUNT(*) AS total, MAX(created_at) AS last_at
             FROM perf_runs GROUP BY url_hash, url ORDER BY last_at DESC LIMIT $limit"
        );
        return array_map(fn($r) => [
            'url' => $r['url'],
            'total' => (int) $r['total'],
            'lastAt' => $r['last_at'],
        ], $rows);
    }

    public function updateNote(int $id, string $note): bool {
        $note = mb_substr(trim($note), 0, 500);
        return $this->db->execute("UPDATE perf_runs SET note = ? WHERE id = ?", [$note !== '' ? $note : null, $id]) > 0
            || $this->fetchRow($id) !== null;
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM perf_runs WHERE id = ?", [$id]);
    }

    private function fetchRow(int $id): ?array {
        return $this->db->queryOne("SELECT * FROM perf_runs WHERE id = ?", [$id]);
    }

    private function publicRow(?array $r): array {
        if (!$r) return [];
        return [
            'id' => (int) $r['id'],
            'url' => $r['url'],
            'strategy' => $r['strategy'],
            'runsRequested' => (int) $r['runs_requested'],
            'runsDone' => (int) $r['runs_done'],
            'status' => $r['status'],
            'note' => $r['note'],
            'scoreMobile' => $r['score_mobile'] !== null ? (int) $r['score_mobile'] : null,
            'scoreDesktop' => $r['score_desktop'] !== null ? (int) $r['score_desktop'] : null,
            'error' => $r['error'],
            'createdAt' => $r['created_at'],
            'completedAt' => $r['completed_at'],
        ];
    }
}
