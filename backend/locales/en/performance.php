<?php
return [
    // Module
    'summary' => 'Your site has a performance score of {{score}}/100.',

    // pagespeed_mobile / desktop
    'psi_mobile.name'          => 'PageSpeed Mobile',
    'psi_desktop.name'         => 'PageSpeed Desktop',
    'psi.display.score'        => '{{score}}/100',
    'psi.display.na'           => 'Not available',
    'psi_mobile.desc.ok'       => 'Google PageSpeed rates your mobile site at {{score}}/100.',
    'psi_mobile.desc.na'       => 'Could not retrieve the mobile PageSpeed score.',
    'psi_mobile.recommend'     => 'Optimize page load speed on mobile devices.',
    'psi_mobile.solution'      => 'We optimize your site to reach 90+ scores in PageSpeed.',
    'psi_desktop.desc.ok'      => 'Google PageSpeed rates your desktop site at {{score}}/100.',
    'psi_desktop.desc.na'      => 'Could not retrieve the desktop PageSpeed score.',
    'psi_desktop.recommend'    => 'Optimize page load speed on desktop.',
    'psi_desktop.solution'     => 'We set up cache, CDN and advanced performance optimizations.',

    // lcp
    'lcp.name'               => 'Largest Contentful Paint (LCP)',
    'lcp.display'            => '{{seconds}}s',
    'lcp.desc.prefix'        => 'The main content takes {{seconds}}s to load. ',
    'lcp.desc.good'          => 'Good load time.',
    'lcp.desc.bad'           => 'Less than 2.5 seconds is recommended.',
    'lcp.recommend'          => 'Optimize images, lazy loading and reduce render-blocking resources.',
    'lcp.solution'           => 'We reduce LCP with cache, CDN, image optimization and code optimization.',

    // fcp
    'fcp.name'        => 'First Contentful Paint (FCP)',
    'fcp.display'     => '{{seconds}}s',
    'fcp.desc'        => 'First visible content appears in {{seconds}}s.',
    'fcp.recommend'   => 'Reduce render-blocking resources and optimize critical CSS.',
    'fcp.solution'    => 'We implement critical CSS inline and load optimization.',

    // cls
    'cls.name'        => 'Cumulative Layout Shift (CLS)',
    'cls.display'     => '{{value}}',
    'cls.desc.prefix' => 'Cumulative layout shift is {{value}}. ',
    'cls.desc.good'   => 'Good value.',
    'cls.desc.bad'    => 'Less than 0.1 is recommended.',
    'cls.recommend'   => 'Set dimensions for images and embeds. Avoid inserting dynamic content above existing content.',
    'cls.solution'    => 'We eliminate layout shifts for a stable visual experience.',

    // tbt
    'tbt.name'        => 'Total Blocking Time (TBT)',
    'tbt.display'     => '{{ms}}ms',
    'tbt.desc.prefix' => 'Total blocking time is {{ms}}ms. ',
    'tbt.desc.good'   => 'Good value.',
    'tbt.desc.bad'    => 'Less than 200ms is recommended.',
    'tbt.recommend'   => 'Reduce heavy JavaScript and split long tasks.',
    'tbt.solution'    => 'We optimize JavaScript and remove unnecessary scripts.',

    // opportunities
    'opp.name'             => 'Improvement opportunities',
    'opp.display'          => '{{count}} opportunities{{savings}}',
    'opp.display.savings'  => ' · {{seconds}}s potential savings',
    'opp.desc.prefix'      => 'Google detected {{count}} optimization opportunities: {{list}}',
    'opp.desc.suffix_more' => '... and {{count}} more.',
    'opp.desc.suffix_end'  => '.',
    'opp.recommend'        => 'Apply the optimizations suggested by PageSpeed to improve load speed.',
    'opp.solution'         => 'We implement all the optimizations recommended by Google PageSpeed.',

    // ttfb
    'ttfb.name'        => 'Server response time (TTFB)',
    'ttfb.display'     => '{{ms}}ms',
    'ttfb.desc.prefix' => 'The server responds in {{ms}}ms. ',
    'ttfb.desc.good'   => 'Good time.',
    'ttfb.desc.bad'    => 'Less than 500ms is recommended.',
    'ttfb.recommend'   => 'Upgrade hosting, enable server cache and optimize database queries.',
    'ttfb.solution'    => 'We recommend optimized hosting and configure server cache.',

    // compression
    'comp.name'          => 'Content compression',
    'comp.display.ok'    => '{{encoding}}',
    'comp.display.none'  => 'No compression',
    'comp.desc.ok'       => 'Content is served with {{encoding}} compression.',
    'comp.desc.none'     => 'Content is not compressed. This increases download time.',
    'comp.recommend'     => 'Enable GZIP or Brotli compression on the server.',
    'comp.solution'      => 'We configure Brotli/GZIP compression to reduce transfer size.',

    // cache_headers
    'cache.name'             => 'Browser cache',
    'cache.display.ok'       => '{{details}}',
    'cache.display.none'     => 'Not configured',
    'cache.desc.ok'          => 'Cache configured: {{details}}. Files are stored for faster loads.',
    'cache.desc.none'        => 'No cache headers or active cache plugin detected. The browser downloads everything each time.',
    'cache.detail.cc'        => 'Cache-Control: {{value}}',
    'cache.detail.etag'      => 'ETag present',
    'cache.detail.expires'   => 'Expires: {{value}}',
    'cache.detail.plugin_h'  => 'Cache plugin active (server headers)',
    'cache.detail.plugin_html' => 'Cache plugin detected in HTML',
    'cache.recommend'        => 'Install a cache plugin (WP Rocket, LiteSpeed Cache) and configure Cache-Control headers.',
    'cache.solution'         => 'We configure aggressive cache for static files with optimized expiration.',
];
