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
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    ip_address TEXT
);

-- Tabla de configuración (key-value)
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

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

-- Índices
CREATE INDEX IF NOT EXISTS idx_audits_domain ON audits(domain);
CREATE INDEX IF NOT EXISTS idx_audits_created ON audits(created_at);
CREATE INDEX IF NOT EXISTS idx_audits_has_contact ON audits(lead_email);
CREATE INDEX IF NOT EXISTS idx_rate_limits_ip ON rate_limits(ip_address, endpoint);
CREATE INDEX IF NOT EXISTS idx_vulnerabilities_slug ON vulnerabilities(plugin_slug);
CREATE INDEX IF NOT EXISTS idx_checklist_audit ON checklist_items(audit_id);
