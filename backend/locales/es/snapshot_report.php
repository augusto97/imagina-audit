<?php
return [
    // Nombre genérico cuando el API no da el nombre de la vulnerabilidad.
    'vuln.fallback_name' => 'Vulnerabilidad',

    // ——— Environment ——————————————————————————————————————————————
    'env.wp_outdated.title'   => 'WordPress {{version}} — disponible {{latest}}',
    'env.wp_outdated.action'  => 'Actualizar core desde Escritorio → Actualizaciones (backup previo obligatorio).',
    'env.php_outdated.title'  => 'PHP {{version}} quedará sin soporte',
    'env.php_outdated.action' => 'Actualizar a PHP 8.2 o superior. Verificar compatibilidad con todos los plugins activos primero.',
    'env.missing_exts.title'  => 'Extensiones PHP recomendadas ausentes: {{exts}}',
    'env.missing_exts.action' => 'Instalar vía panel de PHP del hosting. Imagick es especialmente importante para WebP/compresión.',
    'env.debug_critical.title'  => 'WP_DEBUG + WP_DEBUG_DISPLAY activos en producción',
    'env.debug_critical.action' => 'Los errores PHP se imprimen a visitantes (leak de paths, versiones). Desactivar WP_DEBUG_DISPLAY en wp-config.php ya.',
    'env.debug_warning.title'   => 'WP_DEBUG activo',
    'env.debug_warning.action'  => 'Aceptable si es solo para log interno. En producción lo ideal es apagar debug completamente.',

    // ——— Themes ———————————————————————————————————————————————————
    'themes.no_child.title'         => 'Tema {{name}} sin child theme',
    'themes.no_child.action'        => 'Cualquier customización directa al tema se perderá al actualizar. Crear un child theme con Template: {{slug}}.',
    'themes.outdated.title'         => 'Tema activo desactualizado: {{name}}',
    'themes.outdated.action'        => 'Actualizar desde Apariencia → Temas.',
    'themes.inactive_excess.title'  => '{{count}} temas inactivos en disco',
    'themes.inactive_excess.action' => 'Eliminar temas sin usar desde Apariencia → Temas (mantener solo el activo y un default como fallback).',

    // ——— Security actions (por check key) —————————————————————————
    'security.action.wp_debug'           => 'En wp-config.php: define("WP_DEBUG", false); — o al menos desactivar WP_DEBUG_DISPLAY.',
    'security.action.wp_debug_display'   => 'define("WP_DEBUG_DISPLAY", false); en wp-config.php para no leakear errores a visitantes.',
    'security.action.file_editing'       => 'define("DISALLOW_FILE_EDIT", true); en wp-config.php.',
    'security.action.file_mods'          => 'define("DISALLOW_FILE_MODS", true); impide instalar/actualizar plugins vía admin — solo recomendable con CI/CD.',
    'security.action.db_prefix'          => 'Cambiar prefijo de wp_ a uno custom vía script de migración (wp-config + renombrar tablas + options serializadas).',
    'security.action.auto_updates_core'  => 'Habilitar auto-updates menores: add_filter("auto_update_core", "__return_true"); o dejar defaults de WP.',
    'security.action.app_passwords'      => 'Si no usas apps externas (Jetpack, mobile app) desactivar con add_filter("wp_is_application_passwords_available", "__return_false");',
    'security.action.wp_config_writable' => 'Permisos de wp-config.php a 440 o 400 (read-only).',
    'security.action.xmlrpc'             => 'Desactivar XML-RPC si no lo usas: add_filter("xmlrpc_enabled", "__return_false"); — reduce superficie de fuerza bruta.',
    'security.action.ssl'                => 'Migrar a HTTPS: actualizar site_url/home_url, instalar SSL, forzar redirect 301 desde HTTP.',

    // ——— Database —————————————————————————————————————————————————
    'db.autoload.title'    => 'Autoload pesado: {{size}} en {{count}} opciones',
    'db.autoload.action'   => 'Ralentiza TODO el sitio (cada request carga estas opciones). Usar WP-Optimize / Autoload Options Monitor para identificar las más pesadas y cambiar autoload=no.',
    'db.revisions.title'   => '{{count}} revisiones acumuladas',
    'db.revisions.action'  => 'define("WP_POST_REVISIONS", 5); + limpiar históricas con WP-Optimize.',
    'db.myisam.title'      => '{{count}} tablas con motor MyISAM',
    'db.myisam.action'     => 'Convertir a InnoDB: ALTER TABLE nombre ENGINE=InnoDB; (una por una, backup antes).',
    'db.orphaned.title'    => '{{count}} registros de postmeta huérfanos',
    'db.orphaned.action'   => 'Limpiar con WP-Optimize o SQL directo sobre wp_postmeta LEFT JOIN wp_posts.',

    // ——— Performance ——————————————————————————————————————————————
    'perf.opcache.title'      => 'OPcache desactivado',
    'perf.opcache.action'     => 'En php.ini: opcache.enable=1, opcache.memory_consumption=256. Ganancia típica 30-60% en rendimiento PHP.',
    'perf.object_cache.title' => 'Sin object cache persistente',
    'perf.object_cache.action'=> 'Instalar Redis o Memcached + plugin Redis Object Cache. Reduce queries repetidas a DB.',
    'perf.page_cache.title'   => 'No se detecta page cache',
    'perf.page_cache.action'  => 'Instalar WP Rocket / LiteSpeed Cache / W3 Total Cache, o habilitar cache a nivel de servidor (Nginx FastCGI, Varnish).',
    'perf.image_editor.title' => 'WP usa {{editor}} (sin Imagick)',
    'perf.image_editor.action'=> 'Instalar extensión PHP Imagick para mejor calidad, WebP y AVIF.',

    // ——— Cron —————————————————————————————————————————————————————
    'cron.overdue.title'               => '{{count}} cron jobs atrasados',
    'cron.overdue.action_disabled'     => 'WP_CRON está deshabilitado. Verificar que el cron del sistema esté llamando a wp-cron.php cada minuto.',
    'cron.overdue.action_low_traffic'  => 'Sitio con poco tráfico no dispara WP_CRON. Configurar cron del servidor: */5 * * * * wget -qO- https://tu-sitio.com/wp-cron.php',
    'cron.hook_abuse.title'            => 'Hook {{hook}} registrado {{count}} veces',
    'cron.hook_abuse.action'           => 'Posible leak de wp_schedule_event sin unscheduling. Revisar el plugin responsable.',

    // ——— Media ————————————————————————————————————————————————————
    'media.no_webp.title'   => 'Imágenes sin formato WebP',
    'media.no_webp.action'  => 'Instalar ShortPixel / Imagify / EWWW para convertir JPEG/PNG a WebP automáticamente — reduce peso 25-35% típico.',
    'media.heavy.title'     => 'Biblioteca pesada ({{size}})',
    'media.heavy.action'    => 'Activar lazy loading nativo (ya en WP 5.5+), servir imágenes via CDN, comprimir con calidad 75-85%.',

    // ——— Users ————————————————————————————————————————————————————
    'users.too_many_admins.title'  => '{{count}} usuarios con rol administrator',
    'users.too_many_admins.action' => 'Aplicar principio de mínimo privilegio: bajar a Editor a los que no necesiten tocar plugins/temas. Exigir 2FA en los que queden.',
    'users.no_admins.title'        => 'Ningún administrator visible',
    'users.no_admins.action'       => 'Puede ser un sitio con rol personalizado. Verificar quién tiene "manage_options" realmente.',
    'users.many_users.title'       => '{{count}} usuarios registrados',
    'users.many_users.action'      => 'Revisar si la tabla wp_users está creciendo por spam signups. Considerar un antispam en registro (Cloudflare Turnstile, reCAPTCHA).',

    // ——— Content ——————————————————————————————————————————————————
    'content.rest_bloat.title'         => '{{count}} rutas REST expuestas',
    'content.rest_bloat.action'        => 'Volumen alto indica plugin bloat. Auditar los top namespaces — algunos quizá se puedan desactivar en el frontend con rest_api_init.',
    'content.cpt_rest_exposed.title'   => 'CPT público "{{slug}}" expuesto en REST API',
    'content.cpt_rest_exposed.action'  => 'Si no debería ser accesible sin auth, restringir con rest_authentication_errors o show_in_rest=false.',
];
