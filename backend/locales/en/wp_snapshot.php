<?php
/**
 * WpSnapshot analyzer strings (English). Built incrementally per
 * sub-checker so each commit stays manageable.
 */
return [
    // Module wrapper
    'summary.prefix'     => 'Internal site analysis: {{score}}/100',
    'summary.outdated'   => ' · {{outdated}} of {{total}} plugins with pending update',
    'summary.site'       => '. Site: {{name}}',

    // users_roles (UsersChecker)
    'users.name'             => 'Users & roles',
    'users.display_one_admin'  => '{{total}} users · {{admins}} admin',
    'users.display_many_admin' => '{{total}} users · {{admins}} admins',
    'users.desc.ok'          => '{{total}} registered users with {{admins}} administrator. Least-privilege applied correctly.',
    'users.desc.too_many'    => '{{total}} users with {{admins}} administrators. Each extra admin increases the attack surface — it only takes one with a weak password or who falls for phishing.',
    'users.recommend.many'   => 'Review the admin list under Users → All Users (filter by role: Administrator). Demote anyone who does not need to change plugins/themes to Editor.',
    'users.recommend.two'    => 'Check whether both admins are really needed.',
    'users.solution'         => 'We apply the principle of least privilege and enable 2FA on every admin account.',

    // app_passwords (UsersChecker)
    'apppw.name'              => 'Application Passwords (REST API)',
    'apppw.display.enabled'   => 'Enabled',
    'apppw.display.disabled'  => 'Disabled',
    'apppw.desc.enabled'      => 'Application Passwords are enabled. They allow authenticating REST requests from external apps (WP mobile app, Zapier, etc.). Safe if unused, but you can disable them to reduce surface if you do not need them.',
    'apppw.desc.disabled'     => 'Application Passwords are disabled. Reduces attack surface on the REST API.',
    'apppw.solution'          => 'We configure authentication methods correctly based on actual site use.',

    // plugins_outdated
    'pluginsout.name'           => 'Outdated plugins',
    'pluginsout.display.ok'     => 'All {{total}} plugins up to date',
    'pluginsout.display.bad'    => '{{outdated}} of {{total}} with pending update',
    'pluginsout.desc.ok'        => 'All {{total}} plugins are on their latest version.',
    'pluginsout.desc.bad'       => '{{outdated}} plugins have updates available ({{active}} active). Outdated plugins are the main cause of hacked WordPress sites.',
    'pluginsout.recommend'      => 'Update from WP Admin → Plugins. Back up before updating critical plugins (WooCommerce, Elementor, etc.).',
    'pluginsout.solution'       => 'We update all plugins weekly with prior compatibility testing.',

    // plugins_inactive
    'pluginsinact.name'         => 'Inactive plugins',
    'pluginsinact.display.ok'   => 'None',
    'pluginsinact.display.bad'  => '{{count}} plugins',
    'pluginsinact.desc.ok'      => 'No inactive plugins. Correct.',
    'pluginsinact.desc.bad'     => '{{count}} inactive plugins installed. Even if disabled, their files remain on the server and can be exploited if they contain vulnerabilities.',
    'pluginsinact.recommend'    => 'Remove unused plugins under Plugins → Inactive → Delete. Keep only active ones.',
    'pluginsinact.solution'     => 'We clean up inactive plugins reducing attack surface and site size.',

    // plugin_overload
    'overload.name'             => 'Active plugin count',
    'overload.display'          => '{{count}} active plugins',
    'overload.desc'             => '{{count}} active plugins. Each plugin adds DB queries, PHP code and conflict potential. Rule of thumb: <20 plugins on most sites.',
    'overload.recommend.heavy'  => 'Audit which plugins are really necessary. Combine functionalities (many builders include what several plugins do). Remove redundant ones.',
    'overload.recommend.normal' => 'Periodically check whether any plugin can be replaced with theme code or merged with others.',
    'overload.solution'         => 'We audit your plugin stack and recommend consolidation.',

    // plugins_auto_update
    'pluginsauto.name'          => 'Auto-update of active plugins',
    'pluginsauto.display'       => '{{withAuto}}/{{total}} with auto-update ({{pct}}%)',
    'pluginsauto.desc.prefix'   => '{{withAuto}} of {{total}} active plugins have automatic updates enabled. ',
    'pluginsauto.desc.good'     => 'Good practice.',
    'pluginsauto.desc.bad'      => 'Those without auto-update are only updated manually.',
    'pluginsauto.recommend'     => 'Under Plugins → enable "Automatic updates" for plugins you trust (Yoast, Elementor, WooCommerce, etc.).',
    'pluginsauto.solution'      => 'We configure selective auto-updates with automatic rollback if something fails.',

    // mu_plugins_dropins
    'mudrop.name'              => 'MU-plugins & drop-ins',
    'mudrop.display'           => '{{mu}} MU + {{drop}} drop-ins',
    'mudrop.desc'              => '{{total}} components installed silently (MU-plugins and drop-ins). They load automatically and may be injected by hosting/backup plugins/ManageWP/etc. Worth reviewing one by one.',
    'mudrop.recommend'         => 'Check wp-content/mu-plugins/ and wp-content/*.php (drop-ins like object-cache.php, advanced-cache.php, db.php). Make sure each is legitimate.',
    'mudrop.solution'          => 'We audit MU-plugins and drop-ins to detect malware and backdoors.',

    // theme_active
    'theme.name'               => 'Active theme',
    'theme.display'            => '{{name}} {{version}}{{childSuffix}}',
    'theme.display.child'      => ' (child)',
    'theme.unknown'            => 'Unknown',
    'theme.desc.child'         => 'You use a child theme of {{parent}}. You can customize without losing changes when the parent updates.',
    'theme.desc.no_child'      => 'You use the {{name}} theme directly. Any code changes will be lost on update. {{updateNote}}',
    'theme.desc.update_note'   => 'Also, an update is available.',
    'theme.recommend.no_child' => 'Create a child theme for safe customizations. Under wp-content/themes create a folder with style.css (Template: {{slug}}) and functions.php.',
    'theme.recommend.update'   => 'Update the theme to the latest version.',
    'theme.solution'           => 'We create child themes for safe customizations and update the theme weekly.',

    // themes_inactive
    'themesinact.name'         => 'Inactive themes',
    'themesinact.display'      => '{{count}} unused themes of {{total}}',
    'themesinact.desc'         => '{{count}} inactive themes on disk. Even if unused, their code remains on the server and may contain vulnerabilities. Keep only the active one (+ its parent if child + one default fallback).',
    'themesinact.recommend'    => 'Remove inactive themes under Appearance → Themes → Details → Delete.',
    'themesinact.solution'     => 'We remove unnecessary themes reducing attack surface.',
];
