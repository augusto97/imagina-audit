<?php
return [
    // Fallback name for a vulnerability when the API doesn't supply one.
    'vuln.fallback_name' => 'Vulnerability',

    // ——— Environment ——————————————————————————————————————————————
    'env.wp_outdated.title'   => 'WordPress {{version}} — {{latest}} available',
    'env.wp_outdated.action'  => 'Update the core from Dashboard → Updates (backup first).',
    'env.php_outdated.title'  => 'PHP {{version}} is nearing end-of-life',
    'env.php_outdated.action' => 'Upgrade to PHP 8.2 or higher. Verify plugin compatibility first.',
    'env.missing_exts.title'  => 'Recommended PHP extensions missing: {{exts}}',
    'env.missing_exts.action' => 'Install from the host\'s PHP panel. Imagick is especially important for WebP/compression.',
    'env.debug_critical.title'  => 'WP_DEBUG + WP_DEBUG_DISPLAY are active in production',
    'env.debug_critical.action' => 'PHP errors are being printed to visitors (path and version leaks). Turn WP_DEBUG_DISPLAY off in wp-config.php immediately.',
    'env.debug_warning.title'   => 'WP_DEBUG is active',
    'env.debug_warning.action'  => 'Acceptable if only for internal logging. In production, the best practice is to turn debug off completely.',

    // ——— Themes ———————————————————————————————————————————————————
    'themes.no_child.title'         => 'Theme {{name}} without child theme',
    'themes.no_child.action'        => 'Any direct customization to the theme will be lost on update. Create a child theme with Template: {{slug}}.',
    'themes.outdated.title'         => 'Active theme outdated: {{name}}',
    'themes.outdated.action'        => 'Update from Appearance → Themes.',
    'themes.inactive_excess.title'  => '{{count}} inactive themes installed',
    'themes.inactive_excess.action' => 'Remove unused themes from Appearance → Themes (keep only the active one and a default as fallback).',

    // ——— Security actions (by check key) ——————————————————————————
    'security.action.wp_debug'           => 'In wp-config.php: define("WP_DEBUG", false); — or at least disable WP_DEBUG_DISPLAY.',
    'security.action.wp_debug_display'   => 'Set define("WP_DEBUG_DISPLAY", false); in wp-config.php so errors don\'t leak to visitors.',
    'security.action.file_editing'       => 'Add define("DISALLOW_FILE_EDIT", true); in wp-config.php.',
    'security.action.file_mods'          => 'define("DISALLOW_FILE_MODS", true); blocks installing/updating plugins from admin — only recommended when you have CI/CD.',
    'security.action.db_prefix'          => 'Change the wp_ prefix to a custom one via a migration script (wp-config + rename tables + serialized options).',
    'security.action.auto_updates_core'  => 'Enable minor auto-updates: add_filter("auto_update_core", "__return_true"); or leave WP defaults.',
    'security.action.app_passwords'      => 'If you don\'t use external apps (Jetpack, mobile app) disable them with add_filter("wp_is_application_passwords_available", "__return_false");',
    'security.action.wp_config_writable' => 'Set wp-config.php permissions to 440 or 400 (read-only).',
    'security.action.xmlrpc'             => 'Disable XML-RPC if you don\'t use it: add_filter("xmlrpc_enabled", "__return_false"); — reduces the brute-force attack surface.',
    'security.action.ssl'                => 'Migrate to HTTPS: update site_url/home_url, install SSL, enforce a 301 redirect from HTTP.',

    // ——— Database —————————————————————————————————————————————————
    'db.autoload.title'    => 'Heavy autoload: {{size}} in {{count}} options',
    'db.autoload.action'   => 'Slows down EVERY request (these options load on every page load). Use WP-Optimize / Autoload Options Monitor to identify the heaviest ones and set autoload=no.',
    'db.revisions.title'   => '{{count}} accumulated revisions',
    'db.revisions.action'  => 'define("WP_POST_REVISIONS", 5); + clean historical ones with WP-Optimize.',
    'db.myisam.title'      => '{{count}} tables using the MyISAM engine',
    'db.myisam.action'     => 'Convert to InnoDB: ALTER TABLE name ENGINE=InnoDB; (one at a time, back up first).',
    'db.orphaned.title'    => '{{count}} orphaned postmeta rows',
    'db.orphaned.action'   => 'Clean up with WP-Optimize or direct SQL over wp_postmeta LEFT JOIN wp_posts.',

    // ——— Performance ——————————————————————————————————————————————
    'perf.opcache.title'      => 'OPcache disabled',
    'perf.opcache.action'     => 'In php.ini: opcache.enable=1, opcache.memory_consumption=256. Typical gain is 30-60% PHP performance.',
    'perf.object_cache.title' => 'No persistent object cache',
    'perf.object_cache.action'=> 'Install Redis or Memcached + the Redis Object Cache plugin. Reduces repeated DB queries.',
    'perf.page_cache.title'   => 'No page cache detected',
    'perf.page_cache.action'  => 'Install WP Rocket / LiteSpeed Cache / W3 Total Cache, or enable server-level caching (Nginx FastCGI, Varnish).',
    'perf.image_editor.title' => 'WP is using {{editor}} (no Imagick)',
    'perf.image_editor.action'=> 'Install the PHP Imagick extension for better quality, WebP and AVIF.',

    // ——— Cron —————————————————————————————————————————————————————
    'cron.overdue.title'               => '{{count}} overdue cron jobs',
    'cron.overdue.action_disabled'     => 'WP_CRON is disabled. Verify the system cron is hitting wp-cron.php every minute.',
    'cron.overdue.action_low_traffic'  => 'A low-traffic site doesn\'t trigger WP_CRON. Configure a server cron: */5 * * * * wget -qO- https://your-site.com/wp-cron.php',
    'cron.hook_abuse.title'            => 'Hook {{hook}} registered {{count}} times',
    'cron.hook_abuse.action'           => 'Possible wp_schedule_event leak without unscheduling. Check the plugin responsible.',

    // ——— Media ————————————————————————————————————————————————————
    'media.no_webp.title'   => 'Images without WebP format',
    'media.no_webp.action'  => 'Install ShortPixel / Imagify / EWWW to automatically convert JPEG/PNG to WebP — typical 25-35% size reduction.',
    'media.heavy.title'     => 'Heavy media library ({{size}})',
    'media.heavy.action'    => 'Enable native lazy loading (already in WP 5.5+), serve images via CDN, compress with quality 75-85%.',

    // ——— Users ————————————————————————————————————————————————————
    'users.too_many_admins.title'  => '{{count}} users with administrator role',
    'users.too_many_admins.action' => 'Apply the principle of least privilege: downgrade to Editor those who don\'t need to touch plugins/themes. Require 2FA for the remaining admins.',
    'users.no_admins.title'        => 'No visible administrator',
    'users.no_admins.action'       => 'May be a site with a custom role. Verify who actually has "manage_options".',
    'users.many_users.title'       => '{{count}} registered users',
    'users.many_users.action'      => 'Review whether wp_users is growing from spam signups. Consider an anti-spam on registration (Cloudflare Turnstile, reCAPTCHA).',

    // ——— Content ——————————————————————————————————————————————————
    'content.rest_bloat.title'         => '{{count}} REST routes exposed',
    'content.rest_bloat.action'        => 'High volume indicates plugin bloat. Audit the top namespaces — some could be disabled on the frontend with rest_api_init.',
    'content.cpt_rest_exposed.title'   => 'Public CPT "{{slug}}" exposed in the REST API',
    'content.cpt_rest_exposed.action'  => 'If it shouldn\'t be accessible without auth, restrict it with rest_authentication_errors or show_in_rest=false.',
];
