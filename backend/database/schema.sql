-- Schema de SQLite para Imagina Audit

-- Tabla de auditorías y leads
CREATE TABLE IF NOT EXISTS audits (
    id TEXT PRIMARY KEY,
    url TEXT NOT NULL,
    domain TEXT NOT NULL,
    lead_name TEXT,
    lead_email TEXT,
    lead_whatsapp TEXT,
    lead_company TEXT,
    global_score INTEGER NOT NULL DEFAULT 0,
    global_level TEXT NOT NULL DEFAULT 'unknown',
    is_wordpress INTEGER NOT NULL DEFAULT 0,
    scan_duration_ms INTEGER NOT NULL DEFAULT 0,
    result_json TEXT NOT NULL,
    waterfall_json TEXT,
    is_pinned INTEGER NOT NULL DEFAULT 0,
    lang TEXT NOT NULL DEFAULT 'en',
    is_deleted INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    ip_address TEXT
);

-- Tabla de configuración (key-value)
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabla de traducciones (overrides editados desde admin)
-- La tupla (lang, namespace, key) es única. Si existe, gana sobre el bundle
-- de archivos en backend/locales/{lang}/{namespace}.php.
-- Si source='ai', significa que la traducción fue generada por un provider
-- (ChatGPT/Claude/Google) y el admin podría no haberla revisado.
CREATE TABLE IF NOT EXISTS translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lang TEXT NOT NULL,
    namespace TEXT NOT NULL,
    key TEXT NOT NULL,
    value TEXT NOT NULL,
    source TEXT NOT NULL DEFAULT 'manual',  -- 'manual' | 'ai' | 'import'
    ai_provider TEXT,                        -- 'chatgpt' | 'claude' | 'google' si source='ai'
    reviewed INTEGER NOT NULL DEFAULT 0,     -- 1 si el admin confirmó la traducción AI
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(lang, namespace, key)
);
CREATE INDEX IF NOT EXISTS idx_translations_lang_ns ON translations(lang, namespace);

-- Tabla de vulnerabilidades
CREATE TABLE IF NOT EXISTS vulnerabilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plugin_slug TEXT NOT NULL,
    plugin_name TEXT NOT NULL,
    affected_versions TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT 'medium',
    cve_id TEXT,
    description TEXT NOT NULL,
    fixed_in_version TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabla de rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    ip_address TEXT NOT NULL,
    endpoint TEXT NOT NULL,
    request_time TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabla de snapshots de WordPress (wp-snapshot plugin)
CREATE TABLE IF NOT EXISTS wp_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id TEXT NOT NULL,
    source TEXT NOT NULL DEFAULT 'upload',
    source_url TEXT,
    snapshot_json TEXT NOT NULL,
    analysis_json TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(audit_id)
);

-- Tabla de checklist del reporte técnico
CREATE TABLE IF NOT EXISTS checklist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id TEXT NOT NULL,
    metric_id TEXT NOT NULL,
    completed INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(audit_id, metric_id)
);

-- Cola de auditorías (control de concurrencia)
-- `status` FIFO: queued → running → completed/failed
-- El drenado lo hace el request que acaba de terminar un audit (auto-worker).
-- Un cron cada 5 min limpia jobs huérfanos ("running" > 3 min sin actualizarse).
CREATE TABLE IF NOT EXISTS audit_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id TEXT NOT NULL UNIQUE,
    url TEXT NOT NULL,
    lead_data_json TEXT,
    status TEXT NOT NULL DEFAULT 'queued',
    ip_address TEXT,
    attempts INTEGER NOT NULL DEFAULT 0,
    error_message TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    started_at TEXT,
    completed_at TEXT
);

-- Planes: cuotas de escaneo configurables por el admin.
-- Cada usuario tiene un plan asignado (plan_id). El `monthly_limit` es la
-- cantidad máxima de audits que ese user puede disparar en un mes calendario.
-- Un monthly_limit = 0 significa cuota ilimitada (ej. plan interno del equipo).
-- `max_projects` = 0 también significa ilimitado. Misma convención.
CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    monthly_limit INTEGER NOT NULL DEFAULT 10,
    max_projects INTEGER NOT NULL DEFAULT 0,
    description TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Usuarios: cuentas creadas por el admin. No hay auto-registro — el admin
-- los da de alta desde /admin/users y les comparte la password inicial.
-- El user usa esas credenciales en /login (flujo separado de /admin/login).
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT,
    plan_id INTEGER,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    last_login_at TEXT,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
);

