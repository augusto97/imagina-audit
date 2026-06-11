# Imagina Audit — Release branch

This branch holds the **ready-to-install build artifact**. Nothing else.

## Download

The latest packaged build sits at the root of this branch:

- **`imagina-audit-v2.3.2.zip`** (~1.2 MB) — current

### Changelog

- **v2.3.2** — fix crítico del `kickDrain`: la app no estaba disparando ningún escaneo, solo el cron del sistema procesaba la cola. Causa: `kickDrain` llamaba a `Database::setting('app_url', '')` pensando que era un getter, pero `setting($key, $value)` es un SETTER (upsert). Cada kick sobrescribía `app_url` con string vacío, recibía el `rowCount` (int 0 o 1) en lugar del valor, y construía la URL del self-call como `"0/cron/drain-queue.php"` o `"1/cron/..."` — ambas inválidas, curl fallaba siempre. Fix: leer con `scalar("SELECT value FROM settings WHERE key = 'app_url'")` y rechazar valores no-URL (auto-saneamiento de la basura `"0"`/`"1"` que pudo quedar de runs anteriores).

- **v2.3.1** — fix audits que se quedaban encolados ("stuck at 5%") en hosting compartido. v2.3.0 cambió `kickDrain` para resolver la URL del self-call desde `SERVER_NAME` en vez de `HTTP_HOST` (por una preocupación de Host-spoofing). Pero en hosting compartido `SERVER_NAME` suele ser `localhost` o el nombre interno del vhost, no el dominio real → el self-kick HTTP apuntaba al host equivocado y nunca disparaba el drain. Con `shell_exec` deshabilitado (común en compartido) y sin cron configurado, los audits encolados quedaban congelados. Fix defense-in-depth: **(1)** la resolución de URL de `kickDrain` ahora es `app_url` setting > `HTTP_HOST` > `SERVER_NAME` > localhost (el token de cron se valida con `hash_equals` en el receptor, así que un Host forjado solo puede enrutar la llamada fire-and-forget a otro lado, nunca procesar trabajo). **(2)** `scan-progress.php` re-dispara el drain en cada poll mientras un job sigue `queued` con un slot libre — como el frontend hace polling cada ~1.5s, el propio polling impulsa la cola aunque `shell_exec` esté deshabilitado, el self-kick inicial haya fallado y no exista cron; el dequeue atómico de v2.3.0 hace que kicks solapados sean seguros. **(3)** `/admin/queue` ahora muestra una tarjeta con el **comando de cron exacto** (ruta resuelta) listo para copiar a cPanel → Cron Jobs.

  **Recomendación de cron (red de seguridad):** en cPanel → Cron Jobs, cada 1 minuto: `php /ruta/a/tu/audit/cron/drain-queue.php` — el comando exacto con tu ruta aparece en `/admin/queue`.

