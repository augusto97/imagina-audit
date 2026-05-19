# Deploy — Imagina Audit

Guía de despliegue en hosting compartido con cPanel.

## 1. Build local

```bash
cd frontend
npm install
npm run build          # genera frontend/dist/
cd ..
bash deploy/build.sh   # compila artefactos en deploy/output/
```

## 2. Subida al hosting

Sube el contenido de `deploy/output/` a `public_html/audit/` (o al subdominio que uses).

Estructura resultante en el hosting:

```
public_html/audit/
├── index.html            (React build)
├── assets/               (JS/CSS compilados)
├── api/                  (endpoints PHP)
├── lib/                  (clases PHP + lib/db/ con Dialects)
├── analyzers/            (protegidos por .htaccess)
├── config/               (protegidos por .htaccess)
├── data/                 (incluye `.installed` tras el wizard)
├── database/migrations/  (NNNN_*.sql versionadas)
├── locales/              (bundles backend + frontend.json)
├── storage/backups/      (escribible — backups DB)
├── cache/                (escribible por PHP)
└── logs/                 (escribible por PHP)
```

## 3. Primera instalación — wizard

Abre `https://tu-dominio/audit/setup` en el navegador. La app detecta que aún
no está instalada y dispara un wizard de 3 pasos:

1. **Base de datos** — elige driver:
   - **MySQL/MariaDB** (recomendado para producción, mínimo 5.7+ / 10.3+).
     Crea la base y el usuario antes desde cPanel → MySQL Databases.
     Mete host, puerto, nombre, usuario, password → click **Test connection**.
   - **SQLite** (fallback) — la app crea `~/imagina_audit_data/audit.db` fuera
     de `public_html` automáticamente. Sirve para dev, demos, hostings sin MySQL.
2. **Admin** — email + password iniciales (mínimo 10 caracteres).
3. **Review + install** — confirma. La app escribe `.env`, aplica las
   migraciones versionadas (crea todas las tablas), guarda la cuenta admin,
   marca como instalado y redirige a `/admin/login`.

El wizard queda bloqueado tras la primera ejecución exitosa (existe
`data/.installed`).

## 4. Migración SQLite → MySQL (installs ya existentes)

Si ya tienes una instalación corriendo en SQLite y quieres pasar a MySQL:

1. Crea la base + usuario MySQL en cPanel.
2. Entra al panel admin → **Base de datos**.
3. Mete las credenciales MySQL, click **Test connection**.
4. Click **Run migration** — la app aplica el schema en MySQL y copia todas
   las filas en orden topológico (FKs respetadas).
5. Click **Switch driver to MySQL** — escribe `DB_DRIVER=mysql` al `.env` y
   recarga automáticamente. La próxima request boot-a en MySQL.
6. El archivo SQLite queda intacto como backup hasta que lo borres.

Alternativa CLI: `php database/migrate-from-sqlite.php --mysql-host=... --mysql-db=... ...`.

## 5. Variables de entorno (.env)

`.env` se escribe automáticamente por el wizard. Si prefieres editarlo a mano,
copia `.env.example` a `.env` y configura:

### Bloque DB (nuevo en P7)

| Variable | Obligatorio | Descripción |
|---|---|---|
| `DB_DRIVER` | sí | `mysql` (producción) o `sqlite` (fallback) |
| `DB_HOST` | si DB_DRIVER=mysql | Host MySQL (típico `localhost`) |
| `DB_PORT` | si DB_DRIVER=mysql | Default 3306 |
| `DB_NAME` | si DB_DRIVER=mysql | Nombre de la base |
| `DB_USER` | si DB_DRIVER=mysql | Usuario con CREATE/INSERT/UPDATE/DELETE/SELECT |
| `DB_PASSWORD` | si DB_DRIVER=mysql | Password del usuario |
| `DB_CHARSET` | no | Default `utf8mb4` |
| `DB_SQLITE_PATH` | no | Solo si DB_DRIVER=sqlite. Vacío = ruta auto |
| `SLOW_QUERY_THRESHOLD_MS` | no | Default 200. Queries más lentas se loguean en `logs/`. 0 desactiva |
| `BACKUP_RETENTION_COUNT` | no | Default 10. Backups conservados en `storage/backups/` |

### Bloque general

| Variable | Obligatorio | Descripción |
|---|---|---|
| `APP_ENV` | sí | `production` |
| `APP_DEBUG` | sí | `false` en producción |
| `ALLOWED_ORIGIN` | **sí** | Dominio(s) del panel admin separados por coma. Solo aplica a `/api/admin/*`. Los endpoints públicos están abiertos con `*` para que el widget embebible funcione desde cualquier dominio. |
| `ADMIN_PASSWORD_HASH` | (opcional) | Bypass el wizard. Genera con `php -r "echo password_hash('tu-pass', PASSWORD_BCRYPT);"` |
| `GOOGLE_PAGESPEED_API_KEY` | no | Mejora cuota de PageSpeed |
| `GOOGLE_SAFE_BROWSING_API_KEY` | no | Activa check de Safe Browsing |
| `RATE_LIMIT_MAX_PER_HOUR` | no | Default `10` |
| `CACHE_TTL_SECONDS` | no | Default `86400` (24h) |
| `LEAD_NOTIFICATION_EMAIL` | no | Email para notificar nuevos leads |

### Cron jobs recomendados (cPanel → Cron Jobs)

