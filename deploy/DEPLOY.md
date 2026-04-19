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
├── lib/                  (clases PHP, protegidas por .htaccess)
├── analyzers/            (protegidos por .htaccess)
├── config/               (protegidos por .htaccess)
├── data/                 (protegidos por .htaccess)
├── database/             (protegidos por .htaccess)
├── cache/                (escribible por PHP)
└── logs/                 (escribible por PHP)
```

La base de datos SQLite se crea automáticamente en `~/imagina_audit_data/audit.db`
(fuera de `public_html`) si hay permisos de escritura. Si no, cae en `audit/database/audit.db`
protegida por `.htaccess`.

## 3. Variables de entorno

Crea `public_html/audit/.env` (copia de `.env.example`) y configura:

| Variable | Obligatorio | Descripción |
|---|---|---|
| `APP_ENV` | sí | `production` |
| `APP_DEBUG` | sí | `false` en producción |
| `ALLOWED_ORIGIN` | **sí** | Dominio(s) frontend separados por coma. **No uses `*` en producción.** |
| `ADMIN_PASSWORD_HASH` | sí (primer arranque) | Genera con `php -r "echo password_hash('tu-pass', PASSWORD_BCRYPT);"` |
| `GOOGLE_PAGESPEED_API_KEY` | no | Mejora cuota de PageSpeed |
| `GOOGLE_SAFE_BROWSING_API_KEY` | no | Activa check de Safe Browsing |
| `RATE_LIMIT_MAX_PER_HOUR` | no | Default `10` |
| `CACHE_TTL_SECONDS` | no | Default `86400` (24h) |
| `LEAD_NOTIFICATION_EMAIL` | no | Email para notificar nuevos leads |

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

Configura estos crons en cPanel:

```
# Cleanup de rate_limits y logs antiguos (diario, 3 AM)
0 3 * * * php /home/USER/public_html/audit/cron/cleanup.php

# VACUUM de SQLite (semanal, domingo 4 AM)
0 4 * * 0 php /home/USER/public_html/audit/cron/vacuum.php

# Update de base de vulnerabilidades (diario, 5 AM)
0 5 * * * php /home/USER/public_html/audit/cron/update-vulnerabilities.php
```

(Los scripts de `cron/` se crean como parte de la tarea 6.11 del plan de reestructuración.)

## 9. Verificación post-deploy

- [ ] `GET /audit/api/health` retorna `{"success":true}`
- [ ] `/audit/` carga la home y permite auditar un sitio de prueba
- [ ] `/audit/admin` pide contraseña y tras login muestra el dashboard
- [ ] Cabeceras de seguridad presentes (ver con `curl -I`):
  - `Strict-Transport-Security`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy`
  - `Content-Security-Policy`
- [ ] `.env` y `audit.db` no son accesibles por URL directa
