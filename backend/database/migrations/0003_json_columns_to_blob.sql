-- ════════════════════════════════════════════════════════════════════
-- 0003_json_columns_to_blob — cambiar JSON → LONGBLOB en columnas
-- ────────────────────────────────────────────────────────────────────
-- Bug: JsonStore::encode() devuelve un blob gzip (bytes binarios). En
-- MySQL las columnas declaradas como `JSON` validan que el contenido sea
-- JSON sintácticamente correcto → rechazan los bytes gzip → el INSERT
-- de audits.php fallaba con "Error guardando el resultado".
--
-- SQLite no validaba (su tipo JSON es alias de TEXT) así que el bug
-- solo aparecía en MySQL.
--
-- Fix: ampliar a LONGBLOB (16 MB en MySQL — más que suficiente para
-- el JSON gzipped de un audit grande). En SQLite es no-op porque TEXT
-- acepta binario sin chistar.
-- ════════════════════════════════════════════════════════════════════

--{mysql}
ALTER TABLE audits MODIFY COLUMN result_json LONGBLOB NOT NULL;
ALTER TABLE audits MODIFY COLUMN waterfall_json LONGBLOB;
ALTER TABLE wp_snapshots MODIFY COLUMN snapshot_json LONGBLOB NOT NULL;
ALTER TABLE wp_snapshots MODIFY COLUMN analysis_json LONGBLOB;
-- audit_jobs.lead_data_json contiene JSON real (no gzipped) — lo dejamos
-- como JSON nativo para poder consultarlo con JSON_EXTRACT en el futuro.
--{/mysql}

--{sqlite}
-- No-op en SQLite: TEXT ya acepta binario, no necesita ALTER.
-- Statement dummy para que el splitter no genere SQL vacío.
SELECT 1;
--{/sqlite}
