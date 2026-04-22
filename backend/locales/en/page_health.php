<?php
return [
    'summary' => 'Page technical health has a score of {{score}}/100.',

    // status_code
    'status.name'    => 'HTTP status code',
    'status.display' => '{{code}}',
    'status.desc.ok' => 'The page responds with status 200 (OK).',
    'status.desc.bad' => 'The page responds with status {{code}}. A healthy page should return 200.',
    'status.recommend' => 'Verify that the main page returns status 200.',
    'status.solution'  => 'We verify that all pages respond correctly.',

    // mixed_content
    'mixed.name'          => 'HTTP/HTTPS mixed content',
    'mixed.display.na'    => 'N/A (HTTP site)',
    'mixed.display.ok'    => 'Not detected',
    'mixed.display.bad'   => '{{count}} mixed resources',
    'mixed.desc.na'       => 'The site does not use HTTPS, so mixed content verification does not apply.',
    'mixed.desc.ok'       => 'No resources loaded via HTTP on an HTTPS page. Correct.',
    'mixed.desc.bad'      => '{{count}} resources detected loaded via insecure HTTP inside an HTTPS page. This triggers browser security warnings.',
    'mixed.recommend.na'  => 'Migrate the site to HTTPS.',
    'mixed.recommend.bad' => 'Change all resource URLs from http:// to https:// or use protocol-relative URLs.',
    'mixed.solution.na'   => 'We migrate your site to HTTPS and fix mixed content.',
    'mixed.solution'      => 'We fix all mixed content issues.',

    // meta_refresh
    'mrefresh.name'        => 'Meta Refresh',
    'mrefresh.display.ok'  => 'No',
    'mrefresh.display.bad' => 'Detected',
    'mrefresh.desc.ok'     => 'No meta refresh detected. Correct.',
    'mrefresh.desc.bad'    => 'Detected <meta http-equiv="refresh">. This auto-redirects the page and is bad for SEO because search engines do not handle it well.',
    'mrefresh.recommend'   => 'Replace meta refresh with a server-side 301 redirect.',
    'mrefresh.solution'    => 'We configure proper server-side redirects.',

    // charset
    'charset.name'         => 'Character encoding',
    'charset.display.none' => 'Not declared',
    'charset.desc.utf8'    => 'UTF-8 encoding declared correctly.',
    'charset.desc.other'   => 'Encoding declared as "{{charset}}". UTF-8 is recommended.',
    'charset.desc.none'    => 'No character encoding declared. May cause issues with accents and special characters.',
    'charset.recommend'    => 'Add <meta charset="UTF-8"> at the start of <head>.',
    'charset.solution'     => 'We verify character encoding on every page.',

    // frames
    'frames.name'          => 'Frames & Iframes',
    'frames.display.frame' => 'Uses <frame> (obsolete)',
    'frames.display.many'  => '{{count}} iframes',
    'frames.display.none'  => 'No frames used',
    'frames.desc.frame'    => 'The site uses <frame>, an obsolete technology not supported by search engines.',
    'frames.desc.many'     => '{{count}} iframes found. Too many iframes can hurt performance.',
    'frames.desc.some'     => '{{count}} iframes detected. Acceptable amount.',
    'frames.desc.none'     => 'No frames detected. Correct.',
    'frames.recommend'     => 'Remove the use of <frame> and migrate to a modern design.',
    'frames.solution'      => 'We optimize page structure by removing obsolete elements.',

    // duplicate_canonical
    'dupcan.name'           => 'Duplicate canonical',
    'dupcan.display.ok'     => 'Unique',
    'dupcan.display.none'   => 'Not found',
    'dupcan.display.dup'    => '{{count}} canonicals',
    'dupcan.desc.ok'        => 'Exactly one canonical tag found. Correct.',
    'dupcan.desc.none'      => 'No canonical tag found.',
    'dupcan.desc.dup'       => '{{count}} canonical tags found. Only one should exist. Duplicates confuse search engines.',
    'dupcan.recommend'      => 'Remove duplicate canonicals and keep only one.',
    'dupcan.solution'       => 'We verify and fix canonical tags on every page.',

    // doctype
    'doctype.name'         => 'DOCTYPE declaration',
    'doctype.display.ok'   => 'HTML5',
    'doctype.display.none' => 'Not found',
    'doctype.desc.ok'      => 'HTML5 DOCTYPE declared correctly.',
    'doctype.desc.none'    => '<!DOCTYPE html> not found. Without DOCTYPE, browsers fall into "quirks mode" and render inconsistently.',
    'doctype.recommend'    => 'Add <!DOCTYPE html> as the first line of the document.',
    'doctype.solution'     => 'We verify every page has the correct DOCTYPE declaration.',

    // html_errors
    'htmlerr.name'         => 'HTML errors & warnings',
    'htmlerr.display.ok'   => 'No errors detected',
    'htmlerr.display.bad'  => '{{count}} issues',
    'htmlerr.desc.ok'      => 'No major HTML errors detected.',
    'htmlerr.desc.bad'     => 'HTML issues detected: {{list}}.',
    'htmlerr.err.unclosed'   => 'Unclosed <{{tag}}> tag',
    'htmlerr.err.deprecated' => 'Obsolete tag: {{tag}}',
    'htmlerr.err.inline_styles' => '{{count}} inline styles (excessive)',
    'htmlerr.recommend'    => 'Fix the detected HTML errors to improve browser compatibility.',
    'htmlerr.solution'     => 'We fix HTML errors and optimize the code structure.',

    // link_stats
    'links.name'           => 'Link statistics',
    'links.display'        => '{{total}} links ({{internal}} int. · {{external}} ext. · {{extDofollow}} ext. dofollow)',
    'links.desc'           => 'The page has {{total}} links: {{internal}} internal and {{external}} external. {{dofollow}} dofollow and {{nofollow}} nofollow. {{extDofollow}} external dofollow links.',
    'links.recommend'      => 'Reduce the number of links to fewer than 200 to avoid diluting PageRank.',
    'links.solution'       => 'We optimize the internal link structure to improve SEO.',

    // broken_resources
    'broken.name'          => 'Broken resources',
    'broken.display.ok'    => 'None detected',
    'broken.display.bad'   => '{{count}} broken resources',
    'broken.desc.ok'       => '{{checked}} resources checked and none were broken.',
    'broken.desc.bad'      => '{{count}} broken resources found out of {{checked}} checked: {{list}}.',
    'broken.recommend'     => 'Fix or remove broken resources (images or scripts returning 404).',
    'broken.solution'      => 'We identify and fix all broken resources on the site.',

    // text_code_ratio
    'ratio.name'             => 'Text/Code Ratio',
    'ratio.display.none'     => 'No data',
    'ratio.display'          => '{{ratio}}%',
    'ratio.desc.none'        => 'Could not compute the text/code ratio.',
    'ratio.desc.good'        => 'Text/code ratio is {{ratio}}%. Good balance between visible content and HTML code.',
    'ratio.desc.low_prefix'  => 'Text/code ratio is {{ratio}}%. ',
    'ratio.desc.very_low'    => 'Very low — search engines may consider that this page has little relevant content.',
    'ratio.desc.below_rec'   => 'At least 15% is recommended so search engines value the content.',
    'ratio.recommend'        => 'Reduce unnecessary code (inline CSS/JS, redundant HTML) and add more visible text content.',
    'ratio.solution'         => 'We optimize the code by removing bloat and improving the useful-content ratio.',

    // custom_404
    'n404.name'               => 'Custom 404 page',
    'n404.display.ok'         => 'Configured (HTTP 404)',
    'n404.display.soft'       => 'Returns 200 instead of 404',
    'n404.display.other'      => 'Returns {{code}}',
    'n404.desc.ok'            => 'The server returns status 404 for non-existent pages. Correct.',
    'n404.desc.soft'          => 'The server returns 200 for non-existent URLs instead of 404. This is a "soft 404" that confuses Google and wastes crawl budget.',
    'n404.desc.other'         => 'The server returns status {{code}} for non-existent pages.',
    'n404.recommend'          => 'Configure the server to return HTTP 404 on non-existent pages and display a useful page with links.',
    'n404.solution'           => 'We configure custom 404 pages with helpful links.',

    // url_resolution
    'urlres.name'          => 'URL resolution (www/https)',
    'urlres.display.ok'    => 'All redirect correctly',
    'urlres.display.bad'   => 'Inconsistencies detected',
    'urlres.desc.ok'       => 'All domain variants (http/https, www/non-www) correctly redirect to the main URL.',
    'urlres.desc.bad'      => 'Not all domain variants redirect to the same destination. This may cause duplicate content.',
    'urlres.recommend'     => 'Configure 301 redirects so http, https, www and non-www all point to the same URL.',
    'urlres.solution'      => 'We configure the correct redirects to avoid duplicate content.',
];