```cron
# Backup diario de la DB (mantiene los últimos BACKUP_RETENTION_COUNT)
0 3 * * * php /home/USER/public_html/audit/cron/backup.php

# Drain de la cola de audits cada 2 minutos
*/2 * * * * php /home/USER/public_html/audit/cron/drain-queue.php

# Limpieza de logs/cache viejos diaria
0 4 * * * php /home/USER/public_html/audit/cron/cleanup.php

# Refresh del Plugin Vault semanal
0 5 * * 0 php /home/USER/public_html/audit/cron/refresh-plugin-vault.php

# Actualizar base de vulnerabilidades mensual
0 6 1 * * php /home/USER/public_html/audit/cron/update-vulnerabilities.php
```

## 4. Permisos de archivos

Desde el File Manager de cPanel o vía SSH:

```bash
# Archivos sensibles: solo lectura para el propietario
chmod 600 .env
chmod 600 database/audit.db            # si la DB quedó dentro de public_html

# Directorios con escritura del proceso PHP
chmod 755 cache logs database
chmod 700 ~/imagina_audit_data         # si la DB está fuera de public_html
chmod 600 ~/imagina_audit_data/audit.db

# Código: solo lectura (PHP lee, no escribe)
find lib analyzers config -type f -exec chmod 644 {} \;
find lib analyzers config -type d -exec chmod 755 {} \;
```

En cPanel con PHP-FPM, el propietario suele ser tu usuario cPanel (no `nobody`).
Verifica con `ls -la` que los archivos pertenecen a ti y no a `www-data`.

## 5. Protecciones `.htaccess`

Las carpetas sensibles ya traen `.htaccess` con `Deny from all`. Verifica tras el deploy
que ninguna de estas URLs es accesible públicamente:

- `https://tusitio.com/audit/.env` → debe dar 403
- `https://tusitio.com/audit/database/audit.db` → debe dar 403
- `https://tusitio.com/audit/lib/Auth.php` → debe dar 403
- `https://tusitio.com/audit/logs/` → debe dar 403

## 6. HTTPS y cookies de sesión

La app configura cookies con `Secure; HttpOnly; SameSite=Strict` automáticamente
**solo si detecta HTTPS**. Asegúrate de:

1. Tener certificado SSL activo (Let's Encrypt desde cPanel).
2. Forzar redirect HTTP→HTTPS con `.htaccess` en la raíz del dominio.
3. Si usas Cloudflare/proxy: verificar que llega `X-Forwarded-Proto: https` al PHP.

Sin HTTPS las cookies NO llevarán el flag `Secure` y el admin quedará expuesto a
session hijacking en redes públicas.

## 7. Primer login admin

1. Visita `/audit/admin` y usa la contraseña cuyo hash pusiste en `ADMIN_PASSWORD_HASH`.
2. Cambia la contraseña desde `Settings → General` (se guarda hasheada en la DB y ya
   no hace falta tenerla en `.env`).

## 8. Mantenimiento recomendado

Configura estos crons en cPanel → Cron Jobs (todos usan el mismo
`CRON_SECRET_TOKEN` si se invocan por HTTP; por CLI no hace falta):

```
# Drenar cola de audits + reapear jobs huérfanos (cada 5 min)
*/5 * * * * php /home/USER/public_html/audit/cron/drain-queue.php

# Cleanup diario: rate_limits, login_attempts, audit_jobs viejos,
# cache expirado, log rotation, retención de informes si está habilitada
0 3 * * * php /home/USER/public_html/audit/cron/cleanup.php

# Compactación semanal de SQLite (domingo 4 AM)
0 4 * * 0 php /home/USER/public_html/audit/cron/vacuum.php

# Actualización de base de vulnerabilidades (diario 5 AM)
0 5 * * * php /home/USER/public_html/audit/cron/update-vulnerabilities.php
```

El `drain-queue` es el más crítico — asegura que la cola fluya aunque
un proceso PHP muera a mitad de audit (dead-man switch). Si nunca
tienes picos de concurrencia, actúa como no-op y sale rápido.

## 9. Verificación post-deploy

- [ ] `GET /audit/api/health` retorna `{"success":true}`
- [ ] `/audit/` carga la home y permite auditar un sitio de prueba
- [ ] `/audit/admin` pide contraseña y tras login muestra el dashboard
- [ ] En `/audit/admin/queue` se ve la RAM detectada y la recomendación
      coincide con tu hosting (en 1.5 GB debería marcar 3 slots)
- [ ] En `/audit/admin/retention` puedes activar/desactivar el toggle
      y el preview calcula en vivo
- [ ] Cabeceras de seguridad presentes (ver con `curl -I`):
  - `Strict-Transport-Security`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy`
  - `Content-Security-Policy`
- [ ] `.env` y `audit.db` no son accesibles por URL directa
- [ ] Los 4 crons aparecen en cPanel → Cron Jobs y ejecutan sin errores
      (revisa `/audit/logs/` tras 5-10 min)

## 10. Primera auditoría de prueba

Tras configurar todo:

1. Desde `/audit/` lanza un audit a `https://imaginawp.com` (o cualquier
   sitio conocido). Debe:
   - Responder en <1s con la pantalla de "Escaneando..."
   - Mostrar progreso real paso a paso (ahora viene del backend, no es
     fake con timers)
   - Completar en ~40s y redirigir a resultados
2. Abre `/audit/admin/queue` en otra pestaña y verifica que mientras
   corre ves `running: 1 / 3` y el job aparece en la lista de activos.
3. Lanza 4 audits seguidos desde distintas URLs — la 4ª debe aparecer
   con pantalla "Posición #1 en cola" y procesarse cuando alguna de
   las primeras 3 termine.