-- Rate limit propio para intentos de login de users (sin pisar el de admin)
CREATE TABLE IF NOT EXISTS user_login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    email TEXT,
    attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Proyectos: 1 proyecto = 1 URL (modelo simple). El user agrupa su portfolio
-- de sitios acá. Al lanzar un audit cuya URL coincida con project.url, se
-- auto-atribuye vía audits.project_id. `share_token` nullable — se setea
-- cuando el user activa el link público read-only para clientes.
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    domain TEXT NOT NULL,
    notes TEXT,
    icon TEXT,
    color TEXT,
    share_token TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Checklist vivo por proyecto. Se diferencia del `checklist_items` original
-- (que sigue siendo por audit, snapshot inmutable) — acá mantenemos el
-- estado de cada métrica a lo largo del tiempo. La reconciliación tras un
-- audit nuevo es:
--   * métrica pasó a 🟢 y el user no la tocó (user_modified=0) → status='done' auto
--   * métrica pasó a 🟢 y el user la había cerrado a mano → queda 'done'
--   * métrica sigue 🔴/🟡 y el user la había cerrado → se "re-abre" (status='open',
--       user_modified=0) — el user la cierra de nuevo si quiere
-- `user_modified=1` marca que el valor actual lo puso el user, no la reconciliación.
CREATE TABLE IF NOT EXISTS project_checklist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    metric_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    severity TEXT,
    note TEXT,
    user_modified INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE(project_id, metric_id)
);

-- Tabla de idiomas activos. El admin puede crear/activar idiomas desde el
-- panel. `is_public=1` hace que el idioma aparezca en el LanguageSwitcher
-- del frontend público. `is_active=0` lo oculta por completo (temporal).
-- Los bundles base viven en backend/locales/{code}/ (si existen) + DB
-- overrides en la tabla `translations`. Para idiomas sin bundle base, las
-- keys del idioma default (en) se usan como fallback.
CREATE TABLE IF NOT EXISTS languages (
    code TEXT PRIMARY KEY,                       -- 'en', 'es', 'pt', etc. (ISO 639-1)
    name TEXT NOT NULL,                          -- 'English', 'Español'
    native_name TEXT,                            -- Nombre en su propio idioma
    is_active INTEGER NOT NULL DEFAULT 1,
    is_public INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
-- Seeds iniciales (en y es son los bundles que vienen con la app)
INSERT OR IGNORE INTO languages (code, name, native_name, is_active, is_public, sort_order) VALUES
    ('en', 'English', 'English', 1, 1, 0),
    ('es', 'Spanish', 'Español', 1, 1, 1);

-- Índices
CREATE INDEX IF NOT EXISTS idx_audits_domain ON audits(domain);
CREATE INDEX IF NOT EXISTS idx_audits_url ON audits(url);
CREATE INDEX IF NOT EXISTS idx_audits_created ON audits(created_at);
CREATE INDEX IF NOT EXISTS idx_audits_has_contact ON audits(lead_email);
CREATE INDEX IF NOT EXISTS idx_audits_score ON audits(global_score);
CREATE INDEX IF NOT EXISTS idx_rate_limits_ip ON rate_limits(ip_address, endpoint);
CREATE INDEX IF NOT EXISTS idx_rate_limits_time ON rate_limits(request_time);
CREATE INDEX IF NOT EXISTS idx_vulnerabilities_slug ON vulnerabilities(plugin_slug);
CREATE INDEX IF NOT EXISTS idx_checklist_audit ON checklist_items(audit_id);
CREATE INDEX IF NOT EXISTS idx_wp_snapshots_audit ON wp_snapshots(audit_id);
CREATE INDEX IF NOT EXISTS idx_audit_jobs_status ON audit_jobs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_jobs_started ON audit_jobs(started_at);
CREATE INDEX IF NOT EXISTS idx_audits_pinned ON audits(is_pinned, created_at);
CREATE INDEX IF NOT EXISTS idx_audits_user ON audits(user_id);
CREATE INDEX IF NOT EXISTS idx_audits_project ON audits(project_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audits_user_live ON audits(user_id, is_deleted, created_at);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_plan ON users(plan_id);
CREATE INDEX IF NOT EXISTS idx_user_login_attempts ON user_login_attempts(ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_projects_user_domain ON projects(user_id, domain);
CREATE UNIQUE INDEX IF NOT EXISTS idx_projects_share_token ON projects(share_token) WHERE share_token IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_project_checklist_project ON project_checklist_items(project_id, status);
