# Imagina Audit — Release branch

This branch holds the **ready-to-install build artifact**. Nothing else.

## Download

The latest packaged build sits at the root of this branch:

- **`imagina-audit-v2.0.8.zip`** (~1.2 MB) — current

### Changelog

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
