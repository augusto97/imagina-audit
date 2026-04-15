# CLAUDE.md — Imagina Audit Tool v2.0
## Especificación Técnica Completa para Desarrollo

---

## 1. VISIÓN DEL PROYECTO

### ¿Qué es?
**Imagina Audit** es una aplicación web que analiza sitios WordPress externamente (sin acceso al admin del sitio auditado, sin instalar nada) y genera un informe visual profesional con puntuaciones, semáforos y recomendaciones. Es una herramienta de ventas para la empresa Imagina WP (imaginawp.com).

### Objetivo de negocio
Convencer a prospectos de contratar los planes de soporte mensual de Imagina WP mostrándoles problemas reales en su sitio web que Imagina WP puede resolver. Cada problema detectado se mapea directamente a un servicio incluido en los planes.

### Usuarios
1. **Prospectos (público):** Ingresan su URL en la herramienta y reciben un informe gratuito con problemas detectados y cómo Imagina WP los resuelve.
2. **Admin (Edison):** Usa la herramienta para auditar sitios durante llamadas de venta. Gestiona leads, configuración, textos y base de vulnerabilidades desde un panel admin integrado.

### Requisitos de infraestructura
- **OBLIGATORIO:** Debe correr al 100% en un hosting compartido con cPanel (sin VPS, sin Docker, sin procesos en background).
- El backend es PHP puro (cualquier hosting con PHP 8.0+ y cURL).
- El frontend se compila a archivos estáticos (HTML/CSS/JS) que se suben al hosting.
- No requiere Node.js en el servidor. Node.js solo se usa en la máquina local de desarrollo para compilar el frontend.

---

## 2. TECH STACK

### Frontend (se compila a archivos estáticos)
- **Framework:** React 18+ con Vite
- **Lenguaje:** TypeScript
- **Estilos:** Tailwind CSS 3+
- **Componentes UI:** shadcn/ui (basado en Radix UI) — dark mode por defecto
- **Gráficos:** Recharts (gauges circulares, barras de módulos)
- **Animaciones:** Framer Motion (animación de escaneo, gauges, entradas escalonadas)
- **Iconos:** Lucide React
- **PDF:** html2pdf.js (generación de informe PDF descargable)
- **HTTP Client:** Axios
- **Routing:** React Router v6 (con lazy loading para rutas /admin)
- **Estado global:** Zustand
- **Formularios:** React Hook Form + Zod
- **Notificaciones:** Sonner (toasts)
- **Internacionalización:** i18next (español por defecto)

### Backend (PHP — corre en hosting compartido)
- **Lenguaje:** PHP 8.0+
- **Extensiones requeridas:** cURL, DOM (DOMDocument), JSON, OpenSSL, mbstring — todas vienen por defecto en hosting compartido
- **Sin framework.** PHP vanilla con una estructura organizada de clases/archivos. No necesita Composer ni dependencias externas.
- **Base de datos:** SQLite (un solo archivo .db dentro del hosting, sin necesidad de MySQL). Se usa para almacenar auditorías, leads y configuración. PHP tiene SQLite integrado (extensión pdo_sqlite, habilitada por defecto).
- **Cache:** Archivos JSON en disco (carpeta /cache/) con TTL de 24 horas. Sin Redis ni Memcached.
- **Autenticación admin:** Contraseña hasheada con password_hash() almacenada en la tabla de configuración de SQLite. Sesión PHP estándar con session_start().

