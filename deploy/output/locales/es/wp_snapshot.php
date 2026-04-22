<?php
/**
 * Strings del WpSnapshotAnalyzer (español). Se extiende por sub-checker.
 */
return [
    // Módulo
    'summary.prefix'     => 'Análisis interno del sitio: {{score}}/100',
    'summary.outdated'   => ' · {{outdated}} de {{total}} plugins con actualización pendiente',
    'summary.site'       => '. Sitio: {{name}}',

    // users_roles
    'users.name'             => 'Usuarios y roles',
    'users.display_one_admin'  => '{{total}} usuarios · {{admins}} admin',
    'users.display_many_admin' => '{{total}} usuarios · {{admins}} admins',
    'users.desc.ok'          => '{{total}} usuarios registrados con {{admins}} administrador. Mínimo privilegio aplicado correctamente.',
    'users.desc.too_many'    => '{{total}} usuarios con {{admins}} administradores. Cada admin adicional aumenta la superficie de ataque — basta con que uno tenga password débil o sea víctima de phishing.',
    'users.recommend.many'   => 'Revisar la lista de admins en Usuarios → Todos los usuarios (filtro rol: Administrador). Bajar a Editor quienes no necesiten cambiar plugins/temas.',
    'users.recommend.two'    => 'Revisar si los 2 admins son realmente necesarios.',
    'users.solution'         => 'Aplicamos principio de mínimo privilegio y activamos 2FA en todas las cuentas admin.',

    // app_passwords
    'apppw.name'              => 'Application Passwords (REST API)',
    'apppw.display.enabled'   => 'Habilitadas',
    'apppw.display.disabled'  => 'Deshabilitadas',
    'apppw.desc.enabled'      => 'Application Passwords están habilitadas. Permiten autenticar requests REST desde apps externas (mobile WP app, Zapier, etc.). Seguro si no se usan, pero si no las necesitas puedes desactivarlas para reducir superficie.',
    'apppw.desc.disabled'     => 'Application Passwords deshabilitadas. Reduce superficie de ataque sobre REST API.',
    'apppw.solution'          => 'Configuramos correctamente los métodos de autenticación según el uso real del sitio.',

    // plugins_outdated
    'pluginsout.name'           => 'Plugins desactualizados',
    'pluginsout.display.ok'     => 'Todos los {{total}} plugins al día',
    'pluginsout.display.bad'    => '{{outdated}} de {{total}} con actualización pendiente',
    'pluginsout.desc.ok'        => 'Todos los {{total}} plugins están en su última versión.',
    'pluginsout.desc.bad'       => '{{outdated}} plugins tienen updates disponibles ({{active}} activos). Los plugins desactualizados son la principal causa de sitios WordPress hackeados.',
    'pluginsout.recommend'      => 'Actualizar desde WP Admin → Plugins. Hacer backup antes de actualizar plugins críticos (WooCommerce, Elementor, etc.).',
    'pluginsout.solution'       => 'Actualizamos todos los plugins semanalmente con testing previo de compatibilidad.',

    // plugins_inactive
    'pluginsinact.name'         => 'Plugins inactivos',
    'pluginsinact.display.ok'   => 'Ninguno',
    'pluginsinact.display.bad'  => '{{count}} plugins',
    'pluginsinact.desc.ok'      => 'No hay plugins inactivos. Correcto.',
    'pluginsinact.desc.bad'     => '{{count}} plugins inactivos instalados. Aunque estén desactivados, sus archivos siguen en el servidor y pueden ser explotados si contienen vulnerabilidades.',
    'pluginsinact.recommend'    => 'Eliminar los plugins que no se usan desde Plugins → Desactivados → Eliminar. Conservar solo los activos.',
    'pluginsinact.solution'     => 'Limpiamos plugins inactivos reduciendo superficie de ataque y tamaño del sitio.',

    // plugin_overload
    'overload.name'             => 'Cantidad de plugins activos',
    'overload.display'          => '{{count}} plugins activos',
    'overload.desc'             => '{{count}} plugins activos. Cada plugin añade consultas a DB, código PHP y potencial de conflictos. La regla práctica: <20 plugins en la mayoría de sitios.',
    'overload.recommend.heavy'  => 'Auditar qué plugins son realmente necesarios. Combinar funcionalidades (muchos builders incluyen lo de varios plugins). Eliminar redundantes.',
    'overload.recommend.normal' => 'Revisar periódicamente si algún plugin se puede reemplazar por código en el tema o combinar con otros.',
    'overload.solution'         => 'Auditamos el stack de plugins y recomendamos consolidación.',

    // plugins_auto_update
    'pluginsauto.name'          => 'Auto-update de plugins activos',
    'pluginsauto.display'       => '{{withAuto}}/{{total}} con auto-update ({{pct}}%)',
    'pluginsauto.desc.prefix'   => '{{withAuto}} de {{total}} plugins activos tienen actualización automática habilitada. ',
    'pluginsauto.desc.good'     => 'Buena práctica.',
    'pluginsauto.desc.bad'      => 'Los que no tienen auto-update solo se actualizan manualmente.',
    'pluginsauto.recommend'     => 'En Plugins → habilitar "Actualizaciones automáticas" para los plugins en los que confíes (Yoast, Elementor, WooCommerce, etc.).',
    'pluginsauto.solution'      => 'Configuramos auto-updates selectivas con rollback automático si algo falla.',

    // mu_plugins_dropins
    'mudrop.name'              => 'MU-plugins y drop-ins',
    'mudrop.display'           => '{{mu}} MU + {{drop}} drop-ins',
    'mudrop.desc'              => '{{total}} componentes instalados silenciosamente (MU-plugins y drop-ins). Estos se cargan automáticamente y pueden ser inyectados por hosting/backup plugins/ManageWP/etc. Merece la pena revisarlos uno a uno.',
    'mudrop.recommend'         => 'Revisar wp-content/mu-plugins/ y wp-content/*.php (drop-ins como object-cache.php, advanced-cache.php, db.php). Asegurarse de que cada uno es legítimo.',
    'mudrop.solution'          => 'Auditamos MU-plugins y drop-ins para detectar malware y backdoors.',

    // theme_active
    'theme.name'               => 'Tema activo',
    'theme.display'            => '{{name}} {{version}}{{childSuffix}}',
    'theme.display.child'      => ' (child)',
    'theme.unknown'            => 'Desconocido',
    'theme.desc.child'         => 'Usas child theme de {{parent}}. Puedes personalizar sin perder cambios al actualizar el parent.',
    'theme.desc.no_child'      => 'Usas tema {{name}} directamente. Cualquier modificación al código se perderá al actualizar. {{updateNote}}',
    'theme.desc.update_note'   => 'Además hay actualización disponible.',
    'theme.recommend.no_child' => 'Crear un child theme para personalizaciones sin riesgo de perderlas. En wp-content/themes crear carpeta con style.css (Template: {{slug}}) y functions.php.',
    'theme.recommend.update'   => 'Actualizar el tema a la última versión.',
    'theme.solution'           => 'Creamos child themes para personalizaciones seguras y actualizamos el tema semanalmente.',

    // themes_inactive
    'themesinact.name'         => 'Temas inactivos',
    'themesinact.display'      => '{{count}} temas sin usar de {{total}}',
    'themesinact.desc'         => '{{count}} temas inactivos en disco. Aunque no se usen, su código sigue en el servidor y puede contener vulnerabilidades. Mantener solo el activo (+ su padre si es child + uno default como fallback).',
    'themesinact.recommend'    => 'Eliminar temas inactivos desde Apariencia → Temas → Detalles → Eliminar.',
    'themesinact.solution'     => 'Limpiamos temas innecesarios reduciendo superficie de ataque.',

    // db_size
    'dbsize.name'           => 'Tamaño de la base de datos',
    'dbsize.display'        => '{{size}} · {{rows}} filas · {{tables}} tablas',
    'dbsize.desc.ok'        => 'Base de datos de {{size}} ({{rows}} filas, {{tables}} tablas). Tamaño saludable.',
    'dbsize.desc.heavy'     => 'Base de datos de {{size}} — {{label}}. En las tablas top se ve dónde está el peso (ver detalles).',
    'dbsize.label.critical' => 'CRÍTICO: DB muy pesada',
    'dbsize.label.large'    => 'grande',
    'dbsize.recommend'      => 'Revisar las tablas top: plugins de seguridad (Wordfence = wfHits, wfLogins), orders (WooCommerce), logs. Muchas veces un plugin acumula logs sin rotación.',
    'dbsize.solution'       => 'Optimizamos la DB: purgamos logs de plugins, ajustamos retención, y añadimos índices donde hace falta.',

    // db_autoload
    'dbautoload.name'      => 'Opciones autoload',
    'dbautoload.display'   => '{{size}} · {{count}} opciones',
    'dbautoload.desc.ok'   => 'Autoload de {{size}} con {{count}} opciones. Saludable (<512 KB es lo deseable).',
    'dbautoload.desc.bad'  => 'Autoload pesado ({{size}}, {{count}} opciones). Cada request a WP carga TODAS estas opciones en memoria — un autoload de varios MB ralentiza absolutamente todo el sitio.',
    'dbautoload.recommend' => 'Instalar plugin "WP-Optimize" o "Autoload Options Monitor" para identificar qué opciones pesan más. Muchas veces plugins desactivados dejan basura con autoload=yes.',
    'dbautoload.solution'  => 'Limpiamos opciones autoload pesadas y configuramos buenas prácticas.',

    // db_engine
    'dbengine.name'      => 'Motor de base de datos',
    'dbengine.display'   => '{{count}} tablas con MyISAM',
    'dbengine.desc'      => '{{count}} tablas usan MyISAM. Sin transacciones, sin row-level locking, sin foreign keys. InnoDB es superior en rendimiento y concurrencia.',
    'dbengine.recommend' => 'Convertir a InnoDB: ALTER TABLE nombre_tabla ENGINE=InnoDB; (una por una, empezando por las más pequeñas). Hacer backup antes.',
    'dbengine.solution'  => 'Migramos tablas MyISAM a InnoDB para concurrencia y rendimiento.',

    // db_revisions
    'dbrev.name'      => 'Revisiones de posts',
    'dbrev.display'   => '{{count}} revisiones',
    'dbrev.desc.ok'   => '{{count}} revisiones acumuladas. Cantidad normal.',
    'dbrev.desc.bad'  => '{{count}} revisiones ocupando espacio en wp_posts. Cada edición genera una revisión nueva sin límite por defecto.',
    'dbrev.recommend' => 'Limitar revisiones: en wp-config.php, define("WP_POST_REVISIONS", 5). Limpiar las antiguas con WP-Optimize o plugin similar.',
    'dbrev.solution'  => 'Limpiamos revisiones antiguas y limitamos las futuras.',

    // db_transients
    'dbtrans.name'      => 'Transients en options',
    'dbtrans.display'   => '{{count}} transients',
    'dbtrans.desc.ok'   => '{{count}} transients. Normal.',
    'dbtrans.desc.bad'  => '{{count}} transients. Muchos plugins dejan transients expirados que se acumulan — WP no los limpia solo si no usan TTL correcto.',
    'dbtrans.recommend' => 'Limpiar con WP-Optimize. Configurar cache de objetos (Redis) para que los transients vayan a memoria en vez de a wp_options.',
    'dbtrans.solution'  => 'Configuramos Redis object cache para que transients no toquen la DB.',

    // db_orphaned_meta
    'dbmeta.name'      => 'Metadata huérfana',
    'dbmeta.display'   => '{{count}} registros huérfanos',
    'dbmeta.desc'      => '{{count}} registros en wp_postmeta apuntan a posts que ya no existen. Son datos basura acumulados por plugins que no limpian al borrar posts.',
    'dbmeta.recommend' => 'Limpiar con WP-Optimize o SQL: DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;',
    'dbmeta.solution'  => 'Limpiamos metadata huérfana y otros residuos de la DB.',

    // cron_status
    'cron.name'                => 'Tareas programadas (WP Cron)',
    'cron.display.ok'          => '{{total}} tareas OK',
    'cron.display.overdue'     => '{{overdue}} atrasadas de {{total}}',
    'cron.desc.ok'             => '{{total}} cron jobs registrados, ejecutando a tiempo.',
    'cron.desc.ok_no_wpcron'   => ' WP_CRON está deshabilitado (debería haber cron real del servidor configurado).',
    'cron.desc.overdue'        => '{{overdue}} de {{total}} cron jobs atrasados. Tareas automáticas (actualizaciones, emails, backups) no se están ejecutando.',
    'cron.recommend.no_wpcron' => 'Verificar que el cron del servidor esté llamando a wp-cron.php cada minuto.',
    'cron.recommend.low_traf'  => 'En sitios con tráfico bajo, WP_CRON no se dispara. Configurar cron real del sistema: */5 * * * * wget -qO- https://tu-sitio.com/wp-cron.php',
    'cron.solution'            => 'Configuramos cron real del servidor para que las tareas se ejecuten a tiempo.',

    // media_library
    'media.name'             => 'Biblioteca de medios',
    'media.display'          => '{{count}} archivos · {{size}}',
    'media.desc.ok'          => '{{count}} archivos ({{size}}) en la biblioteca. Tamaño razonable.',
    'media.desc.bad_prefix'  => '{{count}} archivos ocupando {{size}}. ',
    'media.desc.bad_heavy'   => 'La biblioteca es pesada — probablemente hay imágenes sin comprimir ni convertir a WebP.',
    'media.desc.bad_normal'  => 'Optimizable con compresión y WebP.',
    'media.recommend'        => 'Instalar ShortPixel o Imagify para comprimir y convertir a WebP automáticamente. Configurar lazy loading (WP ya lo hace desde 5.5).',
    'media.solution'         => 'Comprimimos imágenes, las convertimos a WebP y servimos via CDN.',

    // custom_post_types
    'cpt.name'         => 'Tipos de contenido',
    'cpt.display'      => '{{custom}} custom · {{total}} total',
    'cpt.desc.none'    => 'Solo se usan los tipos nativos de WP (posts, pages). Estructura simple.',
    'cpt.desc.custom'  => '{{custom}} tipos de contenido personalizados (CPTs) registrados por plugins/tema. Pueden afectar rendimiento si se abusa del REST (show_in_rest=true expone todo el contenido).',
    'cpt.solution'     => 'Auditamos los CPTs y optimizamos queries/índices para los que manejan mucho contenido.',

    // rest_api_routes
    'restroutes.name'      => 'Rutas REST API',
    'restroutes.display'   => '{{total}} rutas en {{namespaces}} namespaces',
    'restroutes.desc.ok'   => '{{total}} rutas REST. Volumen normal para un sitio WordPress.',
    'restroutes.desc.bad'  => '{{total}} rutas REST expuestas. Cada plugin añade endpoints; demasiados indican plugin bloat y potencialmente datos expuestos.',
    'restroutes.recommend' => 'Auditar qué plugins exponen tantas rutas. Considerar si alguno puede desactivarse o si el REST debe restringirse a usuarios autenticados.',
    'restroutes.solution'  => 'Restringimos y auditamos endpoints REST para reducir superficie de ataque.',

    // env_php
    'php.name'              => 'Configuración PHP',
    'php.display.ok'        => 'PHP {{version}} · memory {{memory}} · exec {{exec}}s',
    'php.display.none'      => 'No detectado',
    'php.desc.ok'           => 'PHP {{version}} con memory_limit {{memory}} y {{exec}}s de ejecución. Configuración correcta.',
    'php.desc.bad'          => 'Problemas detectados: {{issues}}.',
    'php.issue.eol'         => 'PHP {{version}} es obsoleto (EOL)',
    'php.issue.outdated'    => 'PHP {{version}} — actualizar a 8.2+ recomendado',
    'php.issue.low_memory'  => 'memory_limit bajo ({{value}}) — WP recomienda 256M+',
    'php.issue.low_wpmem'   => 'WP_MEMORY_LIMIT bajo ({{value}}) — puede quedarse corto en plugins pesados',
    'php.issue.low_exec'    => 'max_execution_time de {{value}}s puede fallar en imports/backups',
    'php.issue.missing_ext' => 'Extensiones faltantes: {{list}}',
    'php.recommend'         => 'Actualizar PHP a 8.2+, subir memory_limit a 256M, max_execution_time a 120s, e instalar extensiones faltantes.',
    'php.solution'          => 'Optimizamos PHP/servidor para máximo rendimiento y compatibilidad.',

    // env_database
    'envdb.name'            => 'Base de datos (motor)',
    'envdb.display'         => '{{type}} {{version}}',
    'envdb.desc.ok'         => '{{type}} {{version}} está actualizado.',
    'envdb.desc.bad'        => '{{type}} {{version}}. {{recommend}}',
    'envdb.recommend.maria_eol'   => 'MariaDB < 10.3 está en EOL. Actualizar a 10.6+',
    'envdb.recommend.maria_old'   => 'Actualizar MariaDB a 10.6+ para mejor rendimiento',
    'envdb.recommend.mysql_eol'   => 'MySQL < 5.7 es inseguro. Actualizar a 8.0+',
    'envdb.recommend.mysql_old'   => 'Actualizar MySQL a 8.0+',
    'envdb.solution'        => 'Gestionamos la actualización de motor de base de datos sin pérdida de datos.',

    // wp_version_internal
    'wpver.name'            => 'Versión de WordPress',
    'wpver.display.current' => '{{version}} (última)',
    'wpver.display.old'     => '{{version}} → {{latest}} disponible',
    'wpver.desc.current'    => 'WordPress {{version}} está al día.',
    'wpver.desc.old'        => 'WordPress {{version}}. La versión más reciente conocida es {{latest}}.',
    'wpver.recommend'       => 'Actualizar WordPress a {{latest}} desde Escritorio → Actualizaciones. Hacer backup primero.',
    'wpver.solution'        => 'Mantenemos WordPress core siempre actualizado, con backup y testing previo.',

    // env_upload
    'upload.name'           => 'Límites de subida',
    'upload.display'        => 'upload {{upload}} · post {{post}}',
    'upload.desc.bad'       => 'Límites pequeños (upload={{upload}}, post={{post}}). Puede bloquear subidas de medios o imports.',
    'upload.desc.ok'        => 'upload_max_filesize={{upload}}, post_max_size={{post}}. Adecuado.',
    'upload.recommend'      => 'Subir upload_max_filesize y post_max_size a 64M en php.ini o .htaccess.',
    'upload.solution'       => 'Configuramos los límites PHP apropiados para el contenido del sitio.',

    // wp_debug
    'wpdebug.name'              => 'WP_DEBUG en producción',
    'wpdebug.display.critical'  => 'Debug + Display ACTIVOS (crítico)',
    'wpdebug.display.warning'   => 'Debug ON · Display OFF',
    'wpdebug.display.off'       => 'Desactivado',
    'wpdebug.desc.critical'     => 'WP_DEBUG y WP_DEBUG_DISPLAY están activos: los errores PHP se imprimen a los visitantes, exponiendo paths, versiones y posibles payloads.',
    'wpdebug.desc.warning'      => 'WP_DEBUG activo pero DISPLAY desactivado. Aceptable si es para logging, pero lo ideal en producción es desactivar ambos.',
    'wpdebug.desc.off'          => 'WP_DEBUG desactivado. Correcto para producción.',
    'wpdebug.recommend.critical' => 'En wp-config.php: define("WP_DEBUG", false); — o como mínimo define("WP_DEBUG_DISPLAY", false);',
    'wpdebug.recommend.warning'  => 'Si no necesitas logs, define("WP_DEBUG", false); en wp-config.php.',
    'wpdebug.solution'          => 'Configuramos WP_DEBUG correctamente: logs internos sin exponer errores a visitantes.',

    // file_editing
    'fileedit.name'              => 'Editor de archivos (DISALLOW_FILE_EDIT)',
    'fileedit.display.blocked'   => 'Bloqueado',
    'fileedit.display.enabled'   => 'Habilitado',
    'fileedit.desc.blocked'      => 'DISALLOW_FILE_EDIT está activo — el editor de temas/plugins desde admin está bloqueado. Buena práctica.',
    'fileedit.desc.enabled'      => 'Editor de archivos activo. Si un atacante obtiene acceso al admin, puede inyectar código en temas/plugins.',
    'fileedit.recommend'         => 'En wp-config.php: define("DISALLOW_FILE_EDIT", true);',
    'fileedit.solution'          => 'Bloqueamos el editor de archivos y otros vectores de inyección de código.',

    // xmlrpc_status
    'xmlrpc.name'              => 'XML-RPC',
    'xmlrpc.display.active'    => 'Activo',
    'xmlrpc.display.inactive'  => 'Desactivado',
    'xmlrpc.desc.active'       => 'XML-RPC está habilitado. Es superficie de ataque común para fuerza bruta y DDoS (pingback).',
    'xmlrpc.desc.inactive'     => 'XML-RPC desactivado. Correcto.',
    'xmlrpc.recommend'         => 'Desactivar XML-RPC si no lo usas (Jetpack/app móvil WP): add_filter("xmlrpc_enabled", "__return_false");',
    'xmlrpc.solution'          => 'Desactivamos XML-RPC o lo protegemos contra fuerza bruta y pingback.',

    // core_auto_updates
    'autoupd.name'              => 'Auto-updates de WordPress core',
    'autoupd.display.enabled'   => 'Habilitados',
    'autoupd.display.manual'    => 'Manuales',
    'autoupd.desc.enabled'      => 'Las actualizaciones automáticas del core están habilitadas. El sitio recibe parches de seguridad menores.',
    'autoupd.desc.manual'       => 'Auto-updates del core deshabilitadas. El sitio no recibe parches de seguridad automáticos — requiere actualización manual.',
    'autoupd.recommend'         => 'Habilitar actualizaciones menores automáticas o establecer un calendario de updates manual (semanal).',
    'autoupd.solution'          => 'Configuramos actualizaciones automáticas seguras con monitoreo de compatibilidad.',

    // db_prefix_status
    'dbprefix.name'             => 'Prefijo de base de datos',
    'dbprefix.display.custom'   => 'Personalizado',
    'dbprefix.display.default'  => 'Por defecto (wp_)',
    'dbprefix.desc.custom'      => 'Prefijo de tablas personalizado. {{note}} Dificulta ataques SQL automatizados.',
    'dbprefix.desc.default'     => 'El prefijo de tablas es el default "wp_". Ataques SQL automatizados apuntan a este prefijo.',
    'dbprefix.recommend'        => 'Considerar migrar a un prefijo custom (ej. "xyz_") — requiere actualizar wp-config.php y renombrar tablas/options.',
    'dbprefix.solution'         => 'Cambiamos el prefijo de DB y blindamos contra SQL injection automatizado.',

    // ssl_internal
    'sslint.name'         => 'HTTPS (site_url)',
    'sslint.display.on'   => 'Activo',
    'sslint.display.off'  => 'NO activo',
    'sslint.desc.on'      => 'El sitio usa HTTPS como URL principal. Correcto.',
    'sslint.desc.off'     => 'El site_url usa HTTP. Los formularios y el login se transmiten sin cifrar.',
    'sslint.recommend'    => 'Migrar a HTTPS completo. Actualizar site_url/home_url y configurar redirect 301 desde HTTP.',
    'sslint.solution'     => 'Instalamos SSL, forzamos HTTPS y eliminamos mixed content.',

    // object_cache
    'objcache.name'        => 'Cache de objetos persistente',
    'objcache.display.on'  => 'Activo: {{type}}',
    'objcache.display.off' => 'No configurado',
    'objcache.desc.on'     => 'Cache de objetos activo ({{type}}). Las consultas repetidas a la DB se sirven desde memoria — mejora significativa de rendimiento.',
    'objcache.desc.off'    => 'Sin cache de objetos persistente. Cada request regenera consultas a la DB. En sitios con tráfico, puede ser la mayor causa de lentitud.',
    'objcache.recommend'   => 'Instalar Redis o Memcached y activar el drop-in en wp-content/object-cache.php. Plugins: Redis Object Cache o W3 Total Cache.',
    'objcache.solution'    => 'Instalamos Redis/Memcached y configuramos object cache para reducir carga en DB.',

    // page_cache
    'pagecache.name'        => 'Cache de página',
    'pagecache.display.on'  => 'Detectado',
    'pagecache.display.off' => 'No detectado',
    'pagecache.desc.on'     => 'Se detectó cache de página (plugin tipo WP Rocket / LiteSpeed Cache / W3 Total Cache).',
    'pagecache.desc.off'    => 'No se detectó cache de página. Cada visita ejecuta PHP + DB queries — lento y costoso.',
    'pagecache.recommend'   => 'Instalar un plugin de cache (WP Rocket, LiteSpeed Cache, W3 Total Cache) o usar cache del servidor (Nginx FastCGI, Varnish).',
    'pagecache.solution'    => 'Configuramos el cache de página con un plugin o a nivel de servidor para latencia <100ms.',

    // opcache
    'opcache.name'        => 'OPcache de PHP',
    'opcache.display.on'  => 'Activo',
    'opcache.display.off' => 'Inactivo',
    'opcache.desc.on'     => 'OPcache activo — PHP cachea los scripts compilados. Ganancia típica: 30-60% de rendimiento PHP.',
    'opcache.desc.off'    => 'OPcache desactivado. PHP recompila cada script en cada petición. En PHP 7.0+ es gratis y transparente — debería estar SIEMPRE activo.',
    'opcache.recommend'   => 'Habilitar OPcache en php.ini: opcache.enable=1, opcache.memory_consumption=256, opcache.max_accelerated_files=10000.',
    'opcache.solution'    => 'Habilitamos y tunamos OPcache para máximo rendimiento PHP.',

    // image_editor
    'imgedit.name'           => 'Editor de imágenes WP',
    'imgedit.display'        => '{{editor}}',
    'imgedit.desc.imagick'   => 'WordPress usa Imagick ({{editor}}) para procesar imágenes. Mejor calidad y soporte WebP que GD.',
    'imgedit.desc.gd'        => 'WordPress usa {{editor}} (normalmente GD). Imagick produce imágenes más pequeñas y soporta formatos modernos (WebP, AVIF) mejor.',
    'imgedit.recommend'      => 'Instalar la extensión PHP Imagick y/o reinstalar el paquete ImageMagick en el servidor.',
    'imgedit.solution'       => 'Configuramos Imagick + WebP para imágenes optimizadas por defecto.',

    // permalinks
    'perm.name'           => 'Estructura de permalinks',
    'perm.display.default' => 'Default (?p=123)',
    'perm.display.custom'  => '{{structure}}',
    'perm.desc.default'   => 'Permalinks por defecto (/?p=123). Terrible para SEO y usabilidad.',
    'perm.desc.custom'    => 'Estructura personalizada: {{structure}}. Bien para SEO.',
    'perm.recommend'      => 'Cambiar en Ajustes → Permalinks a "Nombre de entrada" (/%postname%/).',
    'perm.solution'       => 'Configuramos URLs amigables para SEO.',
];
