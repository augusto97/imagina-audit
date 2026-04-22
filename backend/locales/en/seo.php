<?php
/**
 * SEO analyzer strings (English). Added incrementally per sub-checker.
 *
 * Keys:
 *   summary                 - module-level summary
 *   <metric_id>.name        - metric label
 *   <metric_id>.display.xxx - variants of displayValue
 *   <metric_id>.desc.xxx    - variants of description
 *   <metric_id>.recommend[.xxx] - how-to-fix CTA
 *   <metric_id>.solution    - Imagina WP solution
 */
return [
    'summary' => 'Your site has an SEO score of {{score}}/100.',

    // sitemap
    'sitemap.name'             => 'XML Sitemap',
    'sitemap.display.none'     => 'Not found',
    'sitemap.display.found'    => '{{path}}{{suffix}}',
    'sitemap.display.count_urls'     => ' ({{count}} URLs)',
    'sitemap.display.count_sitemaps' => ' ({{count}} sitemaps)',
    'sitemap.desc.none'        => 'No XML sitemap found at /sitemap.xml, /sitemap_index.xml or referenced in robots.txt. Without a sitemap, Google discovers pages only via internal links, which can leave pages unindexed.',
    'sitemap.desc.found_index' => 'Sitemap found at {{path}}. It is a sitemap index{{suffix}}. Professional structure.',
    'sitemap.desc.found_index_sub'  => ' with {{count}} sub-sitemaps',
    'sitemap.desc.found_urls'  => 'Sitemap found at {{path}}. Contains {{count}} indexed URLs.',
    'sitemap.desc.found_ok'    => 'Sitemap found at {{path}}. Correctly accessible.',
    'sitemap.recommend'        => 'Generate an XML sitemap with a SEO plugin (Yoast, Rank Math) and register it in Google Search Console.',
    'sitemap.solution'         => 'We generate optimized automatic sitemaps and register them in Google Search Console.',

    // robots
    'robots.name'             => 'Robots.txt',
    'robots.display.none'     => 'Not found',
    'robots.display.blocks'   => 'BLOCKS ENTIRE SITE',
    'robots.display.found'    => '{{lines}} directives · {{disallow}} Disallow{{sitemapSuffix}}',
    'robots.display.sitemap_suffix' => ' · Sitemap',
    'robots.desc.none'        => 'No robots.txt file found. While not mandatory, it is a best practice to indicate which sections search engines should not crawl and where the sitemap is located.',
    'robots.desc.blocks'      => 'The robots.txt contains "Disallow: /" which blocks the ENTIRE site from search engines. Google CANNOT index any page. This is a critical problem unless intentional (site under development).',
    'robots.desc.prefix'      => 'Robots.txt present with {{lines}} active directives and {{disallow}} Disallow rules. ',
    'robots.desc.with_sitemap' => 'Includes sitemap reference. ',
    'robots.desc.ok'          => 'Correct configuration.',
    'robots.desc.notes'       => '{{notes}}',
    'robots.note.no_sitemap'  => 'No sitemap reference (add "Sitemap: URL").',
    'robots.note.crawl_delay' => 'Uses Crawl-delay (Google ignores it but other search engines may crawl slower).',
    'robots.recommend.none'     => 'Create a robots.txt file with appropriate directives and sitemap reference.',
    'robots.recommend.blocks'   => 'Change "Disallow: /" to specific directives that only block private sections.',
    'robots.recommend.sitemap'  => 'Add the directive "Sitemap: https://yoursite.com/sitemap.xml" to robots.txt.',
    'robots.solution.none'    => 'We configure optimized robots.txt for your site\'s SEO.',
    'robots.solution.blocks'  => 'We configure optimized robots.txt that protects private areas without blocking public content.',

    // canonical
    'canonical.name'            => 'Canonical URL',
    'canonical.display.none'    => 'Not found',
    'canonical.display.self'    => 'Self-referencing',
    'canonical.display.diff'    => '{{canonical}}',
    'canonical.desc.none'       => 'No <link rel="canonical"> tag found. Without canonical, if your page is accessible by multiple URLs (with/without www, with/without trailing slash, with parameters), Google may index duplicates and split SEO authority.',
    'canonical.desc.self'       => 'Canonical configured: {{canonical}}. Points to itself (self-referencing). Correct.',
    'canonical.desc.diff'       => 'Canonical configured: {{canonical}}. Points to a different URL than the current one. Verify this is intentional.',
    'canonical.recommend.none'  => 'Add <link rel="canonical" href="PREFERRED-URL"> on every page.',
    'canonical.recommend.diff'  => 'Verify the canonical points to the correct URL. A different canonical indicates this page is a variant.',
    'canonical.solution'        => 'We configure correct canonicals on all pages to avoid duplicate content.',

    // hreflang
    'hreflang.name'             => 'Hreflang (multi-language)',
    'hreflang.display.none'     => 'Not configured',
    'hreflang.display.found'    => '{{count}} languages: {{list}}',
    'hreflang.desc.none'        => 'No hreflang tags found. If your site is in a single language this is fine. If you have versions in other languages, you need hreflang so Google does not treat them as duplicate content.',
    'hreflang.desc.found'       => '{{count}} languages/regions configured: {{list}}. {{xDefaultNote}}',
    'hreflang.desc.with_xdefault'    => 'Includes x-default (default page). Correct configuration.',
    'hreflang.desc.without_xdefault' => 'Missing x-default (recommended to indicate the default version).',
    'hreflang.recommend'        => 'Add hreflang="x-default" pointing to the main version of the site.',
    'hreflang.solution'         => 'We configure hreflang for multi-language and multi-region sites.',

    // url_structure
    'url.name'            => 'URL structure',
    'url.display'         => '{{scheme}} · {{www}} · {{length}} chars',
    'url.display.https'   => 'HTTPS',
    'url.display.http'    => 'HTTP',
    'url.display.www'     => 'www',
    'url.display.no_www'  => 'no www',
    'url.desc.prefix'     => 'URL: {{url}}. ',
    'url.desc.https'      => 'HTTPS active. ',
    'url.desc.http'       => 'Unencrypted HTTP. ',
    'url.desc.with_www'   => 'Uses www. ',
    'url.desc.no_www'     => 'No www. ',
    'url.desc.clean'      => 'Clean, SEO-friendly URL structure.',
    'url.issue.no_https'  => 'Does not use HTTPS. Google prioritizes secure sites.',
    'url.issue.params'    => 'URL has {{count}} parameters. Clean URLs are better for SEO.',
    'url.issue.long'      => 'Long URL ({{length}} characters). Short descriptive URLs are recommended.',
    'url.solution'        => 'We optimize URLs to be short, descriptive and SEO-friendly.',
];