- **v2.3.0** — hardening transversal post-auditoría. Resultado de cinco agentes paralelos revisando core libs, seguridad, endpoints, analyzers y frontend.

  **Core**: `date_default_timezone_set('UTC')` global cierra el mismatch PHP-local vs DB-UTC (en hostings no-UTC el reaper mataba todo job 'running' al instante y las ventanas de rate-limit/cuota se corrían horas). `QueueManager::dequeueNext` ahora hace claim atómico con `UPDATE … WHERE id=? AND status='queued'` + verificación de rowCount — causa raíz real del bug v2.2.1 (dos drainers tomando el mismo job y envenenando el failure-cache). `Database::traced` no reintenta statements dentro de transacciones abiertas (un deadlock MySQL 1213 hace rollback implícito de toda la transacción). Failure-cache clasifica errores: no envenena con "Job huérfano", "Error guardando", etc. Scoring corrige `DivisionByZeroError` si los pesos llegan a 0. `Project::pluginsDiff` lee el metric id correcto (`wp_plugins`) — el diff de plugins added/removed estaba muerto desde siempre. `kickDrain` usa `app_url`/`SERVER_NAME` no `HTTP_HOST` spoofeable. Cache escribe atómicamente con tmp+rename (readers ya no observan JSON truncado). Migrator emula `CREATE INDEX IF NOT EXISTS` para Oracle MySQL (solo MariaDB lo soporta nativo) y registra schema_migrations dentro de la transacción SQLite. `CrossDbMigrator` añade tabla `login_attempts` faltante, batch adaptativo de 20 para tablas con blobs gzip (antes OOM-eaba a 256M), ORDER BY determinista. `EnvWriter` strip CR/LF anti-inyección. `Mailer` sanitiza CR/LF, dot-stuffing, verifica códigos SMTP. `JsonStore::encode` loguea fallo (antes silencioso). `Fetcher` aborta responses chunked >5MB con `WRITEFUNCTION` (antes solo `CURLOPT_MAXFILESIZE` que requiere Content-Length); memo-cache por scan elimina ~10 requests redundantes (~5-10s menos por scan WP).

  **Seguridad**: `setup/install.php` y `setup/test-db.php` ya no se gatean solo por el archivo `.installed` — chequean también DB+migraciones+admin. Sin esto, si el flag desaparecía (re-upload del zip, host limpiando `/data/`), un atacante no autenticado podía sobrescribir `admin_password_hash` o usar test-db como port scanner interno. Todos los crons unificados a `hash_equals` + `ensureCronToken()`, sin el fallback al literal `'cambiar-este-token'` que dejaba endpoints abiertos a quien lo adivinara. `history.php` scoped por sesión (admin todos, user los propios, anónimo solo `user_id IS NULL`) — antes leakeaba audits privados a cualquier visitante. `compare.php` scopea su cache + setea Translator lang. `settings.php` enmascara `google_pagespeed_api_key` como las otras keys y exige mínimo 10 chars al cambiar el password admin (consistente con install). `migrate-database.php` y `ai-translate.php` ahora hacen `session_write_close()` tras `requireAuth` (operaciones largas que bloqueaban el admin — patrón v2.0.8).

  **Analyzers (credibilidad del informe)**: el problema más importante para un producto de ventas era el falso positivo por **soft-404**. Si un sitio devolvía su 404 con status 200 (muy común), el orquestador marcaba 8 archivos sensibles + `.git` + `readme.html` + `install.php` como "expuestos" → informe rojo catastrófico **y falso** ante un prospecto. El nuevo `detectSoft404` hace una probe a una URL inexistente al inicio del scan; si el sitio responde 200 con body similar al home, los checks de archivos sensibles devuelven `unknown` en vez de "expuesto". Adicional: `WordPressDetector` valida firma de contenido además del status (magic bytes ZIP, `define(` en wp-config.bak, `KEY=VALUE` en .env). `looksLikeChallenge` detecta páginas de Cloudflare/WAF y aborta el scan con mensaje claro (antes guardaba un informe basura). `checkDebugMode` exige patrón completo de error PHP (`in /path on line N`) — antes cualquier texto con "Warning:" marcaba el sitio como crítico. SPF y DMARC se consultan ahora sobre el dominio registrable (apex), no `www.X` — antes falso "sin SPF/DMARC" para casi todo sitio con www. Email regex filtra TLDs de imagen (no más "logo@2x.png" como email expuesto). robots.txt parsea por bloques User-agent: un `Disallow: /` dentro de `User-agent: GPTBot` ya no marca el sitio como "bloquea indexación". PageSpeed sin score devuelve `null` (level `unknown`) en vez de 50 mágico que castigaba/regalaba arbitrariamente. Firmas reales de plugins de cache (antes había 3 entradas placeholder `"Starter starter starter"` sin uso, dejando WP Super Cache y LiteSpeed sin detectar).

  **Frontend**: `useAudit` polling con token de cancelación — antes el `while` sobrevivía 15 min al unmount o re-scan, dos loops pisaban el store con resultados distintos. `HistorySection` ahora es lazy + Suspense — saca Recharts (~341 KB) del preload de la landing pública. `ScoreGauge` cancela `requestAnimationFrame` en cleanup (no más setState tras unmount ni flicker). ErrorBoundary genérico envuelve `<Routes>` (un error de render en una ruta pública ya no deja al prospecto en pantalla blanca). `SettingsQueue` con `try/finally` en la carga inicial. `PdfReport` con `toast.error` en catch (antes silencioso). `formatCurrency` respeta `i18n.language` (no hardcoded `es-CO`). `useAuth`/`useUser` single-flight para session checks (AdminPage + AdminLayout dejaron de duplicar `/admin/session.php`). SetupGate cachea `installed=true` en localStorage. `MODULE_NAMES` ahora se resuelve via i18n con fallback al record (traducciones funcionan en el grid de features). Errores del polling movidos a `public.scan_error_*` (antes hardcoded en español). Borrado `SCAN_STEPS` (código muerto).

