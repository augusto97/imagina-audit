# Changelog — Imagina Audit

Cada versión se publica automáticamente como GitHub Release al fusionar a
`main` con un `VERSION` nuevo. Las notas del release salen de la sección
correspondiente de este archivo. El historial anterior a v2.6.0 está en el
README de la rama `release`.

## v2.7.1

- **Protección de carpetas comprobada en vivo** (Configuración → Actualizaciones y Diagnóstico): la app intenta descargar un archivo de prueba de cada carpeta interna (`storage`, `cache`, `logs`, `lib`, `config`, `data`, `database`, `analyzers`) y el `.env` desde su propia URL pública, como lo haría cualquier visitante. Si algo es accesible, dice exactamente qué, detecta el servidor (Nginx, Apache, LiteSpeed) y da el bloque listo para copiar; si todo está protegido, lo confirma. Sustituye al aviso genérico sobre Nginx, que ya no aparece.
- Los avisos de una versión solo se muestran mientras esa versión está pendiente de instalar.
- **Consulta de versiones más robusta:** si la API de GitHub limita las consultas (60/hora por IP, compartida en hosting compartido), el actualizador lee el último release por la URL de descarga directa, sin ese límite. Si aun así no puede comprobarlo, el panel lo dice en lugar de mostrar «Estás al día».

## v2.7.0

**Actualizaciones automáticas desde GitHub Releases.**

- Nuevo panel **Configuración → Actualizaciones** (`/admin/updates`): muestra la versión instalada y la última publicada (la app revisa GitHub como mucho una vez por hora) y la instala con un clic.
- Instalación por pasos con progreso real: requisitos y espacio en disco (si no alcanza, avisa sin descargar) → descarga y verificación **sha256** → descompresión segura → **copia de la base** → **migraciones con el código nuevo, antes del cambio** → reparación del `.env` (añade variables nuevas sin pisar las tuyas) → cambio de versión y **verificación**. Si la verificación falla, **vuelve sola a la versión anterior**.
- Se conservan las 3 versiones anteriores (botón «Volver a vX») y las 5 últimas copias de la base previas a actualizar. La poda la hace la versión nueva al arrancar.
- Opción **«Instalar actualizaciones automáticamente»** (desactivada por defecto): con el cron de la cola activo, instala sola cada versión nueva una vez.
- Nunca toca `.env`, subidas, backups, la base de datos ni la configuración del servidor web; si editaste el `.htaccess` raíz a mano se conserva y la versión nueva deja el suyo como `.htaccess.new`.
- `update-rescue.php` en la raíz: vuelta atrás de emergencia si una versión nueva no arrancara.
- `installer.php` publicado en cada release para instalaciones nuevas: súbelo a la carpeta vacía y ábrelo en el navegador.
- Lector del `.env` más robusto (comillas, escapes, comentarios en línea, `export`, CRLF).
- Las pestañas abiertas durante una actualización se recargan solas una vez si piden archivos que ya no existen.
- `deploy/nginx.conf`: añade `storage` a las carpetas bloqueadas.

**Importante:** esta versión hay que instalarla a mano una última vez (extraer el zip como siempre). Desde aquí, las siguientes se instalan desde el panel.

## v2.6.0

- Laboratorio de rendimiento (`/admin/performance-lab`): mediciones de PageSpeed repetidas (rápida o mediana de 3; móvil, escritorio o ambos) sin generar informes ni leads, con historial, notas, cambios contra la prueba anterior y comparación antes/después.
