-- ════════════════════════════════════════════════════════════════════
-- 0002_admin_login_attempts — tabla de rate-limit del login admin
-- ────────────────────────────────────────────────────────────────────
-- Antes vivía como CREATE TABLE inline en api/admin/login.php (SQLite-
-- only, ya no compatible con MySQL). La pasamos a una migración
-- versionada y la limpiamos al estilo del resto del schema.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS login_attempts (
    id           {{AUTO_PK}},
    ip_address   VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip   ON login_attempts(ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_time ON login_attempts(attempted_at);
