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
];
