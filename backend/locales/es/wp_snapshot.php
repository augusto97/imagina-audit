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
];
