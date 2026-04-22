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
];
