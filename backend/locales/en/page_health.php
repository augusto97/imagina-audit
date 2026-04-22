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
];
