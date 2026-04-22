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

    // open_graph
    'og.name'             => 'Open Graph (social media)',
    'og.display.none'     => 'Not configured',
    'og.display.count'    => '{{count}}/{{total}} tags present',
    'og.desc.none'        => 'No Open Graph tags found. When someone shares your site on Facebook, LinkedIn or WhatsApp, it will appear without image, without a custom title and without an attractive description.',
    'og.desc.prefix'      => '{{count}}/{{total}} OG tags configured. ',
    'og.desc.missing_suffix'   => 'Missing: {{missing}}. ',
    'og.desc.no_image_warning' => 'Without og:image, shared links will have no preview image.',
    'og.desc.complete'    => 'Complete configuration. Your site will look professional when shared on social media.',
    'og.recommend.none'   => 'Add the 5 Open Graph tags: og:title, og:description, og:image, og:url, og:type.',
    'og.recommend.missing' => 'Add the missing tags: {{missing}}.',
    'og.solution'         => 'We configure Open Graph and Twitter Cards for a professional presentation on social media.',

    // twitter_cards
    'twitter.name'             => 'Twitter Cards',
    'twitter.display.fallback' => 'Uses Open Graph fallback',
    'twitter.display.none'     => 'Not configured',
    'twitter.display.count'    => '{{count}}/{{total}} tags present',
    'twitter.desc.fallback'    => 'No Twitter Cards tags found, but Open Graph is configured and Twitter/X uses it as fallback. For finer control, adding Twitter-specific tags is recommended.',
    'twitter.desc.none'        => 'No Twitter Cards or Open Graph fallback tags found. Links shared on X/Twitter will appear without any special formatting.',
    'twitter.desc.complete'    => 'All Twitter Cards tags are configured. Optimized presentation on X/Twitter.',
    'twitter.desc.partial'     => '{{count}}/{{total}} Twitter Cards tags. Missing: {{missing}}.',
    'twitter.recommend.fallback' => 'Add twitter:card, twitter:title, twitter:description and twitter:image for full control.',
    'twitter.recommend.none'     => 'Add twitter:card, twitter:title, twitter:description and twitter:image.',
    'twitter.recommend.missing'  => 'Add the missing tags: {{missing}}.',
    'twitter.solution'         => 'We configure Twitter Cards for an optimized presentation when shared on X/Twitter.',

    // structured_data
    'schema.name'              => 'Structured data (Schema.org)',
    'schema.display.none'      => 'Not found',
    'schema.display.microdata' => 'Microdata detected (not JSON-LD)',
    'schema.display.found'     => '{{count}} types: {{list}}{{ellipsis}}',
    'schema.desc.microdata'    => 'Schema.org detected in Microdata format (HTML attributes). It works but JSON-LD is the format recommended by Google for being easier to maintain and debug.',
    'schema.desc.none'         => 'No structured data found (JSON-LD or Microdata). Without Schema markup, Google cannot display rich results (stars, prices, FAQ, breadcrumbs, etc.) for your site.',
    'schema.desc.prefix'       => '{{count}} Schema types found: {{list}}{{ellipsis}}. ',
    'schema.desc.suffix_more'  => ' and {{count}} more',
    'schema.desc.valuable'     => 'Includes valuable types for rich results in Google.',
    'schema.desc.not_valuable' => 'We recommend adding types like Organization, BreadcrumbList or FAQ for rich results.',
    'schema.recommend.microdata' => 'Migrate structured data from Microdata to JSON-LD format for better maintenance.',
    'schema.recommend.none'    => 'Implement Schema.org in JSON-LD format. Minimum recommended: Organization, WebSite, BreadcrumbList.',
    'schema.recommend.partial' => 'Add valuable Schema types: Organization, BreadcrumbList, FAQ, Product depending on the content.',
    'schema.solution'          => 'We implement complete Schema markup to appear with rich snippets in Google.',

    // rss_feeds
    'rss.name'             => 'Web Feeds (RSS/Atom)',
    'rss.display.none'     => 'Not detected',
    'rss.display.found'    => '{{count}} feed(s) detected',
    'rss.desc.found'       => '{{count}} feeds detected: {{list}}. Feeds let users subscribe to site updates.',
    'rss.desc.none'        => 'No RSS or Atom feeds detected. Feeds let users subscribe to your content.',
    'rss.recommend'        => 'Add an RSS feed so users and aggregators can follow your content.',
    'rss.solution'         => 'We set up optimized RSS feeds for content distribution.',

    // serp_preview
    'serp.name'            => 'Google preview',
    'serp.display.ok'      => 'Complete',
    'serp.display.issues'  => '{{count}} problems',
    'serp.desc.ok'         => 'Your result on Google will be shown complete with title, description and favicon.',
    'serp.desc.issues'     => 'Preview issues: {{list}}.',
    'serp.issue.no_title'      => 'No title',
    'serp.issue.title_long'    => 'Title will be truncated in Google (>{{length}} chars)',
    'serp.issue.no_description' => 'No meta description',
    'serp.issue.desc_long'     => 'Description will be truncated (>{{length}} chars)',
    'serp.issue.no_favicon'    => 'No favicon',
    'serp.recommend'       => 'Optimize title (30-70 chars), description (120-160 chars) and add a favicon.',
    'serp.solution'        => 'We optimize how your site looks in Google results.',

    // title
    'title.name'              => 'Title tag',
    'title.display.none'      => 'Not found',
    'title.display.truncated' => '{{title}} ({{length}} chars)',
    'title.desc.none'         => 'No <title> tag found. It is the most important on-page SEO signal: it appears as the title in Google results and in the browser tab.',
    'title.desc.ok'           => 'Title: "{{title}}" ({{length}} chars). {{formatNote}}',
    'title.desc.issues'       => 'Title: "{{title}}" ({{length}} chars). {{issues}}',
    'title.format.separator'  => 'Optimized format with brand separator.',
    'title.format.length_ok'  => 'Appropriate length.',
    'title.issue.short'       => 'Too short ({{length}} chars). Google may rewrite it.',
    'title.issue.long'        => 'Too long ({{length}} chars). It will be truncated in Google results showing "...".',
    'title.issue.generic'     => 'The title looks generic. It should describe the page content.',
    'title.recommend.none'    => 'Add a unique descriptive <title> tag of 30-70 characters with the main keyword.',
    'title.recommend.short'   => 'Extend the title to 30-70 characters including the main keyword and the brand name.',
    'title.recommend.long'    => 'Shorten the title to max 70 characters to avoid truncation in Google.',
    'title.recommend.generic' => 'Use a more descriptive title with the main keyword of the page.',
    'title.solution'          => 'We optimize the titles of all your pages with strategic keywords to improve ranking.',

    // meta_description
    'metadesc.name'              => 'Meta Description',
    'metadesc.display.none'      => 'Not found',
    'metadesc.display.truncated' => '{{preview}}... ({{length}} chars)',
    'metadesc.desc.none'         => 'No meta description found. This tag appears as the text below the title in Google results. Without it, Google picks a random fragment of your page.',
    'metadesc.desc.ok'           => 'Meta description ({{length}} chars): "{{preview}}...". Ideal length.{{ctaNote}}',
    'metadesc.desc.cta_note'     => ' Includes a call to action.',
    'metadesc.desc.issues'       => 'Meta description ({{length}} chars): "{{preview}}...". {{issues}}',
    'metadesc.issue.too_short'   => 'Very short ({{length}} chars). Google may ignore it and show other text.',
    'metadesc.issue.short'       => 'Somewhat short ({{length}} chars). 120-160 is recommended to use the space in Google.',
    'metadesc.issue.long'        => 'Exceeds 160 chars ({{length}}). Google will truncate it showing "...".',
    'metadesc.recommend.none'    => 'Add an attractive meta description of 120-160 characters that invites the click and includes the main keyword.',
    'metadesc.recommend.short'   => 'Extend the meta description to 120-160 characters with an attractive description that invites the click.',
    'metadesc.recommend.long'    => 'Shorten the meta description to max 160 characters to avoid truncation.',
    'metadesc.solution'          => 'We write optimized meta descriptions for every important page of your site.',

    // meta_robots
    'metarobots.name'            => 'Meta Robots',
    'metarobots.display.default' => 'Not defined (default: index, follow)',
    'metarobots.desc.default'    => 'No meta robots tag found. By default Google indexes and follows links, which is correct for most pages.',
    'metarobots.desc.ok'         => 'Meta robots: "{{value}}". Correct indexing configuration.',
    'metarobots.desc.issues'     => 'Meta robots: "{{value}}". WARNING: {{issues}} If this is not intentional, your page will not appear in Google.',
    'metarobots.issue.noindex'   => 'Contains "noindex": Google will NOT index this page.',
    'metarobots.issue.nofollow'  => 'Contains "nofollow": Google will not follow links on this page.',
    'metarobots.recommend'       => 'Verify the noindex/nofollow directives are intentional. If they are not, change to "index, follow".',
    'metarobots.solution.default' => 'We verify indexing directives are correct on all pages.',
    'metarobots.solution'        => 'We verify and fix indexing directives on all pages.',

    // favicon
    'favicon.name'          => 'Favicon',
    'favicon.display.ok'    => 'Configured',
    'favicon.display.none'  => 'Not found',
    'favicon.desc.ok'       => 'Favicon found. Your site shows an icon in the browser tab.',
    'favicon.desc.none'     => 'No favicon detected. Affects visual identity in browsers and bookmarks.',
    'favicon.recommend'     => 'Add a favicon to improve branding.',
    'favicon.solution'      => 'We configure optimized favicons for all devices.',

    // language
    'lang.name'           => 'Declared language',
    'lang.display.none'   => 'Not declared',
    'lang.display.ok'     => 'lang="{{lang}}"',
    'lang.desc.ok'        => 'Site language declared as "{{lang}}".',
    'lang.desc.none'      => 'Language not declared with the lang attribute. Google may not understand the correct language.',
    'lang.recommend'      => 'Add lang="es" (or the correct language) to the <html> tag.',
    'lang.solution'       => 'We correctly configure language and hreflang for international SEO.',
];
