<?php
/**
 * Module names and sales messages (English).
 * These are the names and marketing copy shown at the top/bottom of each
 * module in the audit report. Can still be overridden from the admin
 * settings DB (sales_<id>).
 */
return [
    // Module display names
    'wordpress.name'      => 'WordPress',
    'security.name'       => 'Security',
    'performance.name'    => 'Performance',
    'seo.name'            => 'SEO',
    'mobile.name'         => 'Mobile',
    'infrastructure.name' => 'Infrastructure',
    'conversion.name'     => 'Conversion',
    'page_health.name'    => 'Page Health',
    'wp_internal.name'    => 'WP Internal',
    'backups.name'        => 'Backups',

    // Default sales messages (overridable from admin)
    'sales.wordpress'      => 'Our support plans keep your WordPress updated and secure. We update core, plugins and themes every week with pre-testing to avoid compatibility issues.',
    'sales.security'       => 'We implement a complete security system: firewall, anti-malware protection, security headers, login protection with 2FA, and 24/7 vulnerability monitoring.',
    'sales.performance'    => 'We optimize your site to load in under 3 seconds: advanced cache configuration, CDN, image compression, lazy loading and database optimization.',
    'sales.seo'            => 'We configure the technical SEO foundations: meta tags, schema markup, sitemap, robots.txt, Open Graph, and content optimization to improve your rankings.',
    'sales.mobile'         => 'We ensure your site looks perfect on mobile: responsive design, mobile speed optimization and user experience adapted to touch screens.',
    'sales.infrastructure' => 'We recommend and migrate your site to the most suitable hosting, configure CDN, HTTP/2, compression and all necessary server optimizations.',
    'sales.conversion'     => 'We install and configure the essential tools: Google Analytics, live chat, optimized forms, tracking pixels and legal compliance (cookies, GDPR).',
    'sales.page_health'    => 'We fix all the technical errors on your site: broken resources, mixed content, HTML errors, encoding issues and everything that affects the technical health of your pages.',
    'sales.wp_internal'    => 'We analyze the internal state of your WordPress and optimize it at the level of plugins, database, configuration and performance.',
    'sales.backups'        => 'We configure automatic daily backups with 30-day retention, stored off-server. We include free restoration in case of emergency.',

    // Failed module summary (shown when an analyzer throws)
    'failed.summary' => 'Could not analyze this module.',

    // CTA defaults
    'cta.title'            => 'All these problems have a solution',
    'cta.description'      => 'At Imagina WP we are exclusive WordPress specialists with over 15 years of experience. Our monthly support plans include everything your site needs to stay secure, fast and optimized.',
    'cta.button_whatsapp'  => 'Talk to an Expert on WhatsApp',
    'cta.button_plans'     => 'View Plans and Pricing',
];
