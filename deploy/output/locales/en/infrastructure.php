<?php
return [
    'summary' => 'Your infrastructure has a score of {{score}}/100.',

    // server
    'server.name'          => 'Web Server',
    'server.display.unknown' => 'Unknown',
    'server.display.none'  => 'Not detected',
    'server.desc'          => 'Detected web server: {{name}}.',
    'server.solution'      => 'We recommend LiteSpeed or Nginx for maximum WordPress performance.',

    // http_protocol
    'proto.name'         => 'HTTP Protocol',
    'proto.display'      => 'HTTP/{{version}}',
    'proto.desc.modern'  => 'The site uses HTTP/{{version}}. Modern protocol with multiplexing and better performance.',
    'proto.desc.old'     => 'The site uses HTTP/{{version}}. HTTP/2 offers better performance with parallel loading.',
    'proto.recommend'    => 'Enable HTTP/2 on the server to improve load speed.',
    'proto.solution'     => 'We configure HTTP/2 or HTTP/3 for maximum performance.',

    // ttfb (informational in Infrastructure — shared locale key with performance)
    'ttfb.name'          => 'Response time (TTFB)',
    'ttfb.display'       => '{{ms}}ms',
    'ttfb.desc.prefix'   => 'The server responds in {{ms}}ms. ',
    'ttfb.desc.good'     => 'Good response time.',
    'ttfb.desc.bad'      => 'Less than 500ms is recommended.',
    'ttfb.recommend'     => 'Consider a faster hosting or configure server cache.',
    'ttfb.solution'      => 'We migrate your site to optimized hosting with advanced server cache.',

    // cdn
    'cdn.name'           => 'CDN (Content Delivery Network)',
    'cdn.display.ok'     => '{{name}}',
    'cdn.display.none'   => 'Not detected',
    'cdn.detected.cache' => 'CDN (active cache)',
    'cdn.detected.generic' => 'CDN detected',
    'cdn.desc.ok'        => 'CDN detected: {{name}}. Content is served from servers near the user.',
    'cdn.desc.none'      => 'No CDN detected. Content is served from a single server.',
    'cdn.recommend'      => 'Implement a CDN like Cloudflare to improve speed and availability.',
    'cdn.solution'       => 'We configure Cloudflare CDN so your site loads fast worldwide.',

    // compression (local — same shape as performance.comp)
    'comp.name'          => 'Server compression',
    'comp.display.ok'    => '{{encoding}}',
    'comp.display.none'  => 'No compression',
    'comp.desc.ok'       => 'The server uses {{encoding}} compression. Reduces transfer size.',
    'comp.desc.none'     => 'No GZIP or Brotli compression detected. Files are transferred uncompressed.',
    'comp.recommend'     => 'Enable GZIP or Brotli compression in server configuration.',
    'comp.solution'      => 'We configure Brotli/GZIP compression for maximum efficiency.',

    // php_exposed
    'php.name'           => 'Exposed PHP Version',
    'php.display.ok'     => 'Hidden',
    'php.display.exposed' => '{{value}}',
    'php.desc.ok'        => 'PHP version is hidden. Good security practice.',
    'php.desc.exposed'   => 'PHP version is exposed: {{value}}. Makes it easier for attackers to find vulnerabilities.',
    'php.recommend'      => 'Hide the X-Powered-By header in PHP configuration.',
    'php.solution'       => 'We hide all server information that could be used by attackers.',

    // hosting
    'host.name'          => 'Hosting / IP',
    'host.display'       => '{{provider}} ({{ip}})',
    'host.provider.unknown' => 'Not identified',
    'host.ip.unresolved' => 'Not resolved',
    'host.desc'          => 'Server IP: {{ip}}. Detected provider: {{provider}}.',
    'host.solution'      => 'We evaluate your hosting and recommend the best option for WordPress.',
];
