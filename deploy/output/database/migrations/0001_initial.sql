-- ════════════════════════════════════════════════════════════════════
-- 0001_initial — schema completo de Imagina Audit
-- ────────────────────────────────────────────────────────────────────
-- Esta migración consolida el schema legacy de SQLite (backend/database/
-- schema.sql) + los ALTER TABLE que vivían en Database::runMigrations().
-- Es 100% idempotente: cualquier install previa (que ya tenía estas
-- tablas creadas por la ruta legacy) la aplicará como no-op.
--
-- Reglas seguidas (ver /P7_PRODUCTION_DATABASE.md §3):
--   - Cross-driver vía placeholders {{NOW}}, {{AUTO_PK}}, {{BOOL}},
--     {{JSON}}, {{TABLE_OPTIONS}}.
--   - Sin INSERT OR IGNORE en el código común — el seed de idiomas va
--     en bloque condicional por driver.
--   - VARCHAR explícito en columnas indexadas o que viajan a JOINs/FKs
--     para que MySQL acepte los índices (SQLite los trata como TEXT).
--   - Foreign keys explícitas con ON DELETE adecuado.
--   - Índices definidos junto a la tabla que cubren.
-- ════════════════════════════════════════════════════════════════════

-- ─── audits ──────────────────────────────────────────────────────────
-- Tabla central: cada auditoría queda con su JSON resultado completo.
CREATE TABLE IF NOT EXISTS audits (
    id              VARCHAR(36) PRIMARY KEY,
    url             VARCHAR(2048) NOT NULL,
    domain          VARCHAR(255)  NOT NULL,
    lead_name       VARCHAR(255),
    lead_email      VARCHAR(255),
    lead_whatsapp   VARCHAR(64),
    lead_company    VARCHAR(255),
    global_score    {{INT}} NOT NULL DEFAULT 0,
    global_level    VARCHAR(32) NOT NULL DEFAULT 'unknown',
    is_wordpress    {{BOOL}} NOT NULL DEFAULT 0,
    scan_duration_ms {{INT}} NOT NULL DEFAULT 0,
    result_json     {{JSON}} NOT NULL,
    waterfall_json  {{JSON}},
    is_pinned       {{BOOL}} NOT NULL DEFAULT 0,
    lang            VARCHAR(8) NOT NULL DEFAULT 'en',
    is_deleted      {{BOOL}} NOT NULL DEFAULT 0,
    user_id         {{BIGINT}},
    project_id      {{BIGINT}},
    ip_address      VARCHAR(64),
    created_at      TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_audits_domain       ON audits(domain);
-- url index needs a length prefix en MySQL (InnoDB tope ~3072 bytes).
--{mysql}
CREATE INDEX idx_audits_url ON audits(url(255));
--{/mysql}
--{sqlite}
CREATE INDEX IF NOT EXISTS idx_audits_url ON audits(url);
--{/sqlite}
CREATE INDEX IF NOT EXISTS idx_audits_created      ON audits(created_at);
CREATE INDEX IF NOT EXISTS idx_audits_has_contact  ON audits(lead_email);
CREATE INDEX IF NOT EXISTS idx_audits_score        ON audits(global_score);
CREATE INDEX IF NOT EXISTS idx_audits_pinned       ON audits(is_pinned, created_at);
CREATE INDEX IF NOT EXISTS idx_audits_user         ON audits(user_id);
CREATE INDEX IF NOT EXISTS idx_audits_project      ON audits(project_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audits_user_live    ON audits(user_id, is_deleted, created_at);

-- ─── settings ────────────────────────────────────────────────────────
-- Key-value para configuración admin (branding, mensajes, etc.).
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(128) PRIMARY KEY,
    value       {{TEXT_LONG}} NOT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

-- ─── translations ────────────────────────────────────────────────────
-- Overrides editables del admin. La fuente de verdad son los bundles
-- en backend/locales/ — esta tabla solo guarda lo que cambia.
CREATE TABLE IF NOT EXISTS translations (
    id          {{AUTO_PK}},
    lang        VARCHAR(8)   NOT NULL,
    namespace   VARCHAR(64)  NOT NULL,
    `key`       VARCHAR(255) NOT NULL,
    value       {{TEXT_LONG}} NOT NULL,
    source      VARCHAR(16)  NOT NULL DEFAULT 'manual',
    ai_provider VARCHAR(32),
    reviewed    {{BOOL}} NOT NULL DEFAULT 0,
    updated_at  TIMESTAMP NOT NULL DEFAULT {{NOW}},
    UNIQUE(lang, namespace, `key`)
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_translations_lang_ns ON translations(lang, namespace);

-- ─── vulnerabilities ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vulnerabilities (
    id                {{AUTO_PK}},
    plugin_slug       VARCHAR(128) NOT NULL,
    plugin_name       VARCHAR(255) NOT NULL,
    affected_versions VARCHAR(255) NOT NULL,
    severity          VARCHAR(16)  NOT NULL DEFAULT 'medium',
    cve_id            VARCHAR(64),
    description       TEXT NOT NULL,
    fixed_in_version  VARCHAR(64),
    created_at        TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_vulnerabilities_slug ON vulnerabilities(plugin_slug);

-- ─── rate_limits ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
    ip_address    VARCHAR(64) NOT NULL,
    endpoint      VARCHAR(64) NOT NULL,
    request_time  TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_rate_limits_ip   ON rate_limits(ip_address, endpoint);
CREATE INDEX IF NOT EXISTS idx_rate_limits_time ON rate_limits(request_time);

-- ─── wp_snapshots ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wp_snapshots (
    id            {{AUTO_PK}},
    audit_id      VARCHAR(36) NOT NULL,
    source        VARCHAR(16) NOT NULL DEFAULT 'upload',
    source_url    VARCHAR(2048),
    snapshot_json {{JSON}} NOT NULL,
    analysis_json {{JSON}},
    created_at    TIMESTAMP NOT NULL DEFAULT {{NOW}},
    UNIQUE(audit_id)
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_wp_snapshots_audit ON wp_snapshots(audit_id);

-- ─── checklist_items (per-audit snapshot, admin-only) ────────────────
CREATE TABLE IF NOT EXISTS checklist_items (
    id           {{AUTO_PK}},
    audit_id     VARCHAR(36) NOT NULL,
    metric_id    VARCHAR(128) NOT NULL,
    completed    {{BOOL}} NOT NULL DEFAULT 0,
    notes        TEXT,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT {{NOW}},
    UNIQUE(audit_id, metric_id)
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_checklist_audit ON checklist_items(audit_id);

-- ─── audit_jobs (cola FIFO) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_jobs (
    id              {{AUTO_PK}},
    audit_id        VARCHAR(36) NOT NULL UNIQUE,
    url             VARCHAR(2048) NOT NULL,
    lead_data_json  {{JSON}},
    status          VARCHAR(16) NOT NULL DEFAULT 'queued',
    ip_address      VARCHAR(64),
    attempts        {{INT}} NOT NULL DEFAULT 0,
    error_message   TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT {{NOW}},
    started_at      TIMESTAMP NULL DEFAULT NULL,
    completed_at    TIMESTAMP NULL DEFAULT NULL
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_audit_jobs_status  ON audit_jobs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_jobs_started ON audit_jobs(started_at);

-- ─── plans ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS plans (
    id            {{AUTO_PK}},
    name          VARCHAR(128) NOT NULL,
    monthly_limit {{INT}} NOT NULL DEFAULT 10,
    max_projects  {{INT}} NOT NULL DEFAULT 0,
    description   TEXT,
    is_active     {{BOOL}} NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

-- ─── users ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            {{AUTO_PK}},
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(255),
    plan_id       {{BIGINT}},
    is_active     {{BOOL}} NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT {{NOW}},
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_plan  ON users(plan_id);

-- ─── user_login_attempts ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_login_attempts (
    id           {{AUTO_PK}},
    ip_address   VARCHAR(64) NOT NULL,
    email        VARCHAR(255),
    attempted_at TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_user_login_attempts ON user_login_attempts(ip_address, attempted_at);

-- ─── projects ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id           {{AUTO_PK}},
    user_id      {{BIGINT}} NOT NULL,
    name         VARCHAR(255) NOT NULL,
    url          VARCHAR(2048) NOT NULL,
    domain       VARCHAR(255) NOT NULL,
    notes        TEXT,
    icon         VARCHAR(64),
    color        VARCHAR(16),
    share_token  VARCHAR(64),
    created_at   TIMESTAMP NOT NULL DEFAULT {{NOW}},
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_projects_user        ON projects(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_projects_user_domain ON projects(user_id, domain);

-- share_token UNIQUE pero permitiendo múltiples NULL. SQLite necesita
-- índice parcial; MySQL trata los NULL como distintos en UNIQUE así que
-- un índice normal vale.
--{sqlite}
CREATE UNIQUE INDEX IF NOT EXISTS idx_projects_share_token ON projects(share_token) WHERE share_token IS NOT NULL;
--{/sqlite}
--{mysql}
CREATE UNIQUE INDEX idx_projects_share_token ON projects(share_token);
--{/mysql}

-- ─── project_checklist_items (living checklist por proyecto) ─────────
CREATE TABLE IF NOT EXISTS project_checklist_items (
    id            {{AUTO_PK}},
    project_id    {{BIGINT}} NOT NULL,
    metric_id     VARCHAR(128) NOT NULL,
    status        VARCHAR(16) NOT NULL DEFAULT 'open',
    severity      VARCHAR(16),
    note          TEXT,
    user_modified {{BOOL}} NOT NULL DEFAULT 0,
    completed_at  TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT {{NOW}},
    updated_at    TIMESTAMP NOT NULL DEFAULT {{NOW}},
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE(project_id, metric_id)
){{TABLE_OPTIONS}};

CREATE INDEX IF NOT EXISTS idx_project_checklist_project ON project_checklist_items(project_id, status);

-- ─── languages ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS languages (
    code        VARCHAR(8) PRIMARY KEY,
    name        VARCHAR(64) NOT NULL,
    native_name VARCHAR(64),
    is_active   {{BOOL}} NOT NULL DEFAULT 1,
    is_public   {{BOOL}} NOT NULL DEFAULT 1,
    sort_order  {{INT}} NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT {{NOW}}
){{TABLE_OPTIONS}};

-- Seed inicial: en y es son los bundles que vienen con la app.
-- Sintaxis distinta porque SQLite usa INSERT OR IGNORE y MySQL INSERT IGNORE.
--{sqlite}
INSERT OR IGNORE INTO languages (code, name, native_name, is_active, is_public, sort_order) VALUES
    ('en', 'English', 'English', 1, 1, 0),
    ('es', 'Spanish', 'Español', 1, 1, 1);
--{/sqlite}
--{mysql}
INSERT IGNORE INTO languages (code, name, native_name, is_active, is_public, sort_order) VALUES
    ('en', 'English', 'English', 1, 1, 0),
    ('es', 'Spanish', 'Español', 1, 1, 1);
--{/mysql}
