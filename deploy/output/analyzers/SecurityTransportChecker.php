<?php
/**
 * Verificaciones de transporte (SSL/TLS/DNSSEC/redirects).
 *
 * Sub-checker de SecurityAnalyzer. Aislado para mantener cada responsabilidad
 * en un archivo manejable.
 */

class SecurityTransportChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    public function checkSsl(): array {
        $ssl = Fetcher::getSslInfo($this->host);

        if (!$ssl['valid']) {
            return Scoring::createMetric(
                'ssl_valid',
                Translator::t('security.ssl.name'),
                false,
                Translator::t('security.ssl.display.invalid'),
                0,
                Translator::t('security.ssl.desc.invalid'),
                Translator::t('security.ssl.recommend.install'),
                Translator::t('security.ssl.solution')
            );
        }

        $days = $ssl['daysUntilExpiry'];
        $score = $days > 30 ? 100 : ($days > 7 ? 60 : 20);

        return Scoring::createMetric(
            'ssl_valid',
            Translator::t('security.ssl.name'),
            true,
            Translator::t('security.ssl.display.valid', ['validTo' => $ssl['validTo'], 'days' => $days]),
            $score,
            $days > 30
                ? Translator::t('security.ssl.desc.valid', ['issuer' => $ssl['issuer'], 'validTo' => $ssl['validTo']])
                : Translator::t('security.ssl.desc.expiring', ['days' => $days, 'issuer' => $ssl['issuer']]),
            $days <= 30 ? Translator::t('security.ssl.recommend.renew') : '',
            Translator::t('security.ssl.solution'),
            ['issuer' => $ssl['issuer'], 'validFrom' => $ssl['validFrom'], 'validTo' => $ssl['validTo']]
        );
    }

    public function checkHttpsRedirect(): array {
        $httpUrl = preg_replace('#^https://#', 'http://', $this->url);
        $response = Fetcher::get($httpUrl, 5, false, 0);
        $redirectsToHttps = false;

        if (in_array($response['statusCode'], [301, 302, 307, 308])) {
            $location = $response['headers']['location'] ?? '';
            if (str_starts_with($location, 'https://')) {
                $redirectsToHttps = true;
            }
        }

        return Scoring::createMetric(
            'https_redirect',
            Translator::t('security.redirect.name'),
            $redirectsToHttps,
            $redirectsToHttps ? Translator::t('security.redirect.display.ok') : Translator::t('security.redirect.display.missing'),
            $redirectsToHttps ? 100 : 30,
            $redirectsToHttps ? Translator::t('security.redirect.desc.ok') : Translator::t('security.redirect.desc.missing'),
            $redirectsToHttps ? '' : Translator::t('security.redirect.recommend'),
            Translator::t('security.redirect.solution')
        );
    }

    public function checkHstsPreload(): array {
        $hsts = $this->headers['strict-transport-security'] ?? '';
        $hasHsts = !empty($hsts);
        $hasPreload = stripos($hsts, 'preload') !== false;
        $hasIncludeSubDomains = stripos($hsts, 'includesubdomains') !== false;

        $maxAge = 0;
        if (preg_match('/max-age=(\d+)/i', $hsts, $m)) {
            $maxAge = (int)$m[1];
        }

        $preloadReady = $hasHsts && $hasPreload && $hasIncludeSubDomains && $maxAge >= 31536000;

        return Scoring::createMetric(
            'hsts_preload',
            Translator::t('security.hsts.name'),
            $preloadReady ? 'ready' : ($hasHsts ? 'partial' : 'none'),
            $preloadReady
                ? Translator::t('security.hsts.display.ready')
                : ($hasHsts ? Translator::t('security.hsts.display.partial') : Translator::t('security.hsts.display.none')),
            $preloadReady ? 100 : ($hasHsts ? 70 : 40),
            $preloadReady
                ? Translator::t('security.hsts.desc.ready')
                : ($hasHsts ? Translator::t('security.hsts.desc.partial') : Translator::t('security.hsts.desc.none')),
            !$preloadReady ? Translator::t('security.hsts.recommend') : '',
            Translator::t('security.hsts.solution'),
            ['value' => $hsts, 'maxAge' => $maxAge, 'hasPreload' => $hasPreload, 'hasIncludeSubDomains' => $hasIncludeSubDomains]
        );
    }

    public function checkWeakTlsVersions(): array {
        $weakFound = [];
        $contexts = [
            'TLS 1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT ?? 32,
            'TLS 1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT ?? 128,
        ];

        foreach ($contexts as $version => $method) {
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'crypto_method' => $method,
                ],
            ]);
            $errno = 0; $errstr = '';
            $sock = @stream_socket_client("ssl://{$this->host}:443", $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $ctx);
            if ($sock) {
                $weakFound[] = $version;
                fclose($sock);
            }
        }

        $count = count($weakFound);
        $weakList = implode(', ', $weakFound);
        return Scoring::createMetric(
            'weak_tls',
            Translator::t('security.tls.name'),
            $count,
            $count === 0 ? Translator::t('security.tls.display.ok') : Translator::t('security.tls.display.weak', ['list' => $weakList]),
            $count === 0 ? 100 : ($count === 1 ? 50 : 20),
            $count === 0
                ? Translator::t('security.tls.desc.ok')
                : Translator::t('security.tls.desc.weak', ['list' => $weakList]),
            $count > 0 ? Translator::t('security.tls.recommend') : '',
            Translator::t('security.tls.solution'),
            ['weakVersions' => $weakFound]
        );
    }

    public function checkDnssec(): array {
        $parts = explode('.', $this->host);
        if (count($parts) < 2) {
            return Scoring::createMetric(
                'dnssec',
                Translator::t('security.dnssec.name'),
                null,
                Translator::t('security.dnssec.display.invalid'),
                null,
                Translator::t('security.dnssec.desc.invalid'),
                '',
                ''
            );
        }
        $domain = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $this->host;

        $records = @dns_get_record($domain, DNS_ANY);
        $hasDnssec = false;
        if ($records) {
            foreach ($records as $r) {
                if (isset($r['type']) && in_array($r['type'], ['DS', 'DNSKEY', 'RRSIG'])) {
                    $hasDnssec = true;
                    break;
                }
            }
        }

        return Scoring::createMetric(
            'dnssec',
            Translator::t('security.dnssec.name'),
            $hasDnssec,
            $hasDnssec ? Translator::t('security.dnssec.display.enabled') : Translator::t('security.dnssec.display.disabled'),
            $hasDnssec ? 100 : 60,
            $hasDnssec ? Translator::t('security.dnssec.desc.enabled') : Translator::t('security.dnssec.desc.disabled'),
            !$hasDnssec ? Translator::t('security.dnssec.recommend') : '',
            Translator::t('security.dnssec.solution')
        );
    }

    public function checkSourceCodeExposure(): array {
        $paths = ['/.git/config', '/.git/HEAD', '/.svn/entries', '/.hg/hgrc', '/.DS_Store'];
        // PARALELIZADO: 5 HEAD en paralelo ~1s vs 15s secuencial
        $urls = [];
        foreach ($paths as $p) $urls[$p] = $this->url . $p;
        $responses = Fetcher::multiGet($urls, 3);
        $found = [];
        foreach ($paths as $p) {
            if (($responses[$p]['statusCode'] ?? 0) === 200) $found[] = $p;
        }
        $count = count($found);
        return Scoring::createMetric(
            'source_code_exposure',
            Translator::t('security.source.name'),
            $count,
            $count === 0 ? Translator::t('security.source.display.safe') : Translator::t('security.source.display.exposed', ['count' => $count]),
            $count === 0 ? 100 : 0,
            $count === 0
                ? Translator::t('security.source.desc.safe')
                : Translator::t('security.source.desc.exposed', ['list' => implode(', ', $found)]),
            $count > 0 ? Translator::t('security.source.recommend') : '',
            Translator::t('security.source.solution'),
            ['files' => $found]
        );
    }
}