### Deployment
- Hosting compartido con cPanel
- Frontend: archivos estáticos en carpeta /public_html/audit/ (o subdominio audit.dominio.com)
- Backend: archivos PHP en carpeta /public_html/audit/api/
- SQLite: archivo .db en carpeta FUERA de public_html (no accesible por web) o dentro con .htaccess que bloquee acceso directo
- HTTPS: certificado del hosting (Let's Encrypt via cPanel)

---

## 3. ARQUITECTURA DE CARPETAS

```
imagina-audit/
│
├── frontend/                         # Proyecto React (solo para desarrollo local)
│   ├── public/
│   │   └── favicon.ico
│   ├── src/
│   │   ├── assets/
│   │   │   ├── logo-imagina-white.svg
│   │   │   └── logo-imagina-dark.svg
│   │   │
│   │   ├── components/
│   │   │   ├── ui/                   # Componentes shadcn/ui instalados
│   │   │   │   ├── button.tsx
│   │   │   │   ├── card.tsx
│   │   │   │   ├── input.tsx
│   │   │   │   ├── badge.tsx
│   │   │   │   ├── progress.tsx
│   │   │   │   ├── accordion.tsx
│   │   │   │   ├── tabs.tsx
│   │   │   │   ├── dialog.tsx
│   │   │   │   ├── table.tsx
│   │   │   │   ├── select.tsx
│   │   │   │   ├── textarea.tsx
│   │   │   │   ├── switch.tsx
│   │   │   │   ├── tooltip.tsx
│   │   │   │   ├── separator.tsx
│   │   │   │   ├── skeleton.tsx
│   │   │   │   └── sheet.tsx
│   │   │   │
│   │   │   ├── layout/
│   │   │   │   ├── Header.tsx
│   │   │   │   ├── Footer.tsx
│   │   │   │   └── Layout.tsx
│   │   │   │
│   │   │   ├── audit/                # Componentes de la auditoría pública
│   │   │   │   ├── AuditForm.tsx              # Formulario de entrada URL + datos lead
│   │   │   │   ├── ScanningAnimation.tsx      # Pantalla de escaneo animada
│   │   │   │   ├── ScoreOverview.tsx          # Panel resumen con score global + módulos
│   │   │   │   ├── ScoreGauge.tsx             # Gauge circular SVG animado
│   │   │   │   ├── ModuleCard.tsx             # Card expandible de cada módulo
│   │   │   │   ├── MetricRow.tsx              # Fila individual de métrica con semáforo
│   │   │   │   ├── SemaphoreIcon.tsx          # Icono de semáforo (verde/amarillo/rojo)
│   │   │   │   ├── SecurityModule.tsx
│   │   │   │   ├── PerformanceModule.tsx
│   │   │   │   ├── SeoModule.tsx
│   │   │   │   ├── WordPressModule.tsx
│   │   │   │   ├── MobileModule.tsx
│   │   │   │   ├── InfrastructureModule.tsx
│   │   │   │   ├── ConversionModule.tsx
│   │   │   │   ├── BackupsModule.tsx          # Módulo estático con checklist
│   │   │   │   ├── SolutionMapping.tsx        # Tabla problema → solución Imagina WP
│   │   │   │   ├── EconomicImpact.tsx         # Calculadora de impacto económico
│   │   │   │   ├── CtaSection.tsx             # CTA final con botones WhatsApp/planes
│   │   │   │   └── PdfReport.tsx              # Generador de PDF
│   │   │   │
│   │   │   └── admin/                # Componentes del panel admin
│   │   │       ├── AdminLogin.tsx             # Pantalla de login admin
│   │   │       ├── AdminLayout.tsx            # Layout con sidebar del admin
│   │   │       ├── AdminSidebar.tsx           # Sidebar de navegación admin
│   │   │       ├── DashboardPage.tsx          # Dashboard con métricas generales
│   │   │       ├── LeadsTable.tsx             # Tabla de leads/auditorías
│   │   │       ├── LeadDetail.tsx             # Detalle de un lead individual
│   │   │       ├── SettingsGeneral.tsx        # Config: contacto, branding, API keys
│   │   │       ├── SettingsScoring.tsx        # Config: umbrales y pesos de scoring
│   │   │       ├── SettingsMessages.tsx       # Config: textos de venta por módulo
│   │   │       ├── SettingsPlans.tsx          # Config: planes y precios para el CTA
│   │   │       └── VulnerabilityManager.tsx   # CRUD de base de vulnerabilidades
│   │   │
│   │   ├── hooks/
│   │   │   ├── useAudit.ts                    # Hook para ejecutar auditoría
│   │   │   ├── useAdmin.ts                    # Hook para operaciones admin
│   │   │   └── useAuth.ts                     # Hook para autenticación admin
│   │   │
│   │   ├── lib/
│   │   │   ├── api.ts                         # Cliente HTTP para el backend PHP
│   │   │   ├── scoring.ts                     # Lógica de scoring (mirror del backend)
│   │   │   ├── utils.ts                       # Utilidades comunes
│   │   │   ├── constants.ts                   # Colores, umbrales por defecto, textos
│   │   │   └── pdf-template.ts                # Template y estilos del PDF
│   │   │
│   │   ├── pages/
│   │   │   ├── HomePage.tsx                   # Landing pública con formulario
│   │   │   ├── ResultsPage.tsx                # Página de resultados de auditoría
│   │   │   ├── AdminPage.tsx                  # Wrapper del panel admin (lazy loaded)
│   │   │   └── NotFoundPage.tsx
│   │   │
│   │   ├── store/
│   │   │   ├── auditStore.ts                  # Estado de la auditoría activa
│   │   │   └── authStore.ts                   # Estado de autenticación admin
│   │   │
│   │   ├── types/
│   │   │   ├── audit.ts                       # Tipos de auditoría
│   │   │   └── admin.ts                       # Tipos del admin
│   │   │
│   │   ├── i18n/
│   │   │   ├── index.ts                       # Configuración i18next
│   │   │   ├── es.json                        # Traducciones español
│   │   │   └── en.json                        # Traducciones inglés
│   │   │
│   │   ├── App.tsx                            # Router principal
│   │   ├── main.tsx                           # Entry point
│   │   └── index.css                          # Tailwind imports + variables CSS custom
│   │
│   ├── index.html
│   ├── tailwind.config.ts
│   ├── tsconfig.json
│   ├── vite.config.ts
│   ├── components.json                        # Config de shadcn/ui
│   └── package.json
│
├── backend/                          # PHP — se sube completo al hosting
│   ├── api/
│   │   ├── index.php                          # Router principal de la API
│   │   ├── audit.php                          # POST /api/audit — ejecuta auditoría
│   │   ├── audit-status.php                   # GET /api/audit-status?id=X — estado/resultado
│   │   ├── config.php                         # GET /api/config — config pública (branding, textos)
│   │   ├── health.php                         # GET /api/health — healthcheck
│   │   │
│   │   ├── admin/                             # Rutas protegidas del admin
│   │   │   ├── login.php                      # POST /api/admin/login
│   │   │   ├── logout.php                     # POST /api/admin/logout
│   │   │   ├── session.php                    # GET /api/admin/session — verifica sesión activa
│   │   │   ├── dashboard.php                  # GET /api/admin/dashboard — stats
│   │   │   ├── leads.php                      # GET/DELETE /api/admin/leads
│   │   │   ├── lead-detail.php                # GET /api/admin/lead-detail?id=X
│   │   │   ├── settings.php                   # GET/PUT /api/admin/settings
│   │   │   └── vulnerabilities.php            # GET/POST/PUT/DELETE /api/admin/vulnerabilities
│   │   │
│   │   └── .htaccess                          # Rewrite rules para URLs limpias + headers CORS
│   │
│   ├── lib/
│   │   ├── Fetcher.php                        # Wrapper seguro de cURL con timeout y validación
│   │   ├── HtmlParser.php                     # Helpers de parsing HTML con DOMDocument
│   │   ├── UrlValidator.php                   # Validación y normalización de URLs + anti-SSRF
│   │   ├── Scoring.php                        # Funciones de cálculo de scores
│   │   ├── Cache.php                          # Cache en archivos JSON con TTL
│   │   ├── Database.php                       # Wrapper de SQLite con PDO
│   │   ├── Auth.php                           # Autenticación y sesión admin
│   │   ├── Response.php                       # Helper para respuestas JSON estandarizadas
│   │   └── Logger.php                         # Log simple a archivo (errores y auditorías)
│   │
│   ├── analyzers/
│   │   ├── WordPressDetector.php              # Detecta WP, plugins, tema, versión
│   │   ├── SecurityAnalyzer.php               # SSL, headers, login, vulnerabilidades
│   │   ├── PerformanceAnalyzer.php            # Google PageSpeed API
│   │   ├── SeoAnalyzer.php                    # Meta tags, H1, sitemap, robots, schema
│   │   ├── MobileAnalyzer.php                 # Viewport, usabilidad móvil
│   │   ├── InfrastructureAnalyzer.php         # Servidor, hosting, PHP, protocolo
│   │   ├── ConversionAnalyzer.php             # GA, GTM, chat, formularios, push
│   │   └── AuditOrchestrator.php              # Ejecuta todos los analyzers y compila resultado
│   │
│   ├── config/
│   │   ├── defaults.php                       # Valores por defecto de scoring, pesos, textos
│   │   └── env.php                            # Carga variables de .env
│   │
│   ├── data/
│   │   ├── vulnerabilities.json               # Base local de vulnerabilidades de plugins WP
│   │   └── known-plugins.json                 # Mapeo de slugs → nombres legibles de plugins
│   │
│   ├── cache/                                 # Carpeta de cache (permisos 755)
│   │   └── .htaccess                          # Deny from all
│   │
│   ├── logs/                                  # Carpeta de logs (permisos 755)
│   │   └── .htaccess                          # Deny from all
│   │
│   ├── database/
│   │   ├── schema.sql                         # Schema de SQLite
│   │   ├── seed.php                           # Script para crear DB y datos iniciales
│   │   └── .htaccess                          # Deny from all
│   │
│   ├── .env.example                           # Variables de entorno ejemplo
│   ├── .env                                   # Variables de entorno (no se sube a git)
│   └── .htaccess                              # Bloquea acceso a archivos sensibles
│
├── deploy/
│   ├── build.sh                               # Script: npm run build + copiar archivos
│   └── README-deploy.md                       # Instrucciones de deploy para hosting compartido
│
├── .gitignore
├── README.md
└── CLAUDE.md                                  # Este archivo
```

### Estructura en el hosting (después del deploy)
```
public_html/
└── audit/                        # O subdominio audit.tusitio.com
    ├── index.html                # React build (entry point)
    ├── assets/                   # CSS, JS, imágenes compiladas por Vite
    │   ├── index-[hash].js
    │   ├── index-[hash].css
    │   └── ...
    ├── api/                      # Backend PHP
    │   ├── .htaccess
    │   ├── index.php
    │   ├── audit.php
    │   ├── config.php
    │   ├── admin/
    │   │   └── ...
    │   └── ...
    ├── lib/                      # Clases PHP (protegidas por .htaccess)
    │   └── .htaccess             # Deny from all
    ├── analyzers/                # Analyzers PHP (protegidos por .htaccess)
    │   └── .htaccess             # Deny from all
    ├── config/
    │   └── .htaccess             # Deny from all
    ├── data/
    │   └── .htaccess             # Deny from all
    ├── database/
    │   └── .htaccess             # Deny from all
    ├── cache/
    │   └── .htaccess             # Deny from all
    └── logs/
        └── .htaccess             # Deny from all
```

---

## 4. TIPOS DE DATOS

### 4.1 Tipos de Auditoría (frontend: types/audit.ts / backend: equivalente en PHP)

```typescript
// === TIPOS PRINCIPALES ===

export type SemaphoreLevel = 'critical' | 'warning' | 'good' | 'excellent' | 'info' | 'unknown';

export interface MetricResult {
  id: string;                          // Identificador único: "ssl_valid", "pagespeed_mobile", etc.
  name: string;                        // Nombre legible: "Certificado SSL"
  value: string | number | boolean | null;  // Valor detectado
  displayValue: string;                // Valor formateado para mostrar: "Válido hasta 2025-12-01"
  score: number;                       // 0-100 para esta métrica individual
  level: SemaphoreLevel;               // Semáforo visual
  description: string;                 // Explicación para el usuario
  recommendation: string;              // Qué debería hacer
  imaginaSolution: string;             // Cómo Imagina WP lo resuelve
  details?: Record<string, any>;       // Datos adicionales opcionales
}

export interface ModuleResult {
  id: string;                          // 'wordpress' | 'security' | 'performance' | 'seo' | 'mobile' | 'infrastructure' | 'conversion' | 'backups'
  name: string;                        // "Seguridad"
  icon: string;                        // Nombre del icono Lucide: "shield", "gauge", etc.
  score: number;                       // 0-100 promedio ponderado del módulo
  level: SemaphoreLevel;
  weight: number;                      // Peso en el score global (0.05 - 0.25)
  metrics: MetricResult[];
  summary: string;                     // Resumen del módulo
  salesMessage: string;                // Mensaje de venta
}

export interface AuditResult {
  id: string;                          // UUID del audit
  url: string;                         // URL escaneada
  domain: string;                      // Dominio limpio
  timestamp: string;                   // ISO 8601
  scanDurationMs: number;              // Duración total en milisegundos
  globalScore: number;                 // 0-100 score ponderado
  globalLevel: SemaphoreLevel;
  totalIssues: {
    critical: number;
    warning: number;
    good: number;
  };
  modules: ModuleResult[];
  isWordPress: boolean;
  economicImpact: {
    estimatedMonthlyLoss: number;
    currency: string;
    explanation: string;
  };
  solutionMap: SolutionItem[];         // Mapeo problema → solución
}

export interface SolutionItem {
  problem: string;
  level: SemaphoreLevel;
  solution: string;
  includedInPlan: string;              // "Basic" | "Pro" | "Custom"
}

export interface AuditRequest {
  url: string;
  leadName?: string;
  leadEmail?: string;
  leadWhatsapp?: string;
  leadCompany?: string;
}
```

### 4.2 Tipos del Admin (frontend: types/admin.ts)

```typescript
export interface DashboardStats {
  totalAudits: number;
  totalLeads: number;               // Auditorías con datos de contacto
  auditsToday: number;
  auditsThisWeek: number;
  auditsThisMonth: number;
  averageScore: number;
  scoreDistribution: {               // Para gráfico de barras
    critical: number;                // 0-29
    deficient: number;               // 30-49
    regular: number;                 // 50-69
    good: number;                    // 70-89
    excellent: number;               // 90-100
  };
  recentAudits: LeadSummary[];       // Últimas 10 auditorías
}

export interface LeadSummary {
  id: string;
  url: string;
  domain: string;
  leadName: string | null;
  leadEmail: string | null;
  leadWhatsapp: string | null;
  leadCompany: string | null;
  globalScore: number;
  globalLevel: SemaphoreLevel;
  timestamp: string;
  hasContactInfo: boolean;
}

export interface AppSettings {
  // Branding
  companyName: string;
  companyUrl: string;
  companyWhatsapp: string;
  companyEmail: string;
  companyPlansUrl: string;
  logoUrl: string;
  
  // API Keys
  googlePagespeedApiKey: string;     // Opcional
  
  // Scoring weights
  moduleWeights: Record<string, number>;
  
  // Scoring thresholds
  thresholds: {
    excellent: number;               // Default: 90
    good: number;                    // Default: 70
    warning: number;                 // Default: 50
    critical: number;                // Default: 30
  };
  
  // Sales messages per module (editables desde admin)
  salesMessages: Record<string, string>;
  
  // CTA section
  ctaTitle: string;
  ctaDescription: string;
  ctaButtonWhatsappText: string;
  ctaButtonPlansText: string;
  
  // Plans info (para la tabla de soluciones)
  plans: {
    name: string;
    price: string;
    currency: string;
  }[];
}

export interface VulnerabilityEntry {
  id: number;
  pluginSlug: string;               // "elementor", "contact-form-7", etc.
  pluginName: string;                // "Elementor"
  affectedVersions: string;          // "<3.18.0"
  severity: 'low' | 'medium' | 'high' | 'critical';
  cveId: string;                     // "CVE-2024-XXXXX" o vacío
  description: string;               // Descripción breve de la vulnerabilidad
  fixedInVersion: string;            // "3.18.0"
  dateAdded: string;                 // ISO 8601
}
```

---

## 5. BACKEND PHP — ESPECIFICACIÓN DETALLADA

### 5.1 Configuración Base

#### .env.example
```env
# Modo
APP_ENV=production
APP_DEBUG=false

# API Keys (opcionales — mejoran cuotas)
GOOGLE_PAGESPEED_API_KEY=

# Admin
ADMIN_PASSWORD_HASH=                 # Generar con: php -r "echo password_hash('tupassword', PASSWORD_BCRYPT);"

# Rate Limiting
RATE_LIMIT_MAX_PER_HOUR=10

# Cache
CACHE_TTL_SECONDS=86400

# Lead notifications (opcional)
LEAD_WEBHOOK_URL=
LEAD_NOTIFICATION_EMAIL=

# CORS
ALLOWED_ORIGIN=https://audit.imaginawp.com

# Branding por defecto (se sobreescribe desde admin/settings en DB)
COMPANY_NAME=Imagina WP
COMPANY_WHATSAPP=+573001234567
```

#### .htaccess principal del backend
```apache
# Bloquear acceso directo a archivos sensibles
<FilesMatch "\.(env|db|sqlite|json|log|sql)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Bloquear acceso a carpetas protegidas
RedirectMatch 403 ^/audit/(lib|analyzers|config|data|database|cache|logs)/.*$
```

#### .htaccess del directorio /api/
```apache
RewriteEngine On

# Headers CORS
Header set Access-Control-Allow-Origin "%{ALLOWED_ORIGIN}e"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization"
Header set Access-Control-Allow-Credentials "true"

# Preflight OPTIONS
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"
```

#### database/schema.sql
```sql
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
    result_json TEXT NOT NULL,              -- JSON completo del AuditResult
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

-- Índices
CREATE INDEX IF NOT EXISTS idx_audits_domain ON audits(domain);
CREATE INDEX IF NOT EXISTS idx_audits_created ON audits(created_at);
CREATE INDEX IF NOT EXISTS idx_audits_has_contact ON audits(lead_email);
CREATE INDEX IF NOT EXISTS idx_rate_limits_ip ON rate_limits(ip_address, endpoint);
CREATE INDEX IF NOT EXISTS idx_vulnerabilities_slug ON vulnerabilities(plugin_slug);
```

### 5.2 Clase: lib/Fetcher.php

```
Wrapper seguro para cURL. Todas las peticiones HTTP externas pasan por aquí.

Características obligatorias:
- Timeout configurable (default: 10 segundos para requests individuales)
- User-Agent: "ImaginaAudit/1.0 (+https://imaginawp.com)"
- Follow redirects: máximo 5
- Tamaño máximo de respuesta: 5MB
- Anti-SSRF: rechazar URLs que apunten a IPs privadas (10.x.x.x, 192.168.x.x, 127.x.x.x, 172.16-31.x.x, 169.254.x.x, 0.0.0.0, ::1, localhost). Resolver DNS ANTES de hacer la petición y validar la IP.
- Retry automático: 1 reintento en caso de timeout
- Retornar objeto con: statusCode, headers, body, finalUrl, responseTime (ms)
- Manejar excepciones sin exponer detalles internos
- No ejecutar JavaScript (solo descarga HTML/texto)
```

### 5.3 Clase: lib/HtmlParser.php

```
Wrapper sobre DOMDocument para parsing HTML robusto.

Métodos necesarios:
- loadHtml(string $html): carga HTML suprimiendo errores de parsing (libxml_use_internal_errors)
- getMeta(string $name): obtiene contenido de una meta tag por name o property
- getTitle(): obtiene el contenido de <title>
- getHeadings(): retorna array de todos los headings con su nivel (h1, h2, etc.)
- getImages(): retorna array de todas las imágenes con src y alt
- getLinks(): retorna array de todos los enlaces con href y texto
- getScripts(): retorna array de todos los scripts con src
- getStylesheets(): retorna array de todos los links CSS con href
- querySelector(string $selector): búsqueda básica por tag, clase o id
- getTextContent(): texto visible sin tags ni scripts
- findInHtml(string $pattern): búsqueda por regex en el HTML crudo
```

### 5.4 Endpoint: api/audit.php (POST)

```
Flujo de ejecución:

1. Recibir JSON body: { url, leadName?, leadEmail?, leadWhatsapp?, leadCompany? }
2. Validar URL con UrlValidator (formato válido, resoluble por DNS, no IP privada)
3. Rate limit check: máx. X auditorías por IP por hora (consultar tabla rate_limits)
4. Cache check: buscar en tabla audits si misma URL fue escaneada en últimas 24 horas
   → Si existe en cache, retornar resultado cacheado
5. Fetch inicial: descargar HTML del homepage con Fetcher (timeout 15 segundos)
6. Ejecutar TODOS los analyzers. NOTA IMPORTANTE SOBRE PHP:
   PHP no tiene Promise.allSettled() como JS. Los analyzers se ejecutan secuencialmente
   PERO cada uno tiene su propio try-catch. Si un analyzer falla, los demás continúan.
   Para optimizar tiempo: reutilizar el HTML descargado una sola vez y pasárselo a todos
   los analyzers que lo necesiten (para que no descarguen el HTML cada uno).
   Solo el PerformanceAnalyzer necesita hacer una llamada HTTP adicional (a la API de Google).
7. Compilar AuditResult con AuditOrchestrator
8. Generar solutionMap automáticamente (cada métrica roja/amarilla → solución Imagina WP)
9. Calcular impacto económico
10. Guardar en tabla audits (lead data + resultado completo como JSON)
11. Si lead tiene email/WhatsApp, enviar notificación al admin (email o webhook)
12. Retornar AuditResult como JSON

Tiempo máximo de ejecución: configurar set_time_limit(120) para dar margen.
El escaneo real debería completarse en 15-45 segundos.
```

### 5.5 Analyzer: analyzers/WordPressDetector.php

```
Recibe: HTML descargado, URL original, headers de respuesta.
Retorna: ModuleResult con todas las métricas de identidad WordPress.

DETECCIÓN DE WORDPRESS (verificar en orden, marcar isWordPress=true al primer positivo):
1. Meta tag <meta name="generator" content="WordPress X.X.X">
2. Presencia de /wp-content/ en links CSS o JS del HTML
3. Presencia de /wp-includes/ en links del HTML
4. Link rel="https://api.w.org/" en HTML o en headers Link
5. Fetch /wp-json/ → respuesta 200 con JSON válido
6. Fetch /xmlrpc.php → respuesta 200 o 405 (Method Not Allowed confirma que existe)
7. Fetch /wp-login.php → respuesta 200 con formulario de login
8. Comentarios HTML con "WordPress" o "starter-theme"

VERSIÓN DE WORDPRESS:
- Extraer de meta generator si está presente
- Extraer de /feed/ o /feed/rss2/ → buscar <generator>https://wordpress.org/?v=X.X.X</generator>
- Extraer de /wp-json/ → a veces aparece en el response
- Comparar versión detectada con la última estable (hardcodear la última conocida y actualizar periódicamente)

DETECCIÓN DE TEMA:
- Extraer rutas /wp-content/themes/NOMBRE/ del HTML
- Intentar Fetch /wp-content/themes/NOMBRE/style.css → buscar "Theme Name:" y "Version:"
- Detectar child theme (si hay 2 temas referenciados)

DETECCIÓN DE PLUGINS:
- Buscar TODAS las rutas /wp-content/plugins/NOMBRE/ en CSS y JS del HTML
- Para cada plugin detectado:
  a. Intentar Fetch /wp-content/plugins/NOMBRE/readme.txt → extraer "Stable tag:" para versión
  b. Consultar API WordPress.org: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=NOMBRE
     → Extraer: version (última), rating, active_installs, last_updated
  c. Comparar versión detectada vs. última disponible
  d. Cruzar con tabla vulnerabilities de SQLite
  NOTA: Limitar a máximo 15 plugins consultados a la API para no alargar el escaneo.

VERIFICACIONES DE EXPOSICIÓN:
- REST API abierta: Fetch /wp-json/wp/v2/users (timeout 5s)
  → Si retorna array de usuarios = CRÍTICO (puntuación -25)
  → Extraer usernames encontrados para mostrar en el informe
- XML-RPC activo: Fetch /xmlrpc.php
  → Si responde (200 o 405) = riesgo medio (puntuación -10)
- Enumeración de usuarios: Fetch /?author=1 (no seguir redirect, solo ver Location header)
  → Si redirige a /author/NOMBRE/ = expuesto (puntuación -10)
- Debug mode: buscar "Fatal error:", "Warning:", "Notice:", "WP_DEBUG" en el HTML
  → Si encuentra errores PHP visibles = CRÍTICO
- Archivos sensibles: HEAD request (timeout 3s) a cada uno:
  /wp-config.php.bak, /wp-config.old, /wp-config.txt,
  /.env, /debug.log, /wp-content/debug.log, /error_log,
  /wp-content/uploads/wc-logs/, /backup.zip, /wp-content/backups/
  → Si alguno retorna 200 = MEGA CRÍTICO (puntuación -30 cada uno)
  NOTA: Hacer máximo 5-6 checks para no alargar el scan.

MÉTRICAS DEL MÓDULO:
- wp_detected: boolean — ¿Es WordPress?
- wp_version: string — Versión detectada o "No detectada"
- wp_version_current: boolean — ¿Está al día?
- theme_name: string — Nombre del tema
- theme_version: string — Versión del tema
- child_theme: boolean — ¿Usa child theme?
- plugins_count: number — Cantidad de plugins detectados
- plugins_outdated: number — Plugins desactualizados
- plugins_list: array — Lista con nombre, versión, estado
- rest_api_exposed: boolean — ¿Usuarios accesibles por REST?
- xmlrpc_active: boolean
- user_enumeration: boolean
- debug_mode: boolean
- sensitive_files: array — Archivos sensibles encontrados
- login_exposed: boolean — ¿wp-login.php accesible?

SCORING DEL MÓDULO:
- Base 100 puntos
- WP desactualizado: -20
- Sin child theme: -5
- Cada plugin desactualizado: -5 (máx. -25)
- REST API expone usuarios: -25
- XML-RPC activo: -10
- User enumeration: -10
- Debug mode visible: -15
- Cada archivo sensible: -30
- Login sin protección: -5
- Mínimo: 0
```

### 5.6 Analyzer: analyzers/SecurityAnalyzer.php

```
Recibe: URL, headers de respuesta HTTP, HTML descargado.
Retorna: ModuleResult con métricas de seguridad.

CERTIFICADO SSL:
- Usar stream_context_create() con ssl options para conectar al puerto 443
- stream_socket_client() + stream_context_get_params() para obtener certificado
- Extraer: issuer, validFrom, validTo, protocol
- Calcular días hasta expiración
- Score: válido >30 días = 100 | válido <30 días = 60 | expirado = 0 | sin SSL = 0

REDIRECCIÓN HTTP → HTTPS:
- Fetch http://dominio.com (sin seguir redirects)
- Verificar que retorna 301 o 302 con Location: https://
- No redirige = -15 puntos

HEADERS DE SEGURIDAD HTTP (analizar los response headers del fetch inicial):
Headers a verificar y puntaje:
| Header                    | Presente | Ausente |
|---------------------------|----------|---------|
| X-Content-Type-Options    | +14      | 0       |
| X-Frame-Options           | +14      | 0       |
| Content-Security-Policy   | +14      | 0       |
| Strict-Transport-Security | +14      | 0       |
| X-XSS-Protection          | +11      | 0       |
| Referrer-Policy            | +11      | 0       |
| Permissions-Policy         | +11      | 0       |
Bonus: +11 si todos están presentes
Total posible headers: 100 puntos

HEADERS QUE NO DEBERÍAN ESTAR EXPUESTOS:
- Server: con versión detallada (e.g., "Apache/2.4.41 (Ubuntu)") → -5 puntos y warning
- X-Powered-By: presente (e.g., "PHP/8.1.2") → -5 puntos y warning

PLUGINS CON VULNERABILIDADES:
- Para cada plugin detectado en WordPressDetector:
  Buscar en tabla vulnerabilities de SQLite por plugin_slug
  Si hay match de versión → CRÍTICO
  Cada plugin vulnerable: -20 puntos sobre el módulo

LOGIN EXPUESTO:
- Reutilizar dato de WordPressDetector (login_exposed)
- Si wp-login.php es accesible y no se detecta captcha/2FA → -10 puntos

DIRECTORY LISTING:
- Fetch /wp-content/uploads/ → si body contiene "Index of" = listing activo → -10
- Fetch /wp-content/plugins/ → mismo check → -10

SCORING DEL MÓDULO:
Promedio ponderado de:
- SSL (peso 25%): score del certificado
- Headers (peso 35%): score de headers
- Vulnerabilidades (peso 25%): 100 - (vulnerabilidades * 20), mínimo 0
- Accesos expuestos (peso 15%): 100 - penalizaciones por login/directory/headers expuestos
```

### 5.7 Analyzer: analyzers/PerformanceAnalyzer.php

```
Recibe: URL original.
Retorna: ModuleResult con métricas de rendimiento.

GOOGLE PAGESPEED INSIGHTS API:
Endpoint: https://www.googleapis.com/pagespeedonline/v5/runPagespeed
Parámetros:
  url: URL a analizar
  category: performance
  strategy: 'mobile' (primera llamada) y 'desktop' (segunda llamada)
  locale: es
  key: GOOGLE_PAGESPEED_API_KEY (de .env, opcional)

Hacer DOS llamadas cURL (una mobile, una desktop). Es la parte más lenta del escaneo.

MÉTRICAS A EXTRAER del JSON de respuesta:
- lighthouseResult.categories.performance.score → multiplicar por 100
- lighthouseResult.audits.first-contentful-paint.numericValue → FCP (ms)
- lighthouseResult.audits.largest-contentful-paint.numericValue → LCP (ms)
- lighthouseResult.audits.cumulative-layout-shift.numericValue → CLS
- lighthouseResult.audits.interactive.numericValue → TTI (ms)
- lighthouseResult.audits.speed-index.numericValue → Speed Index (ms)
- lighthouseResult.audits.total-blocking-time.numericValue → TBT (ms)
- lighthouseResult.audits.server-response-time.numericValue → TTFB (ms)

OPORTUNIDADES DE MEJORA:
Extraer de lighthouseResult.audits, solo las que tengan score < 1:
- render-blocking-resources
- uses-optimized-images
- uses-text-compression
- uses-responsive-images
- unused-javascript
- unused-css-rules
- offscreen-images
Para cada una extraer: title, displayValue, details.overallSavingsMs

MÉTRICAS PROPIAS (del fetch inicial):
- Compresión: header Content-Encoding (gzip, br, deflate, o ninguno)
- Cache: headers Cache-Control, Expires, ETag
- HTTP/2: versión del protocolo

CÁLCULO DE IMPACTO ECONÓMICO:
loadTimeSeconds = LCP / 1000
excessSeconds = max(0, loadTimeSeconds - 2.5)
conversionLossPercent = excessSeconds * 7
estimatedMonthlyVisits = 3000  (valor por defecto)
baseConversionRate = 0.02
avgConversionValue = 50
lostConversions = estimatedMonthlyVisits * baseConversionRate * (conversionLossPercent / 100)
monthlyLoss = round(lostConversions * avgConversionValue)

SCORING DEL MÓDULO:
- Score principal: PageSpeed mobile score (ya viene de 0-100 desde Google)
- Ajustes:
  Sin compresión GZIP/Brotli: -10
  Sin cache headers: -5
  TTFB > 800ms: -10
  TTFB > 1500ms: -20
- Mínimo: 0
```

### 5.8 Analyzer: analyzers/SeoAnalyzer.php

```
Recibe: HTML descargado, URL, headers.
Retorna: ModuleResult con métricas SEO.

META TAGS:
- <title>: extraer texto. Score: presente y 30-70 chars = 100 | presente pero muy corto/largo = 60 | ausente = 0
- <meta name="description">: extraer content. Score: presente y 120-160 chars = 100 | presente pero fuera de rango = 60 | ausente = 0
- <meta name="robots">: verificar que no tenga "noindex" (a menos que sea intencional)
- <link rel="canonical">: verificar presencia

OPEN GRAPH:
- og:title, og:description, og:image, og:url, og:type
- Score: todos presentes = 100 | parcial = proporcional | ninguno = 0

TWITTER CARDS:
- twitter:card, twitter:title, twitter:description, twitter:image
- Score: todos = 100 | parcial = proporcional | ninguno = 0

ESTRUCTURA DE ENCABEZADOS:
- Contar todos los H1, H2, H3, etc.
- Exactamente 1 H1 = score 100 | 0 H1 = score 0 | múltiples H1 = score 30
- Jerarquía lógica (no saltar niveles) = bonus

IMÁGENES:
- Contar todas las <img>
- Contar cuántas tienen alt no vacío
- Score: porcentaje de imágenes con alt (90%+ = 100, proporcional hacia abajo)

DATOS ESTRUCTURADOS:
- Buscar <script type="application/ld+json"> → parsear JSON → extraer @type
- Presente = 100 | Ausente = 0

SITEMAP:
- Fetch /sitemap.xml → verificar respuesta 200 con contenido XML
- Si no, Fetch /sitemap_index.xml
- Si no, buscar en robots.txt la línea "Sitemap:"
- Encontrado = 100 | No encontrado = 0

ROBOTS.TXT:
- Fetch /robots.txt → verificar respuesta 200
- Verificar que no bloquee todo (Disallow: /)
- Presente y correcto = 100 | Presente pero bloquea todo = 20 | Ausente = 30

FAVICON:
- Buscar <link rel="icon">, <link rel="shortcut icon">
- Presente = 100 | Ausente = 0 (penalización menor)

IDIOMA:
- Verificar <html lang="XX">
- Presente = 100 | Ausente = 0

CANTIDAD DE CONTENIDO:
- Contar palabras de texto visible en el body (excluir scripts, styles, tags)
- >500 palabras = 100 | 300-500 = 70 | <300 = 30

GOOGLE ANALYTICS:
- Buscar en HTML: gtag, GoogleAnalyticsObject, analytics.js, gtag/js?id=
- Presente = 100 | Ausente = 0

SCORING DEL MÓDULO:
Promedio ponderado:
- Title + Description: 20%
- H1 + estructura: 15%
- Open Graph: 10%
- Images alt: 10%
- Sitemap + Robots: 15%
- Schema/datos estructurados: 10%
- Analytics: 10%
- Otros (canonical, favicon, lang, contenido): 10%
```

### 5.9 Analyzer: analyzers/MobileAnalyzer.php

```
Recibe: HTML descargado, datos de PageSpeed mobile del PerformanceAnalyzer.
Retorna: ModuleResult.

VIEWPORT:
- Buscar <meta name="viewport">
- Verificar que contenga width=device-width
- Presente y correcto = 100 | Presente pero mal configurado = 50 | Ausente = 0

DATOS DE PAGESPEED MOBILE:
- Reutilizar el score mobile del PerformanceAnalyzer
- Extraer audits específicas de usabilidad si están disponibles

RESPONSIVE CHECK:
- Buscar media queries en los CSS inline o detectar frameworks responsivos (Bootstrap, Tailwind)
- Verificar si las imágenes usan srcset o <picture>

SCORING: viewport (30%) + PageSpeed mobile score (50%) + responsive indicators (20%)
```

### 5.10 Analyzer: analyzers/InfrastructureAnalyzer.php

```
Recibe: URL, headers de respuesta, IP del servidor.
Retorna: ModuleResult.

SERVIDOR WEB:
- Extraer header 'Server'
- Detectar: Apache, Nginx, LiteSpeed, OpenLiteSpeed, Cloudflare, IIS, otros
- Mapear a recomendación específica para cada tipo

HOSTING / PROVEEDOR:
- Resolver IP: gethostbyname(dominio)
- Identificar proveedor por rango de IP o reverse DNS (solo los más comunes)
- Detectar Cloudflare: header CF-Ray presente

PHP EXPUESTO:
- Header X-Powered-By: si está presente → warning
- Extraer versión de PHP si aparece

PROTOCOLO HTTP:
- Detectar HTTP/1.1 vs HTTP/2 vs HTTP/3
- HTTP/1.1 = penalización moderada

TTFB:
- Usar dato del PerformanceAnalyzer o medir propio con cURL (CURLINFO_STARTTRANSFER_TIME)
- <200ms = excelente | 200-500ms = bueno | 500-800ms = regular | >800ms = malo

CDN:
- Detectar por headers: CF-Ray (Cloudflare), X-Cache (Varnish), X-CDN, Via
- Sin CDN = recomendación

COMPRESIÓN:
- Header Content-Encoding del fetch inicial
- gzip o br = bueno | ninguno = malo

SCORING: TTFB (30%) + Servidor/protocolo (20%) + CDN (15%) + Compresión (15%) + Headers expuestos (10%) + PHP seguro (10%)
```

### 5.11 Analyzer: analyzers/ConversionAnalyzer.php

```
Recibe: HTML descargado, scripts detectados.
Retorna: ModuleResult.

Buscar en el HTML (tanto en scripts src como en inline scripts):

ANALYTICS:
- Google Analytics: 'gtag(', 'analytics.js', 'gtag/js?id=G-', 'gtag/js?id=UA-'
- Google Tag Manager: 'gtm.js', 'GTM-', 'googletagmanager.com'

PUBLICIDAD:
- Facebook Pixel: 'fbq(', 'facebook.com/tr?', 'fbevents.js'
- Google Ads: 'googleads.g.doubleclick.net', 'conversion.js'

CHAT EN VIVO:
- Tawk.to: 'tawk.to'
- Crisp: 'crisp.chat'
- Intercom: 'intercom'
- Tidio: 'tidio'
- JivoChat: 'jivo'
- Drift: 'drift.com'
- HubSpot chat: 'hubspot.com'
- WhatsApp links: 'wa.me/', 'api.whatsapp.com'
- JoinChat plugin: 'joinchat'

FORMULARIOS:
- Contact Form 7: 'wpcf7'
- Gravity Forms: 'gform'
- WPForms: 'wpforms'
- Elementor Forms: 'elementor-form'
- Otros: cualquier <form> con action

NOTIFICACIONES PUSH:
- OneSignal: 'onesignal.com'
- Gravitec: 'gravitec.net'
- PushEngage: 'pushengage.com'
- WebPushr: 'webpushr.com'

EMAIL MARKETING:
- Mailchimp: 'mailchimp.com', 'mc.js'
- Brevo/Sendinblue: 'brevo.com', 'sendinblue'

LEGAL/COOKIES:
- Cookie banners: CookieBot, CookieYes, Complianz, GDPR Cookie Consent
- Links a /privacy-policy, /politica-de-privacidad, /aviso-legal, /terms

REDES SOCIALES:
- Buscar links a facebook.com, instagram.com, twitter.com, x.com, linkedin.com, youtube.com, tiktok.com

SCORING:
Cada herramienta detectada suma puntos:
- Analytics instalado: +25
- Chat/WhatsApp: +20
- Formulario de contacto: +20
- Notificaciones push: +10
- Cookies/legal: +10
- Redes sociales (al menos 2): +10
- Facebook Pixel: +5
Total posible: 100
```

### 5.12 Analyzer: analyzers/AuditOrchestrator.php

```
Clase principal que:
1. Recibe la URL y datos del lead
2. Hace el fetch inicial del HTML una sola vez
3. Instancia cada analyzer y les pasa el HTML + headers + URL
4. Ejecuta cada analyzer dentro de try-catch individual
5. Si un analyzer falla, su módulo se marca con score = null y level = 'unknown'
6. Recopila todos los ModuleResult
7. Calcula el globalScore con los pesos configurados
8. Genera el solutionMap: por cada métrica con level 'critical' o 'warning',
   crear un SolutionItem con el problema y la solución Imagina WP correspondiente
   (la solución viene del campo imaginaSolution de cada MetricResult)
9. Calcula el impacto económico
10. Genera el conteo de issues (critical, warning, good)
11. Retorna el AuditResult completo
```

### 5.13 Admin API Endpoints

```
TODOS los endpoints admin requieren sesión PHP activa (verificar con Auth.php).

POST /api/admin/login
- Body: { password: string }
- Validar con password_verify() contra el hash en settings o .env
- Iniciar sesión PHP: $_SESSION['admin_authenticated'] = true
- Retornar: { success: true }

POST /api/admin/logout
- Destruir sesión PHP
- Retornar: { success: true }

GET /api/admin/session
- Verificar si $_SESSION['admin_authenticated'] === true
- Retornar: { authenticated: true/false }

GET /api/admin/dashboard
- Query SQLite para obtener:
  COUNT total audits, COUNT hoy, COUNT esta semana, COUNT este mes
  AVG global_score
  COUNT WHERE lead_email IS NOT NULL (leads con contacto)
  Distribución de scores (GROUP BY rangos)
  Últimas 10 auditorías
- Retornar: DashboardStats

GET /api/admin/leads?page=1&limit=20&sort=date&filter=all
- Paginación de auditorías
- Filtros: all | with_contact | critical_score | this_week
- Sort: date_desc | date_asc | score_asc | score_desc
- Retornar: { leads: LeadSummary[], total: number, page: number }

GET /api/admin/lead-detail?id=UUID
- Retornar el AuditResult completo (result_json de la tabla audits)

DELETE /api/admin/leads?id=UUID
- Eliminar una auditoría específica

GET /api/admin/settings
- Retornar todas las settings de la tabla settings como AppSettings

PUT /api/admin/settings
- Body: Partial<AppSettings>
- Actualizar cada key en la tabla settings
- Retornar: { success: true }

GET /api/admin/vulnerabilities?page=1&limit=50
- Listar vulnerabilidades con paginación
- Retornar: { vulnerabilities: VulnerabilityEntry[], total: number }

POST /api/admin/vulnerabilities
- Body: VulnerabilityEntry (sin id)
- Insertar en tabla vulnerabilities
- Retornar: { success: true, id: number }

PUT /api/admin/vulnerabilities
- Body: VulnerabilityEntry (con id)
- Actualizar registro

DELETE /api/admin/vulnerabilities?id=NUMBER
- Eliminar registro

GET /api/config
- (ENDPOINT PÚBLICO, sin autenticación)
- Retornar solo los datos de branding y textos necesarios para el frontend público:
  companyName, companyUrl, companyWhatsapp, companyPlansUrl, logoUrl,
  ctaTitle, ctaDescription, plans, salesMessages
- NO exponer API keys, password hash, ni datos internos
```

---

## 6. FRONTEND — ESPECIFICACIÓN DETALLADA

### 6.1 Diseño Visual

```css
/* Paleta de colores — variables CSS en index.css */

/* Modo oscuro (por defecto) */
:root {
  --bg-primary:       #0B0F1A;     /* Fondo principal — casi negro azulado */
  --bg-secondary:     #111827;     /* Fondo de cards */
  --bg-tertiary:      #1F2937;     /* Fondo de inputs, hovers */
  --bg-glass:         rgba(17, 24, 39, 0.7);  /* Glassmorphism */
  
  --text-primary:     #F1F5F9;     /* Texto principal */
  --text-secondary:   #94A3B8;     /* Texto secundario/muted */
  --text-tertiary:    #64748B;     /* Texto muy sutil */
  
  --border-default:   #1E293B;     /* Bordes sutiles */
  --border-hover:     #334155;     /* Bordes hover */
  
  --accent-primary:   #3B82F6;     /* Azul principal — botones, links */
  --accent-hover:     #2563EB;     /* Azul hover */
  --accent-glow:      rgba(59, 130, 246, 0.15);  /* Glow sutil */
  
  --color-critical:   #EF4444;     /* Rojo — problemas críticos */
  --color-warning:    #F59E0B;     /* Ámbar — advertencias */
  --color-good:       #10B981;     /* Verde — bien */
  --color-excellent:  #059669;     /* Verde intenso — excelente */
  --color-info:       #6B7280;     /* Gris — informativo */
  
  --radius:           12px;        /* Border radius de cards */
  --radius-sm:        8px;         /* Border radius de inputs/botones */
}

/* Fuente: Inter (importar de Google Fonts) */
font-family: 'Inter', system-ui, -apple-system, sans-serif;

/* Estilos de glass cards */
.glass-card {
  background: var(--bg-glass);
  backdrop-filter: blur(12px);
  border: 1px solid var(--border-default);
  border-radius: var(--radius);
}

/* Glow sutil en elementos activos */
.glow {
  box-shadow: 0 0 20px var(--accent-glow);
}
```

**Principios de diseño:**
- Dark mode por defecto (toggle a light mode disponible)
- Glassmorphism sutil en cards principales
- Gradiente sutil en el hero/header (de --bg-primary a ligeramente más claro)
- Animaciones con Framer Motion: entradas escalonadas (stagger children), spring physics en gauges
- Iconos Lucide en línea fina (strokeWidth 1.5)
- Espaciado generoso — la herramienta debe respirar y no sentirse abrumadora
- Responsive: mobile-first, breakpoints en sm(640px), md(768px), lg(1024px)

### 6.2 Página: HomePage

```
Estructura visual:

HEADER
- Logo Imagina WP (izquierda)
- Toggle idioma ES/EN (derecha)
- Toggle dark/light mode (derecha)

HERO SECTION (centrado)
- Headline: "Auditoría Gratuita de tu WordPress"
- Subheadline: "Descubre en 30 segundos qué tan seguro, rápido y optimizado está tu sitio web"
- Gradiente sutil de fondo

FORMULARIO (centrado, card glass)
- Input grande: "https://tusitio.com" (con icono Globe)
- Input: Nombre (opcional, con placeholder "Tu nombre")
- Input: Email (opcional)
- Input: WhatsApp (opcional, con placeholder "+57...")
- Botón grande: "🔍 Auditar Mi Sitio Gratis" (azul, con hover glow)
- Micro-copy debajo: "✓ Sin instalar nada · ✓ 100% externo · ✓ Resultados en 30 seg"

FEATURES GRID (debajo del formulario)
- 8 cards pequeñas (2x4 en desktop, 2x4 en mobile) con icono + nombre de cada módulo:
  🛡️ Seguridad | ⚡ Rendimiento | 🔍 SEO | 🧩 WordPress
  📱 Móvil | 🖥️ Servidor | 📊 Conversión | 💾 Backups
- Cada card con animación de entrada escalonada

TRUST BAR
- "Con la experiencia de 15 años de maestría exclusiva en WordPress"
- Logos de herramientas: Elementor, WP Rocket, Rank Math, Gravity Forms, Cloudflare, etc.

FOOTER
- © Imagina WP | Link a política de privacidad
```

### 6.3 Pantalla: ScanningAnimation

```
Se muestra INMEDIATAMENTE después de hacer submit del formulario.
El frontend NO espera la respuesta del backend. En su lugar:
1. Muestra la animación simulada.
2. Hace el POST /api/audit en paralelo.
3. Cuando la API responde, completa la animación y navega a resultados.

Si la API responde ANTES de que la animación termine (poco probable), 
espera a que la animación llegue al 100% y luego navega.

Si la API tarda más de 60 segundos, mostrar error amigable.

Diseño:
- Fondo oscuro con partículas sutiles o grid animado (CSS puro)
- Dominio escaneado en grande: "Escaneando tusitio.com..."
- Barra de progreso principal (0% → 100%) con animación smooth
- Lista de módulos con estados:
  ✅ completado (verde)
  🔄 analizando... (azul, con spinner)
  ⏳ pendiente (gris)
- Texto dinámico debajo: "Verificando certificado SSL...", "Consultando Google PageSpeed...", etc.
- Los estados cambian automáticamente con intervalos de 2-4 segundos (simulado)
- Efecto de "terminal" o "consola" sutil para dar sensación técnica
```

### 6.4 Página: ResultsPage

```
HEADER STICKY
- Logo | Dominio escaneado | Botón "Descargar PDF" | Botón "Nueva Auditoría"

SCORE OVERVIEW (hero de resultados)
- Score global grande en el centro (ScoreGauge animado, gauge circular SVG)
- Clasificación: "Deficiente" con color de semáforo
- Badges: "12 críticos · 7 importantes · 4 menores"
- Grid de 8 mini-gauges (uno por módulo) con score y semáforo
  → Al hacer clic en un mini-gauge, scroll suave a ese módulo

NAVIGATION TABS (sticky debajo del header en scroll)
- Tabs horizontales scrollables: Seguridad | Rendimiento | SEO | WordPress | Móvil | Servidor | Conversión | Backups

MÓDULOS (uno debajo del otro)
Cada módulo es un ModuleCard con:
- Header: icono + nombre + score gauge pequeño + badge de nivel
- Mensaje de resumen: "Tu sitio tiene una puntuación de seguridad de 22/100"
- Lista de métricas (MetricRow):
  Cada métrica:
  - Icono semáforo (colored dot o shield icon)
  - Nombre de la métrica
  - Valor detectado (o "No detectado")
  - Expandible (Accordion): al hacer clic muestra:
    - Descripción del problema
    - Recomendación general
    - "✅ Cómo lo resuelve Imagina WP: [texto específico]"
- Mensaje de venta al final del módulo (texto editable desde admin)

IMPACTO ECONÓMICO (sección especial entre Rendimiento y SEO)
- Card destacada con ícono de dinero
- "Estimamos que tu sitio podría estar perdiendo ~$XXX USD/mes por problemas de velocidad"
- Disclaimer: "Estimación basada en promedios de la industria"

TABLA DE SOLUCIONES (SolutionMapping)
- Tabla de 3 columnas: Problema | Estado (semáforo) | Solución Imagina WP
- Solo muestra los problemas detectados (critical + warning)
- Cada fila de solución tiene badge del plan que lo incluye (Basic/Pro/Custom)

CTA FINAL (CtaSection)
- Card grande con gradiente sutil
- Título: "Todos estos problemas tienen solución"
- Descripción: texto editable desde admin
- Botones:
  [💬 Hablar con un Experto por WhatsApp] (verde, link a wa.me/)
  [📋 Ver Planes y Precios] (azul, link a imaginawp.com/mensualidad)
- Logos de herramientas incluidas
- "15 años de experiencia exclusiva en WordPress"

FOOTER
```

### 6.5 Panel Admin

```
Acceso: /admin (lazy loaded, no se carga hasta que se navega a /admin)

LOGIN (/admin)
- Card centrada con logo
- Input de contraseña + botón "Ingresar"
- Error toast si la contraseña es incorrecta

ADMIN LAYOUT (después de login)
- Sidebar izquierda (colapsable en mobile):
  📊 Dashboard
  👥 Leads y Auditorías
  ⚙️ Configuración General
  📝 Textos y Mensajes
  🏷️ Planes y Precios
  📊 Scoring y Umbrales
  🛡️ Vulnerabilidades
  🚪 Cerrar Sesión

DASHBOARD
- Cards de métricas: Total auditorías | Leads con contacto | Auditorías hoy | Score promedio
- Gráfico de barras: distribución de scores (Recharts)
- Tabla de últimas 10 auditorías (clic para ver detalle)

LEADS Y AUDITORÍAS
- Tabla paginada con columnas: Fecha | Dominio | Nombre | Email | WhatsApp | Score | Acciones
- Filtros: Todos | Con datos de contacto | Score crítico | Esta semana
- Ordenar por: Fecha | Score
- Acciones: Ver informe completo | Abrir WhatsApp | Copiar email | Eliminar
- Click en "Ver informe" abre el mismo ResultsPage pero con datos guardados

CONFIGURACIÓN GENERAL
- Formulario con campos:
  Nombre de la empresa, URL, WhatsApp, Email, URL de planes
  Logo URL
  API Key de Google PageSpeed (opcional)
  Contraseña admin (cambiar)

TEXTOS Y MENSAJES
- Textarea para el mensaje de venta de CADA módulo (8 textareas)
- Textarea para título del CTA, descripción del CTA, texto de botones
- Preview en vivo del CTA mientras editas

PLANES Y PRECIOS
- Tabla editable con los planes (nombre, precio, moneda)
- Se usan en la tabla de soluciones y en el CTA

SCORING Y UMBRALES
- Sliders o inputs numéricos para:
  - Peso de cada módulo (deben sumar 1.0)
  - Umbral de excellent/good/warning/critical
- Preview de cómo cambiaría un score ejemplo

VULNERABILIDADES
- Tabla CRUD: slug, nombre, versiones afectadas, severidad, CVE, descripción, versión fix
- Botón "Agregar vulnerabilidad"
- Editar y eliminar inline
- Búsqueda por nombre de plugin
```

### 6.6 Routing y Lazy Loading

```typescript
// App.tsx
const AdminPage = lazy(() => import('./pages/AdminPage'));

<Routes>
  <Route path="/" element={<HomePage />} />
  <Route path="/results/:auditId" element={<ResultsPage />} />
  <Route path="/admin/*" element={
    <Suspense fallback={<LoadingSpinner />}>
      <AdminPage />
    </Suspense>
  } />
  <Route path="*" element={<NotFoundPage />} />
</Routes>
```

Esto garantiza que el código del admin NO se descarga a menos que alguien navegue a /admin. Los prospectos nunca cargan ese código.

### 6.7 Generación de PDF

```
Usar html2pdf.js para renderizar un componente React oculto (PdfReport.tsx) a PDF.
El PDF debe contener:

Página 1: Portada
- Logo Imagina WP
- "Informe de Auditoría Web"
- URL auditada + fecha
- Score global grande con clasificación

Página 2: Resumen ejecutivo
- Grid de 8 módulos con score y semáforo
- Conteo de issues
- Impacto económico estimado

Páginas 3-6: Detalle de módulos (2 módulos por página aprox.)
- Nombre + score + lista de métricas con semáforo
- Solo las métricas más relevantes (no todas, para no hacer un PDF de 20 páginas)

Página final: Soluciones
- Tabla de problemas → soluciones
- CTA con datos de contacto de Imagina WP
- "Informe generado por Imagina Audit — imaginawp.com"

Objetivo: PDF de 5-8 páginas máximo, tamaño < 3MB
```

---

## 7. SEGURIDAD

### Backend PHP
- Rate limiting por IP (tabla SQLite)
- Anti-SSRF en Fetcher (bloquear IPs privadas y localhost)
- Validación de inputs con filtros PHP (filter_var, filter_input)
- Prepared statements SIEMPRE para queries SQLite (nunca concatenar SQL)
- password_hash() / password_verify() para contraseña admin
- Sesiones PHP con session_regenerate_id() en login
- set_time_limit(120) solo en el endpoint de audit, no globalmente
- .htaccess para bloquear acceso a archivos sensibles (.env, .db, .json, logs)
- Headers de seguridad en respuestas de la API
- No exponer errores PHP en producción (display_errors = Off)
- Log de errores a archivo

### Frontend
- No almacenar tokens en localStorage (usar cookies httpOnly vía sesión PHP)
- Sanitizar cualquier dato que venga del backend antes de renderizarlo
- CSP headers servidos por .htaccess del hosting
- Las rutas /admin/* verifican autenticación antes de renderizar

---

## 8. DEPLOY EN HOSTING COMPARTIDO

### Instrucciones paso a paso

```bash
# 1. En tu máquina local (donde tienes Node.js instalado):
cd frontend
npm install
npm run build
# Esto genera la carpeta frontend/dist/ con HTML/CSS/JS estáticos

# 2. Subir al hosting vía FTP o File Manager de cPanel:
# Crear carpeta: public_html/audit/
# Subir el contenido de frontend/dist/ → public_html/audit/
# Subir la carpeta backend/api/ → public_html/audit/api/
# Subir la carpeta backend/lib/ → public_html/audit/lib/
# Subir la carpeta backend/analyzers/ → public_html/audit/analyzers/
# Subir la carpeta backend/config/ → public_html/audit/config/
# Subir la carpeta backend/data/ → public_html/audit/data/
# Subir backend/database/ → public_html/audit/database/
# Crear carpetas vacías: public_html/audit/cache/ y public_html/audit/logs/
# Subir archivo backend/.env → public_html/audit/.env

# 3. Configurar permisos:
# cache/ → 755
# logs/ → 755
# database/ → 755
# .env → 600

# 4. Ejecutar el seeder de la base de datos:
# Abrir en el navegador: https://tusitio.com/audit/database/seed.php
# (Este archivo crea la DB SQLite y los datos iniciales)
# IMPORTANTE: Después de ejecutar, eliminar o renombrar seed.php por seguridad

# 5. Configurar .htaccess en /audit/ para que React Router funcione:
# (Ya incluido en el build, pero verificar que mod_rewrite esté activo)
```

### .htaccess para React Router (en /audit/)
```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /audit/
  
  # No reescribir archivos reales ni la carpeta api/
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !^/audit/api/
  
  # Redirigir todo lo demás a index.html (React Router)
  RewriteRule ^(.*)$ index.html [L]
</IfModule>
```

### Requisitos mínimos del hosting
- PHP 8.0+ (idealmente 8.1+)
- Extensiones: curl, dom, json, openssl, mbstring, pdo_sqlite
- mod_rewrite habilitado (Apache)
- Permite archivos .htaccess
- Al menos 256MB de memoria PHP (para el endpoint de audit)
- max_execution_time configurable (necesitamos 120 segundos mínimo para el audit)

---

## 9. CONFIGURACIÓN DINÁMICA vs ESTÁTICA

### Contenido que se carga dinámicamente desde el backend (editable sin rebuild):
- Nombre de empresa, WhatsApp, URL de planes, logo
- Mensajes de venta por módulo
- Textos del CTA
- Umbrales de scoring y pesos de módulos
- Planes y precios
- Base de vulnerabilidades

El frontend hace GET /api/config al cargar para obtener estos datos.
Se cachean en el store de Zustand durante la sesión del usuario.

### Contenido estático en el código (requiere rebuild para cambiar):
- Estructura de la UI y componentes
- Lógica de las animaciones
- Diseño visual y estilos
- Nombres y descripciones de las métricas
- Textos base de recomendaciones (los genéricos que aplican siempre)

---

## 10. FASES DE DESARROLLO

### Fase 1 — MVP (prioridad máxima)
- [ ] Estructura de carpetas y archivos base
- [ ] Backend: .env, Database.php, schema.sql, seed.php
- [ ] Backend: Fetcher.php, HtmlParser.php, UrlValidator.php, Cache.php
- [ ] Backend: WordPressDetector.php
- [ ] Backend: SecurityAnalyzer.php
- [ ] Backend: PerformanceAnalyzer.php (integración PageSpeed API)
- [ ] Backend: SeoAnalyzer.php
- [ ] Backend: InfrastructureAnalyzer.php
- [ ] Backend: ConversionAnalyzer.php
- [ ] Backend: MobileAnalyzer.php
- [ ] Backend: AuditOrchestrator.php + Scoring.php
- [ ] Backend: api/audit.php + api/config.php + api/health.php
- [ ] Backend: .htaccess en todas las carpetas protegidas
- [ ] Frontend: Setup Vite + React + Tailwind + shadcn/ui
- [ ] Frontend: HomePage con formulario
- [ ] Frontend: ScanningAnimation
- [ ] Frontend: ResultsPage completa con todos los módulos
- [ ] Frontend: ScoreGauge, MetricRow, ModuleCard, SemaphoreIcon
- [ ] Frontend: SolutionMapping + EconomicImpact + CtaSection
- [ ] Frontend: Generación de PDF
- [ ] Frontend: Responsive mobile
- [ ] Testing con 5+ sitios WordPress reales
- [ ] Deploy en hosting compartido

### Fase 2 — Admin + Mejoras
- [ ] Backend: api/admin/* (todos los endpoints admin)
- [ ] Frontend: AdminLogin + AdminLayout + Sidebar
- [ ] Frontend: DashboardPage con gráficos
- [ ] Frontend: LeadsTable + LeadDetail
- [ ] Frontend: Settings (General, Messages, Plans, Scoring)
- [ ] Frontend: VulnerabilityManager (CRUD)
- [ ] Toggle dark/light mode
- [ ] Toggle ES/EN
- [ ] Notificación de nuevo lead por email (PHP mail())

### Fase 3 — Evolución
- [ ] Comparación de 2 sitios lado a lado
- [ ] Historial de auditorías por dominio (comparación temporal)
- [ ] Widget embebible para otros sitios
- [ ] Envío de resumen por WhatsApp API
- [ ] Exportar leads a CSV desde admin
- [ ] Base de vulnerabilidades auto-actualizable (cron job o feed RSS de WPVulnDB)

---

## 11. INSTRUCCIONES PARA CLAUDE CODE

### Orden de desarrollo:
1. Crear TODA la estructura de carpetas primero.
2. Implementar backend PHP: empezar por las clases utilitarias (Fetcher, HtmlParser, Database), luego los analyzers uno por uno, luego el orquestador, luego las rutas API.
3. Implementar frontend React: empezar por setup y componentes base, luego las páginas, luego la integración con el backend.
4. El admin (Fase 2) se hace DESPUÉS de que la Fase 1 funcione completamente.

### Principios de código:
- **PHP:** Clases con namespace simulado (cada archivo = una clase). Sin framework. PDO para SQLite. Prepared statements SIEMPRE. Try-catch en cada analyzer. Comentarios en español.
- **React/TypeScript:** TypeScript estricto (no usar `any`). Componentes funcionales con hooks. Separar lógica de presentación. Comentarios en español.
- **Ambos:** Manejo de errores robusto. Si un analyzer falla, los demás continúan. El usuario nunca ve un stack trace o error técnico.

### Notas de branding:
- La app es de marca Imagina WP. No mencionar "Claude", "Anthropic", ni ninguna otra herramienta de desarrollo en la UI.
- El tono de los textos es profesional pero accesible. Evitar jerga excesiva en las recomendaciones.
- Cada recomendación debe terminar con cómo Imagina WP lo resuelve.
- El diseño dark mode debe verse premium y tecnológico, no "hacker".

### Testing:
- Probar con estas URLs como mínimo:
  - Un sitio WordPress conocido con problemas (buscar uno real)
  - imaginawp.com (debería puntuar bien)
  - Un sitio WordPress de cliente que sepas que tiene problemas
  - Un sitio que NO sea WordPress (para verificar detección correcta)
  - Un sitio con HTTPS expirado o sin HTTPS

---

*Última actualización: Abril 2026*
*Proyecto: Imagina Audit Tool v2.0*
*Stack: React + Vite + Tailwind + shadcn/ui + Framer Motion (frontend) | PHP + SQLite (backend)*
*Deploy: Hosting compartido con cPanel*
