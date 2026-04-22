<?php
/**
 * Verificaciones de seguridad específicas de WordPress (no vulnerabilidades).
 *
 * Sub-checker de SecurityAnalyzer. Aplica solo si el sitio es WordPress.
 */

class SecurityWpChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    public function checkDirectoryListing(): array {
        $dirs = ['/wp-content/uploads/', '/wp-content/plugins/'];
        // PARALELIZADO
        $urls = [];
        foreach ($dirs as $dir) $urls[$dir] = $this->url . $dir;
        $responses = Fetcher::multiGet($urls, 5);
        $listings = [];
        foreach ($dirs as $dir) {
            $resp = $responses[$dir] ?? null;
            if ($resp && ($resp['statusCode'] ?? 0) === 200 && str_contains($resp['body'] ?? '', 'Index of')) {
                $listings[] = $dir;
            }
        }

        $score = empty($listings) ? 100 : max(0, 100 - (count($listings) * 30));

        $listingCount = count($listings);
        return Scoring::createMetric(
            'directory_listing',
            Translator::t('security.dir.name'),
            $listingCount,
            $listingCount > 0 ? Translator::t('security.dir.display.exposed', ['count' => $listingCount]) : Translator::t('security.dir.display.ok'),
            $score,
            $listingCount > 0
                ? Translator::t('security.dir.desc.exposed', ['list' => implode(', ', $listings)])
                : Translator::t('security.dir.desc.ok'),
            $listingCount > 0 ? Translator::t('security.dir.recommend') : '',
            Translator::t('security.dir.solution')
        );
    }

    public function checkWpInfoFiles(): array {
        $files = ['/readme.html', '/license.txt', '/wp-config-sample.php'];
        // PARALELIZADO: 3 HEAD paralelas en ~1s vs ~9s secuencial
        $urls = [];
        foreach ($files as $f) $urls[$f] = $this->url . $f;
        $responses = Fetcher::multiGet($urls, 3);
        $exposed = [];
        foreach ($files as $f) {
            if (($responses[$f]['statusCode'] ?? 0) === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_info_files',
            Translator::t('security.wpinfo.name'),
            $count,
            $count === 0 ? Translator::t('security.wpinfo.display.ok') : Translator::t('security.wpinfo.display.exposed', ['count' => $count]),
            $count === 0 ? 100 : 50,
            $count === 0
                ? Translator::t('security.wpinfo.desc.ok')
                : Translator::t('security.wpinfo.desc.exposed', ['list' => implode(', ', $exposed)]),
            $count > 0 ? Translator::t('security.wpinfo.recommend') : '',
            Translator::t('security.wpinfo.solution'),
            ['files' => $exposed]
        );
    }

    public function checkWpInstallFiles(): array {
        $files = ['/wp-admin/install.php', '/wp-admin/upgrade.php', '/wp-admin/install-helper.php'];
        // PARALELIZADO: 3 HEAD paralelas
        $urls = [];
        foreach ($files as $f) $urls[$f] = $this->url . $f;
        $responses = Fetcher::multiGet($urls, 3);
        $exposed = [];
        foreach ($files as $f) {
            if (($responses[$f]['statusCode'] ?? 0) === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_install_files',
            Translator::t('security.wpinstall.name'),
            $count,
            $count === 0 ? Translator::t('security.wpinstall.display.ok') : Translator::t('security.wpinstall.display.exposed', ['count' => $count]),
            $count === 0 ? 100 : 30,
            $count === 0
                ? Translator::t('security.wpinstall.desc.ok')
                : Translator::t('security.wpinstall.desc.exposed', ['list' => implode(', ', $exposed)]),
            $count > 0 ? Translator::t('security.wpinstall.recommend') : '',
            Translator::t('security.wpinstall.solution'),
            ['files' => $exposed]
        );
    }

    public function checkPhpInUploads(): array {
        $response = Fetcher::get($this->url . '/wp-content/uploads/', 5, true, 0);
        $foundPhp = [];

        if ($response['statusCode'] === 200 && str_contains($response['body'], 'Index of')) {
            preg_match_all('/href=["\']([^"\']+\.php)["\']/i', $response['body'], $matches);
            foreach ($matches[1] ?? [] as $phpFile) {
                if (!str_starts_with($phpFile, '?')) $foundPhp[] = $phpFile;
            }
        }

        $count = count($foundPhp);
        return Scoring::createMetric(
            'php_in_uploads',
            Translator::t('security.phpup.name'),
            $count,
            $count === 0 ? Translator::t('security.phpup.display.ok') : Translator::t('security.phpup.display.exposed'),
            $count === 0 ? 100 : 0,
            $count === 0 ? Translator::t('security.phpup.desc.ok') : Translator::t('security.phpup.desc.exposed'),
            $count > 0 ? Translator::t('security.phpup.recommend') : '',
            Translator::t('security.phpup.solution'),
            ['phpFiles' => array_slice($foundPhp, 0, 10)]
        );
    }

    public function checkRestApiEnumerationExtra(): array {
        $endpoints = [
            '/wp-json/wp/v2/users',
            '/wp-json/wp/v2/pages',
            '/wp-json/wp/v2/posts',
            '/wp-json/wp/v2/media',
        ];
        // PARALELIZADO: 4 GET en paralelo ~3s vs 12s secuencial
        $urls = [];
        foreach ($endpoints as $ep) $urls[$ep] = $this->url . $ep;
        $responses = Fetcher::multiGet($urls, 3);
        $exposed = [];
        foreach ($endpoints as $ep) {
            $resp = $responses[$ep] ?? null;
            if ($resp && ($resp['statusCode'] ?? 0) === 200) {
                $data = json_decode($resp['body'] ?? '', true);
                if (is_array($data) && !empty($data)) {
                    $exposed[] = ['endpoint' => $ep, 'count' => count($data)];
                }
            }
        }

        $count = count($exposed);
        $exposedList = implode(', ', array_map(fn($e) => $e['endpoint'], $exposed));
        return Scoring::createMetric(
            'rest_api_enumeration',
            Translator::t('security.restextra.name'),
            $count,
            $count === 0 ? Translator::t('security.restextra.display.ok') : Translator::t('security.restextra.display.exposed', ['count' => $count]),
            $count === 0 ? 100 : ($count >= 3 ? 30 : 50),
            $count === 0
                ? Translator::t('security.restextra.desc.ok')
                : Translator::t('security.restextra.desc.exposed', ['list' => $exposedList]),
            $count > 0 ? Translator::t('security.restextra.recommend') : '',
            Translator::t('security.restextra.solution'),
            ['endpoints' => $exposed]
        );
    }

    public function checkDefaultAdminUser(): array {
        $defaultUsers = ['admin', 'administrator', 'test', 'demo', 'user', 'wordpress'];
        $detectedUsers = [];

        $response = Fetcher::get($this->url . '/wp-json/wp/v2/users', 3, true, 0);
        if ($response['statusCode'] === 200) {
            $data = json_decode($response['body'], true);
            if (is_array($data)) {
                foreach ($data as $u) {
                    $slug = strtolower($u['slug'] ?? '');
                    if (in_array($slug, $defaultUsers)) {
                        $detectedUsers[] = $slug;
                    }
                }
            }
        }

        if (empty($detectedUsers)) {
            // PARALELIZADO: 3 GET en paralelo ~1-3s vs 9s secuencial
            $authorUrls = [];
            for ($i = 1; $i <= 3; $i++) $authorUrls["a$i"] = $this->url . '/?author=' . $i;
            $authorResponses = Fetcher::multiGet($authorUrls, 3);
            foreach ($authorResponses as $resp) {
                if (in_array($resp['statusCode'] ?? 0, [301, 302], true)) {
                    $location = $resp['headers']['location'] ?? '';
                    if (preg_match('#/author/([^/]+)/?#i', $location, $m)) {
                        $slug = strtolower($m[1]);
                        if (in_array($slug, $defaultUsers)) {
                            $detectedUsers[] = $slug;
                        }
                    }
                }
            }
        }

        $count = count(array_unique($detectedUsers));
        return Scoring::createMetric(
            'default_admin_user',
            Translator::t('security.admin.name'),
            $count,
            $count === 0 ? Translator::t('security.admin.display.ok') : Translator::t('security.admin.display.bad'),
            $count === 0 ? 100 : 20,
            $count === 0 ? Translator::t('security.admin.desc.ok') : Translator::t('security.admin.desc.bad'),
            $count > 0 ? Translator::t('security.admin.recommend') : '',
            Translator::t('security.admin.solution'),
            ['detected' => array_values(array_unique($detectedUsers))]
        );
    }

    public function checkSecurityPlugin(): array {
        // Un plugin de seguridad se puede detectar por 4 señales:
        //   1. Slug en /wp-content/plugins/ del HTML (fácil, pero muchos plugins
        //      de seguridad no cargan assets en el frontend).
        //   2. Cookies set-cookie típicas (Wordfence, iThemes).
        //   3. Headers HTTP específicos (Sucuri, Wordfence, Jetpack, Cloudflare).
        //   4. HEAD request a /wp-content/plugins/<slug>/readme.txt (más caro pero
        //      detecta plugins silenciosos como Wordfence que suelen bloquearlo o
        //      devolverlo con 200).
        $plugins = [
            'wordfence'              => ['name' => 'Wordfence',                  'cookie' => 'wfwaf-authcookie|wordfence_verifiedHuman', 'header' => 'x-wf-'],
            'sucuri-scanner'         => ['name' => 'Sucuri Security',            'cookie' => null, 'header' => 'x-sucuri-'],
            'ithemes-security'       => ['name' => 'Solid Security (iThemes)',   'cookie' => 'ithemes-security|itsec-',                 'header' => null],
            'better-wp-security'     => ['name' => 'Solid Security (iThemes)',   'cookie' => null, 'header' => null],
            'all-in-one-wp-security' => ['name' => 'All In One WP Security',     'cookie' => 'aiowps_',                                  'header' => null],
            'really-simple-ssl'      => ['name' => 'Really Simple SSL',          'cookie' => null, 'header' => null],
            'defender-security'      => ['name' => 'Defender Security (WPMUdev)', 'cookie' => null, 'header' => null],
            'shield-security'        => ['name' => 'Shield Security',            'cookie' => 'icwp-wpsf-',                               'header' => null],
            'malcare-security'       => ['name' => 'MalCare',                    'cookie' => null, 'header' => null],
            'jetpack'                => ['name' => 'Jetpack (incluye security)', 'cookie' => null, 'header' => null],
            'wp-cerber'              => ['name' => 'WP Cerber',                  'cookie' => 'cerber_groove',                            'header' => null],
            'limit-login-attempts-reloaded' => ['name' => 'Limit Login Attempts Reloaded', 'cookie' => null, 'header' => null],
        ];

        $detected = [];
        $signals = [];  // Por qué señal se detectó cada uno

        // 1. HTML: slug exacto en /wp-content/plugins/<slug>/
        foreach ($plugins as $slug => $info) {
            if (str_contains($this->html, "/wp-content/plugins/$slug/")) {
                $detected[$info['name']] = true;
                $signals[$info['name']] = 'asset en HTML';
            }
        }

        // 2. Cookies: Set-Cookie o Cookie headers del fetch inicial
        $cookieHeader = '';
        if (isset($this->headers['set-cookie'])) {
            $cookieHeader = is_array($this->headers['set-cookie'])
                ? implode('; ', $this->headers['set-cookie'])
                : (string) $this->headers['set-cookie'];
        }
        foreach ($plugins as $slug => $info) {
            if ($info['cookie'] === null || isset($detected[$info['name']])) continue;
            foreach (explode('|', $info['cookie']) as $token) {
                if (stripos($cookieHeader, $token) !== false) {
                    $detected[$info['name']] = true;
                    $signals[$info['name']] = 'cookie';
                    break;
                }
            }
        }

        // 3. Headers HTTP: algunos security plugins/WAFs dejan fingerprint
        foreach ($plugins as $slug => $info) {
            if ($info['header'] === null || isset($detected[$info['name']])) continue;
            foreach ($this->headers as $hName => $hVal) {
                if (stripos($hName, $info['header']) !== false) {
                    $detected[$info['name']] = true;
                    $signals[$info['name']] = 'header';
                    break;
                }
            }
        }

        // 4. Probe directo a readme.txt de los principales (sin bloquear mucho
        //    el audit). Solo para los 4 más comunes que suelen ser invisibles
        //    en el HTML público.
        $silentSlugs = ['wordfence', 'sucuri-scanner', 'ithemes-security', 'better-wp-security', 'all-in-one-wp-security', 'shield-security'];
        $probeUrls = [];
        foreach ($silentSlugs as $slug) {
            $name = $plugins[$slug]['name'];
            if (isset($detected[$name])) continue;
            $probeUrls[$slug] = $this->url . "/wp-content/plugins/$slug/readme.txt";
        }
        if (!empty($probeUrls)) {
            $responses = Fetcher::multiGet($probeUrls, 3);
            foreach ($probeUrls as $slug => $_) {
                $resp = $responses[$slug] ?? null;
                if (!$resp) continue;
                $sc = (int) ($resp['statusCode'] ?? 0);
                $body = (string) ($resp['body'] ?? '');
                // 200 con contenido de readme.txt = instalado y accesible
                if ($sc === 200 && stripos($body, '=== ') !== false) {
                    $name = $plugins[$slug]['name'];
                    $detected[$name] = true;
                    $signals[$name] = 'readme accesible';
                }
                // 403 Forbidden protegido por el propio plugin = también instalado
                elseif ($sc === 403) {
                    $name = $plugins[$slug]['name'];
                    $detected[$name] = true;
                    $signals[$name] = 'readme bloqueado por plugin';
                }
            }
        }

        $detectedList = array_keys($detected);
        $count = count($detectedList);
        $detectedName = $count > 0 ? implode(', ', $detectedList) : '';

        return Scoring::createMetric(
            'security_plugin',
            Translator::t('security.splugin.name'),
            $count,
            $count === 0 ? Translator::t('security.splugin.display.none') : Translator::t('security.splugin.display.ok', ['name' => $detectedName]),
            $count > 0 ? 100 : 60,
            $count > 0
                ? Translator::t('security.splugin.desc.ok', ['name' => $detectedName])
                : Translator::t('security.splugin.desc.none'),
            $count === 0 ? Translator::t('security.splugin.recommend') : '',
            Translator::t('security.splugin.solution'),
            ['detected' => $detectedList, 'signals' => $signals]
        );
    }
}