- **v2.2.3** — fix audits being scanned in English regardless of the language the visitor requested. fix audits being scanned in English regardless of the language the visitor requested. Since v2.0.8 the architecture became queue-only: `audit.php` reads `body.lang` and sets the translator, but it never carried that lang into the job row. Later `drain-queue.php` (cron or HTTP kick) picked up the job and ran `processJob` with no lang context, so the `Translator` silently fell through to `DEFAULT_LANG = 'en'`. Every metric name, description, recommendation and imagina-solution string got frozen into `result_json` in English — even when the admin viewed the lead in a Spanish UI. Fix: persist `lang` inside `lead_data_json` and re-apply it via `Translator::setLang()` at the top of `processJob`. Existing audits keep their stored language; re-scan from the public form to refresh them.
- **v2.2.2** — i18n completeness pass. 19 hardcoded strings extracted to `t()` across admin, public, layout and dashboard components (`AdminPage` ErrorBoundary, `DashboardPage` error/retry, `VulnerabilityManager` severity labels, `AdminLayout` collapse button, `Footer` admin link, `Header` compare button fallback, `RecentAuditsTable` score column + open-lead tooltip, `LanguageSwitcher` aria-label + active indicator, `ResultsPage` WhatsApp share message, `ComparePage` "vs" label + URL placeholders, `SettingsScoring` preview hint). 18 new translation keys added to `en.json` / `es.json` and backend mirrors. Date formatting in the admin dashboard + WhatsApp share now uses `i18n.language` instead of the hardcoded `'es-CO'` locale.
- **v2.2.1** — fix audits stuck in queue + URLs acting "blacklisted". `CRON_SECRET_TOKEN` now auto-generates and persists in settings if missing (kicks via HTTP no longer return 403 silently on fresh installs). New rescue panel in `/admin/queue` with 4 buttons: process queue now, reset stuck running jobs, clear failure cache (all or by URL).
- **v2.2.0** — admin UI to tune the scoring (`/admin/scoring`). Per-module include/exclude toggle, per-module critical-cap slider, exponential penalty curve config, per-metric toggle + weight, and a live preview that recalculates a recent audit with the proposed config before saving.
- **v2.1.0** — honest scoring (no more inflated 85/good for sites with real problems). Four new levers: stricter thresholds (good ≥ 80), per-metric weights (SSL pesa más que X-Powered-By), critical-cap per module (1 crítica = el módulo no puede pintar verde), exponential penalty by total criticals. Old audits recalc on read.
- **v2.0.8** — admin panel no longer blocks during a running scan. Two causes fixed: (1) `audit.php` now releases the PHP session lock immediately after reading auth state (was held for the full 30-45s scan, blocking every other request from the same browser); (2) scans are now strictly queue-only — `audit.php` enqueues + responds in milliseconds, the actual work runs in a separate worker via `drain-queue.php`, kicked off asynchronously via `shell_exec` or short-timeout HTTP self-call.
- **v2.0.7** — fix "Error guardando el resultado" mid-scan on MySQL. The four columns that store gzipped JSON (`audits.result_json`, `audits.waterfall_json`, `wp_snapshots.snapshot_json`, `wp_snapshots.analysis_json`) were declared as `JSON`; MySQL rejected the binary gzip bytes. New migration `0003_json_columns_to_blob` widens them to `LONGBLOB`. Audit failure toast now surfaces the underlying DB error instead of the generic placeholder.

