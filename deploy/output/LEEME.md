# Deploy — Imagina Audit

## IMPORTANTE: Archivos ocultos

GitHub no muestra archivos que empiezan con punto (`.env`, `.htaccess`).
Por eso cada archivo oculto tiene una copia visible con extensión `.txt`:

| Archivo real (oculto) | Copia visible |
|---|---|
| `.env` | `env.txt` |
| `.htaccess` | `htaccess.txt` |
| `cache/.htaccess` | `cache/htaccess.txt` |
| `logs/.htaccess` | `logs/htaccess.txt` |
| `lib/.htaccess` | `lib/htaccess.txt` |
| (etc. en cada subcarpeta) | |

### Al subir al hosting:

1. Sube TODO el contenido de `deploy/output/` a tu hosting
2. **Renombra** cada `htaccess.txt` → `.htaccess` y `env.txt` → `.env`
3. **Elimina** los archivos `.txt` (son solo copias)

O si usas el File Manager de cPanel con "Mostrar archivos ocultos" activado,
los `.htaccess` y `.env` originales ya están ahí — solo elimina los `.txt`.

## Pasos de instalación

1. Sube el contenido de `output/` a la raíz de tu subdominio (ej: `audit.tudominio.com`)
2. Edita `.env`:
   - `ALLOWED_ORIGIN=https://audit.tudominio.com`
   - `COMPANY_WHATSAPP=+57TUNUMERO`
3. Asegúrate de que `cache/` y `logs/` tengan permisos 755
4. Visita `https://audit.tudominio.com/api/database/seed.php` UNA vez
5. Elimina `database/seed.php` después de ejecutarlo
6. Visita `https://audit.tudominio.com/api/health` — debe decir `"healthy"`
7. Visita `https://audit.tudominio.com/` — deberías ver la app

### Si usas subcarpeta en vez de subdominio

Si montas en `tudominio.com/audit/` en vez de un subdominio:
- Edita el `.htaccess` de la raíz y cambia `RewriteBase /` por `RewriteBase /audit/`
- Recompila el frontend con `VITE_API_URL=/audit/api` en `frontend/.env`

### Contraseña admin por defecto

- Password: `imagina2024`
- Acceso: `/admin` (Fase 2, aún no implementado en el frontend)
- Cambiar desde la base de datos después del primer uso
