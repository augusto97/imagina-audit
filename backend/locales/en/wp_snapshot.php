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

    // db_size
    'dbsize.name'           => 'Database size',
    'dbsize.display'        => '{{size}} · {{rows}} rows · {{tables}} tables',
    'dbsize.desc.ok'        => 'Database of {{size}} ({{rows}} rows, {{tables}} tables). Healthy size.',
    'dbsize.desc.heavy'     => 'Database of {{size}} — {{label}}. The top tables show where the weight is (see details).',
    'dbsize.label.critical' => 'CRITICAL: very heavy DB',
    'dbsize.label.large'    => 'large',
    'dbsize.recommend'      => 'Review the top tables: security plugins (Wordfence = wfHits, wfLogins), orders (WooCommerce), logs. Often a plugin accumulates logs without rotation.',
    'dbsize.solution'       => 'We optimize the DB: purge plugin logs, tune retention, and add indexes where needed.',

    // db_autoload
    'dbautoload.name'      => 'Autoloaded options',
    'dbautoload.display'   => '{{size}} · {{count}} options',
    'dbautoload.desc.ok'   => 'Autoload of {{size}} with {{count}} options. Healthy (<512 KB is ideal).',
    'dbautoload.desc.bad'  => 'Heavy autoload ({{size}}, {{count}} options). Every WP request loads ALL these options into memory — a multi-MB autoload slows the entire site.',
    'dbautoload.recommend' => 'Install "WP-Optimize" or "Autoload Options Monitor" to identify which options weigh the most. Often deactivated plugins leave junk with autoload=yes.',
    'dbautoload.solution'  => 'We clean up heavy autoload options and configure best practices.',

    // db_engine
    'dbengine.name'      => 'Database engine',
    'dbengine.display'   => '{{count}} tables with MyISAM',
    'dbengine.desc'      => '{{count}} tables use MyISAM. No transactions, no row-level locking, no foreign keys. InnoDB is superior in performance and concurrency.',
    'dbengine.recommend' => 'Convert to InnoDB: ALTER TABLE table_name ENGINE=InnoDB; (one by one, starting with the smallest). Back up first.',
    'dbengine.solution'  => 'We migrate MyISAM tables to InnoDB for concurrency and performance.',

    // db_revisions
    'dbrev.name'      => 'Post revisions',
    'dbrev.display'   => '{{count}} revisions',
    'dbrev.desc.ok'   => '{{count}} accumulated revisions. Normal amount.',
    'dbrev.desc.bad'  => '{{count}} revisions taking up space in wp_posts. Each edit generates a new revision with no default limit.',
    'dbrev.recommend' => 'Limit revisions: in wp-config.php, define("WP_POST_REVISIONS", 5). Clean old ones with WP-Optimize or similar.',
    'dbrev.solution'  => 'We clean up old revisions and limit future ones.',

    // db_transients
    'dbtrans.name'      => 'Transients in options',
    'dbtrans.display'   => '{{count}} transients',
    'dbtrans.desc.ok'   => '{{count}} transients. Normal.',
    'dbtrans.desc.bad'  => '{{count}} transients. Many plugins leave expired transients that accumulate — WP does not clean them itself if they don\'t use proper TTL.',
    'dbtrans.recommend' => 'Clean with WP-Optimize. Set up object cache (Redis) so transients go to memory instead of wp_options.',
    'dbtrans.solution'  => 'We set up Redis object cache so transients never touch the DB.',

    // db_orphaned_meta
    'dbmeta.name'      => 'Orphaned metadata',
    'dbmeta.display'   => '{{count}} orphaned records',
    'dbmeta.desc'      => '{{count}} records in wp_postmeta point to posts that no longer exist. They are junk left behind by plugins that don\'t clean up when posts are deleted.',
    'dbmeta.recommend' => 'Clean with WP-Optimize or SQL: DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;',
    'dbmeta.solution'  => 'We clean up orphaned metadata and other DB residue.',

    // cron_status
    'cron.name'                => 'Scheduled tasks (WP Cron)',
    'cron.display.ok'          => '{{total}} tasks OK',
    'cron.display.overdue'     => '{{overdue}} overdue of {{total}}',
    'cron.desc.ok'             => '{{total}} cron jobs registered, executing on time.',
    'cron.desc.ok_no_wpcron'   => ' WP_CRON is disabled (real server cron should be configured).',
    'cron.desc.overdue'        => '{{overdue}} of {{total}} cron jobs overdue. Automatic tasks (updates, emails, backups) are not running.',
    'cron.recommend.no_wpcron' => 'Verify the server cron is calling wp-cron.php every minute.',
    'cron.recommend.low_traf'  => 'On low-traffic sites, WP_CRON is not triggered. Configure real system cron: */5 * * * * wget -qO- https://your-site.com/wp-cron.php',
    'cron.solution'            => 'We configure real server-side cron so tasks run on time.',

    // media_library
    'media.name'             => 'Media library',
    'media.display'          => '{{count}} files · {{size}}',
    'media.desc.ok'          => '{{count}} files ({{size}}) in the library. Reasonable size.',
    'media.desc.bad_prefix'  => '{{count}} files taking up {{size}}. ',
    'media.desc.bad_heavy'   => 'The library is heavy — likely there are uncompressed images not converted to WebP.',
    'media.desc.bad_normal'  => 'Optimizable with compression and WebP.',
    'media.recommend'        => 'Install ShortPixel or Imagify to compress and convert to WebP automatically. Configure lazy loading (WP does this since 5.5).',
    'media.solution'         => 'We compress images, convert them to WebP and serve them via CDN.',

    // custom_post_types
    'cpt.name'         => 'Content types',
    'cpt.display'      => '{{custom}} custom · {{total}} total',
    'cpt.desc.none'    => 'Only WP native types are used (posts, pages). Simple structure.',
    'cpt.desc.custom'  => '{{custom}} custom post types (CPTs) registered by plugins/theme. They can affect performance if REST is overused (show_in_rest=true exposes all content).',
    'cpt.solution'     => 'We audit CPTs and optimize queries/indexes for those handling lots of content.',

    // rest_api_routes
    'restroutes.name'      => 'REST API routes',
    'restroutes.display'   => '{{total}} routes in {{namespaces}} namespaces',
    'restroutes.desc.ok'   => '{{total}} REST routes. Normal volume for a WordPress site.',
    'restroutes.desc.bad'  => '{{total}} REST routes exposed. Each plugin adds endpoints; too many indicate plugin bloat and potentially exposed data.',
    'restroutes.recommend' => 'Audit which plugins expose so many routes. Consider whether any can be deactivated or whether REST should be restricted to authenticated users.',
    'restroutes.solution'  => 'We restrict and audit REST endpoints to reduce attack surface.',
];
