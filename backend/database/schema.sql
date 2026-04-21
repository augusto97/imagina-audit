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
