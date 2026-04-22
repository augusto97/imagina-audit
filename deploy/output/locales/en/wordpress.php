<?php
return [
    // Module-level
    'summary.wp'     => 'Your WordPress installation has a score of {{score}}/100.',
    'summary.not_wp' => 'This site does not appear to be built with WordPress.',

    // When site is NOT WordPress
    'not_wp.name'        => 'WordPress detection',
    'not_wp.display'     => 'Not WordPress',
    'not_wp.description' => 'No WordPress detected on this site. This module does not apply and does not affect the global score.',
    'not_wp.solution'    => 'We are exclusive WordPress specialists with over 15 years of experience.',
    'not_wp.module_summary' => 'This site is not built with WordPress. This module does not apply.',

    // wp_version
    'version.name'             => 'WordPress version',
    'version.display.none'     => 'Not detected',
    'version.desc.current'     => 'WordPress is up to date.',
    'version.desc.outdated'    => 'WordPress {{version}} is outdated. The latest version is {{latest}}.',
    'version.desc.unknown'     => 'Could not detect the WordPress version.',
    'version.recommendation'   => 'Update WordPress to the latest version to patch vulnerabilities and improve performance.',
    'version.solution'         => 'We update WordPress weekly with prior compatibility testing.',

    // wp_theme
    'theme.name'           => 'WordPress theme',
    'theme.display.none'   => 'Not detected',
    'theme.desc.found'     => 'Active theme: {{display}}.',
    'theme.desc.missing'   => 'Could not detect the active theme.',
    'theme.solution'       => 'We keep your theme updated and optimized.',

    // wp_plugins
    'plugins.name'             => 'Detected plugins',
    'plugins.display'          => '{{count}} plugins ({{outdated}} outdated)',
    'plugins.desc.found'       => '{{count}} plugins detected. {{outdatedSuffix}}',
    'plugins.desc.outdated_suffix'  => '{{count}} need updating.',
    'plugins.desc.all_up_suffix'    => 'All appear up to date.',
    'plugins.desc.none'        => 'No plugins detected (they may be hidden).',
    'plugins.recommendation'   => 'Update outdated plugins to patch vulnerabilities.',
    'plugins.solution'         => 'We update all your plugins weekly with compatibility testing.',

    // user_enumeration
    'user_enum.name'              => 'User enumeration',
    'user_enum.display.exposed'   => 'Exposed',
    'user_enum.display.exposed_with_user' => 'Exposed ({{username}})',
    'user_enum.display.safe'      => 'Protected',
    'user_enum.desc.exposed'      => 'User enumeration is enabled. The username "{{username}}" was discovered via /?author=1. Attackers can use these usernames for brute-force attacks.',
    'user_enum.desc.safe'         => 'User enumeration via /?author=1 is protected or disabled.',
    'user_enum.recommendation'    => 'Block user enumeration with a security plugin or .htaccess rule.',
    'user_enum.solution'          => 'We block user enumeration and protect against brute-force attacks.',

    // rest_api_exposed
    'rest_api.name'             => 'Users REST API',
    'rest_api.display.exposed'  => 'Exposed - users visible',
    'rest_api.display.safe'     => 'Protected',
    'rest_api.desc.exposed'     => 'The REST API exposes the site usernames. This makes brute-force attacks easier.',
    'rest_api.desc.safe'        => 'The users REST API is protected or not accessible.',
    'rest_api.recommendation'   => 'Disable or restrict access to the /wp-json/wp/v2/users endpoint.',
    'rest_api.solution'         => 'We protect the REST API and block user enumeration.',

    // xmlrpc_active
    'xmlrpc.name'             => 'XML-RPC',
    'xmlrpc.display.active'   => 'Active',
    'xmlrpc.display.inactive' => 'Disabled or not accessible',
    'xmlrpc.desc.active'      => 'XML-RPC is enabled. It can be used for brute-force attacks and amplified DDoS.',
    'xmlrpc.desc.inactive'    => 'XML-RPC is not accessible.',
    'xmlrpc.recommendation'   => 'Disable XML-RPC if it is not used for external apps.',
    'xmlrpc.solution'         => 'We disable XML-RPC and protect against brute-force attacks.',

    // debug_mode
    'debug.name'             => 'Debug mode',
    'debug.display.visible'  => 'PHP errors visible',
    'debug.display.hidden'   => 'Disabled',
    'debug.desc.visible'     => 'Visible PHP errors were detected on the page. This exposes server information.',
    'debug.desc.hidden'      => 'No visible PHP errors detected.',
    'debug.recommendation'   => 'Disable WP_DEBUG in production and hide error messages.',
    'debug.solution'         => 'We configure debug mode properly and hide errors in production.',

    // sensitive_files
    'sensitive.name'             => 'Exposed sensitive files',
    'sensitive.display.exposed'  => '{{count}} files exposed',
    'sensitive.display.none'     => 'None detected',
    'sensitive.desc.exposed'     => 'Sensitive files publicly accessible were found: {{list}}',
    'sensitive.desc.none'        => 'No sensitive files exposed.',
    'sensitive.recommendation'   => 'Remove or protect the exposed sensitive files immediately.',
    'sensitive.solution'         => 'We protect all sensitive files and configure access rules.',
];
