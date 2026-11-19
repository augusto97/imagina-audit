-- ════════════════════════════════════════════════════════════════════
-- 0004_perf_lab — Laboratorio de rendimiento
-- ────────────────────────────────────────────────────────────────────
-- Pruebas repetidas SOLO de PageSpeed (sin auditoría completa, sin lead,
-- sin caché de 24h). Pensado para iterar optimizaciones sobre un mismo
-- sitio muchas veces al día y comparar antes/después.
--
--   url_hash   sha1 de la URL normalizada → índice compacto (una URL
--              larga no entra en un índice utf8mb4 de MySQL 5.7).
--   strategy   mobile | desktop | both
--   runs_*     mediciones pedidas (1 rápida / 3 precisa) y hechas.
--   status     pending (esperando la siguiente ronda) | measuring |
--              completed | failed
--   attempts   rondas reclamadas — corta bucles si el servidor mata el
--              proceso PHP en cada intento.
--   raw_json   gzip con las mediciones crudas mientras la prueba avanza
--              (se vacía al terminar).
--   result_json gzip con el resultado final (mediana por estrategia).
-- Timestamps los escribe PHP (date()) para comparar siempre en la misma
-- zona horaria que nowMinus().
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS perf_runs (
    id             {{AUTO_PK}},
    url            VARCHAR(2048) NOT NULL,
    url_hash       CHAR(40) NOT NULL,
    strategy       VARCHAR(16) NOT NULL,
    runs_requested {{INT}} NOT NULL DEFAULT 1,
    runs_done      {{INT}} NOT NULL DEFAULT 0,
    attempts       {{INT}} NOT NULL DEFAULT 0,
    status         VARCHAR(16) NOT NULL DEFAULT 'pending',
    note           VARCHAR(500),
    score_mobile   {{INT}},
    score_desktop  {{INT}},
    raw_json       {{BLOB}},
    result_json    {{BLOB}},
    error          TEXT,
    created_at     TIMESTAMP NOT NULL DEFAULT {{NOW}},
    updated_at     TIMESTAMP NULL,
    completed_at   TIMESTAMP NULL
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_perf_runs_url     ON perf_runs(url_hash, created_at);
CREATE INDEX IF NOT EXISTS idx_perf_runs_created ON perf_runs(created_at);
