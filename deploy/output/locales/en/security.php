<?php
return [
    // Module summary
    'summary' => 'Your site has a security score of {{score}}/100.',

    // ssl_valid
    'ssl.name'             => 'SSL Certificate',
    'ssl.display.invalid'  => 'Invalid or not present',
    'ssl.display.valid'    => 'Valid until {{validTo}} ({{days}} days)',
    'ssl.desc.invalid'     => 'The site has no valid SSL certificate. Visitors will see security warnings.',
    'ssl.desc.valid'       => 'Valid SSL certificate issued by {{issuer}}. Expires on {{validTo}}.',
    'ssl.desc.expiring'    => 'SSL certificate expiring soon ({{days}} days). Issued by {{issuer}}.',
    'ssl.recommend.install' => 'Install an SSL certificate (Let\'s Encrypt is free).',
    'ssl.recommend.renew'   => 'Renew the SSL certificate before it expires.',
    'ssl.solution'         => 'We install and configure free SSL with Let\'s Encrypt on your hosting. We monitor expiration and renew it automatically.',

    // https_redirect
    'redirect.name'            => 'HTTP → HTTPS redirect',
    'redirect.display.ok'      => 'Correctly configured',
    'redirect.display.missing' => 'Not configured',
    'redirect.desc.ok'         => 'HTTP correctly redirects to HTTPS.',
    'redirect.desc.missing'    => 'HTTP does not redirect to HTTPS. Visitors could access the insecure version.',
    'redirect.recommend'       => 'Configure a 301 redirect from HTTP to HTTPS.',
    'redirect.solution'        => 'We configure the HTTPS redirect and force secure connections.',

    // hsts_preload
    'hsts.name'             => 'HSTS Preload',
    'hsts.display.ready'    => 'Ready for preload',
    'hsts.display.partial'  => 'HSTS without preload',
    'hsts.display.none'     => 'No HSTS',
    'hsts.desc.ready'       => 'HSTS fully configured with preload, includeSubDomains and max-age >= 1 year. Ready to submit to hstspreload.org.',
    'hsts.desc.partial'     => 'HSTS present but missing preload / includeSubDomains / sufficient max-age to qualify for the Chrome preload list.',
    'hsts.desc.none'        => 'No HSTS. Configure it to force HTTPS and be eligible for the preload list.',
    'hsts.recommend'        => 'Configure: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload. Then register at hstspreload.org',
    'hsts.solution'         => 'We configure HSTS with preload for maximum HTTPS protection.',

    // weak_tls
    'tls.name'          => 'Weak TLS versions',
    'tls.display.ok'    => 'TLS 1.2+ only',
    'tls.display.weak'  => '{{list}} enabled',
    'tls.desc.ok'       => 'The server only accepts TLS 1.2 and higher. Correct.',
    'tls.desc.weak'     => 'The server accepts weak TLS versions: {{list}}. They are vulnerable to attacks like POODLE and BEAST.',
    'tls.recommend'     => 'Disable TLS 1.0 and 1.1 in the server configuration. Enable only TLS 1.2 and TLS 1.3.',
    'tls.solution'      => 'We configure the server to accept only modern TLS versions.',

    // dnssec
    'dnssec.name'               => 'DNSSEC',
    'dnssec.display.enabled'    => 'Enabled',
    'dnssec.display.disabled'   => 'Not enabled',
    'dnssec.display.invalid'    => 'N/A',
    'dnssec.desc.enabled'       => 'DNSSEC enabled. Protects against DNS cache poisoning and malicious redirects.',
    'dnssec.desc.disabled'      => 'DNSSEC not enabled. Without DNS signing, the domain\'s DNS records can be spoofed.',
    'dnssec.desc.invalid'       => 'Invalid domain.',
    'dnssec.recommend'          => 'Enable DNSSEC at your registrar or DNS provider (Cloudflare does it in 1 click).',
    'dnssec.solution'           => 'We configure DNSSEC to protect against DNS attacks.',

    // source_code_exposure
    'source.name'            => 'Source code exposure',
    'source.display.safe'    => 'Protected',
    'source.display.exposed' => '{{count}} files exposed',
    'source.desc.safe'       => 'No exposed version-control files detected (.git, .svn). Correct.',
    'source.desc.exposed'    => 'CRITICAL: Version-control files accessible: {{list}}. An attacker can download all source code including credentials.',
    'source.recommend'       => 'Block access to /.git/, /.svn/, etc. in .htaccess or remove those directories from the web server.',
    'source.solution'        => 'We protect against source code and system file leaks.',

    // security_headers (bundle)
    'headers.name'           => 'Security headers',
    'headers.display'        => '{{present}}/7 headers configured',
    'headers.desc.none'      => 'Critical: no security headers are present. The site is highly exposed to common web attacks.',
    'headers.desc.partial'   => 'Some security headers are missing: {{missing}}.',
    'headers.desc.ok'        => 'All modern security headers are configured correctly.',
    'headers.recommend'      => 'Configure the missing security headers in the server or from WordPress. The most critical ones: {{list}}.',
    'headers.solution'       => 'We configure all modern security headers in your hosting.',

    // exposed_headers
    'exposed.name'            => 'Exposed headers',
    'exposed.display.ok'      => 'Hidden',
    'exposed.display.exposed' => '{{list}} visible',
    'exposed.desc.ok'         => 'No sensitive headers exposed.',
    'exposed.desc.exposed'    => 'These headers leak server information: {{list}}. They help attackers identify vulnerabilities.',
    'exposed.recommend'       => 'Remove or override the Server and X-Powered-By headers in the server configuration.',
    'exposed.solution'        => 'We hide server version information to reduce the attack surface.',

    // sri
    'sri.name'            => 'Subresource Integrity (SRI)',
    'sri.display'         => '{{withSri}}/{{total}} external scripts with SRI',
    'sri.desc.ok'         => 'External scripts without integrity hashes: {{count}}. SRI protects against compromised CDN scripts.',
    'sri.desc.none'       => 'No external scripts detected or all have SRI. Correct.',
    'sri.recommend'       => 'Add integrity="sha384-..." to the <script> tags that load from external CDNs.',
    'sri.solution'        => 'We enable SRI on external scripts to protect against CDN supply-chain attacks.',

    // exposed_email
    'email.name'            => 'Email exposed on site',
    'email.display.ok'      => 'Protected',
    'email.display.exposed' => '{{count}} emails visible',
    'email.desc.ok'         => 'No emails exposed in the HTML.',
    'email.desc.exposed'    => '{{count}} emails were found in the public HTML: {{list}}. Spammers bots harvest these.',
    'email.recommend'       => 'Replace visible emails with contact forms or protect them with JavaScript obfuscation.',
    'email.solution'        => 'We protect visible emails with anti-spam obfuscation.',

    // dmarc
    'dmarc.name'         => 'DMARC',
    'dmarc.display.ok'   => 'Configured ({{policy}})',
    'dmarc.display.none' => 'Not configured',
    'dmarc.desc.ok'      => 'DMARC policy {{policy}}. Protects the domain against email spoofing.',
    'dmarc.desc.none'    => 'No DMARC record. Attackers can send emails spoofing your domain.',
    'dmarc.recommend'    => 'Add a DNS TXT record for _dmarc with v=DMARC1; p=reject; rua=mailto:...',
    'dmarc.solution'     => 'We configure DMARC to protect against domain spoofing.',

    // spf
    'spf.name'         => 'SPF',
    'spf.display.ok'   => 'Configured',
    'spf.display.none' => 'Not configured',
    'spf.desc.ok'      => 'SPF record configured. Authorizes senders on behalf of the domain.',
    'spf.desc.none'    => 'No SPF record. Emails from the domain may be marked as spam.',
    'spf.recommend'    => 'Add a DNS TXT record with v=spf1 include:tu-proveedor-email.com ~all',
    'spf.solution'     => 'We configure SPF and DKIM for optimal email deliverability.',

    // safe_browsing
    'sb.name'         => 'Google Safe Browsing',
    'sb.display.ok'   => 'Clean',
    'sb.display.bad'  => 'REPORTED as unsafe',
    'sb.display.na'   => 'Not verifiable',
    'sb.desc.ok'      => 'Google Safe Browsing reports no threats for this domain.',
    'sb.desc.bad'     => 'Google Safe Browsing has reported this site as unsafe (malware / phishing / deceptive content). Browsers block it.',
    'sb.desc.na'      => 'Could not check Safe Browsing (API not configured).',
    'sb.recommend'    => 'Clean up malware / phishing content and request review at Google Search Console.',
    'sb.solution'     => 'We monitor and clean up malware, then request the de-list from Google.',

    // directory_listing
    'dir.name'            => 'Directory listing',
    'dir.display.ok'      => 'Protected',
    'dir.display.exposed' => '{{count}} directories exposed',
    'dir.desc.ok'         => 'No open directory listing found.',
    'dir.desc.exposed'    => 'Directory listing enabled on: {{list}}. Attackers can browse and download files directly.',
    'dir.recommend'       => 'Disable directory indexing with Options -Indexes in .htaccess.',
    'dir.solution'        => 'We disable directory indexing to prevent information disclosure.',

    // wp_info_files
    'wpinfo.name'            => 'Exposed WordPress info files',
    'wpinfo.display.ok'      => 'Protected',
    'wpinfo.display.exposed' => '{{count}} files exposed',
    'wpinfo.desc.ok'         => 'WordPress info files are protected or not accessible.',
    'wpinfo.desc.exposed'    => 'These files leak WordPress structure info: {{list}}.',
    'wpinfo.recommend'       => 'Remove or protect readme.html, license.txt and similar files.',
    'wpinfo.solution'        => 'We remove unnecessary info files and protect the WP installation.',

    // wp_install_files
    'wpinstall.name'            => 'Exposed WordPress install/debug files',
    'wpinstall.display.ok'      => 'Protected',
    'wpinstall.display.exposed' => '{{count}} files exposed',
    'wpinstall.desc.ok'         => 'No install or debug files accessible.',
    'wpinstall.desc.exposed'    => 'CRITICAL: exposed files: {{list}}. Could allow installation hijack or info disclosure.',
    'wpinstall.recommend'       => 'Delete install.php, upgrade.php and debug.log from the web root. Protect wp-admin/install.php in .htaccess.',
    'wpinstall.solution'        => 'We secure these entry points and remove debug files.',

    // php_in_uploads
    'phpup.name'            => 'PHP in /wp-content/uploads/',
    'phpup.display.ok'      => 'Blocked',
    'phpup.display.exposed' => 'PHP executable',
    'phpup.desc.ok'         => 'PHP execution in uploads/ is blocked. Correct.',
    'phpup.desc.exposed'    => 'CRITICAL: PHP executes in wp-content/uploads/. Attackers who upload a malicious file can run code.',
    'phpup.recommend'       => 'Add a .htaccess in wp-content/uploads/ with <FilesMatch "\\.php$"> Deny from all </FilesMatch>.',
    'phpup.solution'        => 'We block PHP execution in uploads/ with hardening at the hosting level.',

    // rest_api_enum_extra (besides wp/v2/users checked by WordPressDetector)
    'restextra.name'            => 'Extra REST API enumeration',
    'restextra.display.ok'      => 'Protected',
    'restextra.display.exposed' => '{{count}} endpoints exposed',
    'restextra.desc.ok'         => 'Sensitive REST API endpoints are protected.',
    'restextra.desc.exposed'    => 'Sensitive endpoints accessible: {{list}}. Allow enumeration of users / sensitive data.',
    'restextra.recommend'       => 'Restrict access to additional sensitive REST endpoints.',
    'restextra.solution'        => 'We restrict REST API endpoints to authenticated users only.',

    // default_admin_user
    'admin.name'         => 'Default "admin" user',
    'admin.display.ok'   => 'Not detected',
    'admin.display.bad'  => 'Detected / probable',
    'admin.desc.ok'      => 'No default username \'admin\' detected.',
    'admin.desc.bad'     => 'A username \'admin\' was detected (or similar). Very targeted by brute-force attacks.',
    'admin.recommend'    => 'Rename the user to a non-obvious username and use strong passwords.',
    'admin.solution'     => 'We rename default users and configure brute-force protection.',

    // security_plugin
    'splugin.name'         => 'Security plugin',
    'splugin.display.ok'   => 'Detected: {{name}}',
    'splugin.display.none' => 'Not detected',
    'splugin.desc.ok'      => 'Security plugin installed: {{name}}.',
    'splugin.desc.none'    => 'No security plugin detected. The site is exposed to common attacks.',
    'splugin.recommend'    => 'Install and configure a reputable security plugin (Wordfence, Sucuri, iThemes Security).',
    'splugin.solution'     => 'We install and configure a professional security plugin with active monitoring.',

    // core_vulnerabilities
    'cve_core.name'            => 'WordPress core CVEs',
    'cve_core.display.ok'      => 'No known CVEs',
    'cve_core.display.exposed' => '{{count}} CVEs ({{worst}})',
    'cve_core.desc.ok'         => 'No known CVEs affecting this WordPress version.',
    'cve_core.desc.exposed'    => 'WordPress {{version}} is affected by {{count}} known CVEs. Worst severity: {{worst}}.',
    'cve_core.recommend'       => 'Update WordPress immediately to the latest stable version.',
    'cve_core.solution'        => 'We keep the WP core up to date with automatic testing and patching.',

    // plugin_vulnerabilities
    'cve_plugins.name'            => 'Vulnerable plugins',
    'cve_plugins.display.ok'      => 'No CVEs detected',
    'cve_plugins.display.exposed' => '{{count}} plugins with CVE',
    'cve_plugins.desc.ok'         => 'No plugins with known active CVEs.',
    'cve_plugins.desc.exposed'    => '{{count}} plugins affected by known CVEs: {{list}}.',
    'cve_plugins.recommend'       => 'Update the affected plugins immediately. If no fix is available, replace them with a secure alternative.',
    'cve_plugins.solution'        => 'We update vulnerable plugins weekly and replace those that no longer receive updates.',

    // theme_vulnerabilities
    'cve_theme.name'            => 'Theme vulnerabilities',
    'cve_theme.display.ok'      => 'No CVEs detected',
    'cve_theme.display.exposed' => '{{count}} CVEs in theme',
    'cve_theme.desc.ok'         => 'No known CVEs in the active theme.',
    'cve_theme.desc.exposed'    => 'The theme {{theme}} is affected by {{count}} known CVEs.',
    'cve_theme.recommend'       => 'Update the theme or replace it with one that is actively maintained.',
    'cve_theme.solution'        => 'We keep your theme secure and migrate to a safe alternative if it\'s abandoned.',
];