- **v2.0.6** — fix `/admin/vulnerabilities` empty on fresh installs: auto-seed from `data/vulnerabilities.json` when table is empty; baseline plugin list so "Refresh from API" works without prior audits; inline LIMIT/OFFSET as int casts to dodge MySQL native-prepare edge case.
- **v2.0.5** — fix /admin/health throwing "Table sqlite_master doesn't exist" on MySQL installs (diag.php now branches by active driver for table listing + column-existence checks).
- **v2.0.4** — fix Database retry wrapper that misread legitimate `null` results as "loop failed" (was throwing "Query failed without exception" on every dashboard load and on any optional-row lookup).
- **v2.0.3** — persistent install (re-uploads don't re-trigger the wizard if DB+admin are intact); dashboard 500 now shows the real error in the UI.
- **v2.0.2** — more MySQL `key` backticks (Translator + translations CRUD); fix admin URL infinite-loop after login.
- **v2.0.1** — first batch of MySQL `key` backticks + drop ROW_NUMBER() OVER (now 5.7-compatible).
- **v2.0** — initial public build.

Extract it into your hosting and open `https://your-domain/setup` to run the
install wizard.

## What's inside the zip

- `index.html`, `assets/` — compiled React frontend.
- `api/` — PHP endpoints (router + admin + user + setup wizard).
- `lib/` — backend classes (Database wrapper, Migrator, Backup, Languages,
  EnvWriter, …) plus `lib/db/` with the dialects.
- `analyzers/` — security/performance/seo/etc. analyzers.
- `config/`, `data/`, `locales/` — defaults, vulnerability data, translation
  bundles (backend `.php` + frontend `.json`).
- `database/migrations/` — versioned schema (the migrator applies them on
  first boot or from the wizard).
- `cron/` — scripts you can wire to cPanel cron (backup, drain-queue,
  cleanup, plugin-vault refresh, vulnerability updates).
- `cache/`, `logs/`, `uploads/`, `storage/backups/` — empty, ready-to-write
  folders with `.htaccess` guards.
- `widget/` — embeddable JS widget.
- `.env.example`, `.htaccess.backend`, `.htaccess` — install templates.

## Install in 60 seconds

1. **Upload** the unzipped folder to your hosting (cPanel File Manager or
   FTP). Typical target: `public_html/audit/`.
2. **Permissions** — make sure `cache/`, `logs/`, `uploads/`,
   `storage/backups/`, `data/` and the folder where the SQLite/MySQL
   database lives (`imagina_audit_data/`) are writable by PHP (`0755` on
   directories is enough on most cPanel setups).
3. **Visit** `https://your-domain/audit/setup` and follow the 3-step
   wizard:
   - Pick **MySQL/MariaDB** (recommended) or **SQLite** (fallback).
   - Test the DB connection.
   - Create the admin email + password.
   - Click **Install**. Migrations run automatically, admin gets created,
     `data/.installed` flag is written.
4. **Log in** at `/admin/login`.

Detailed deployment notes and the cron-job table live in
`deploy/DEPLOY.md` of the source branch.

## How this branch is updated

This branch is **orphan** (no history shared with `main` /
`claude/*`). Each new release rebuilds `deploy/output/` and replaces the
zip here. Don't rebase or merge feature branches into this one — it's an
artifact-only branch.

Source code, issues and PRs: see the main feature branch
(`claude/analyze-wordpress-audit-app-D9hQR` while in dev; will move to
`main` on stable release).
