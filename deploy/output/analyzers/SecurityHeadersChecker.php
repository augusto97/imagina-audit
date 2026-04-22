<?php
/**
 * Verificación de cabeceras HTTP de seguridad y SRI.
 *
 * Sub-checker de SecurityAnalyzer.
 */

class SecurityHeadersChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    public function checkSecurityHeaders(): array {
        $headersToCheck = [
            'x-content-type-options' => ['name' => 'X-Content-Type-Options', 'points' => 14],
            'x-frame-options' => ['name' => 'X-Frame-Options', 'points' => 14],
            'content-security-policy' => ['name' => 'Content-Security-Policy', 'points' => 14],
            'strict-transport-security' => ['name' => 'Strict-Transport-Security', 'points' => 14],
            'x-xss-protection' => ['name' => 'X-XSS-Protection', 'points' => 11],
            'referrer-policy' => ['name' => 'Referrer-Policy', 'points' => 11],
            'permissions-policy' => ['name' => 'Permissions-Policy', 'points' => 11],
        ];

        $present = [];
        $missing = [];
        $score = 0;

        foreach ($headersToCheck as $headerKey => $info) {
            if (isset($this->headers[$headerKey])) {
                $present[] = $info['name'];
                $score += $info['points'];
            } else {
                $missing[] = $info['name'];
            }
        }

        if (empty($missing)) {
            $score += 11;
        }

        $score = min(100, $score);

        $missingList = implode(', ', $missing);
        return Scoring::createMetric(
            'security_headers',
            Translator::t('security.headers.name'),
            count($present),
            Translator::t('security.headers.display', ['present' => count($present)]),
            $score,
            empty($present)
                ? Translator::t('security.headers.desc.none')
                : (empty($missing)
                    ? Translator::t('security.headers.desc.ok')
                    : Translator::t('security.headers.desc.partial', ['missing' => $missingList])),
            empty($missing) ? '' : Translator::t('security.headers.recommend', ['list' => $missingList]),
            Translator::t('security.headers.solution'),
            ['present' => $present, 'missing' => $missing]
        );
    }

    public function checkExposedHeaders(): array {
        $exposed = [];
        $score = 100;

        if (isset($this->headers['server'])) {
            $server = $this->headers['server'];
            if (preg_match('/[\d.]+/', $server)) {
                $exposed[] = "Server: $server";
                $score -= 20;
            }
        }

        if (isset($this->headers['x-powered-by'])) {
            $exposed[] = 'X-Powered-By: ' . $this->headers['x-powered-by'];
            $score -= 20;
        }

        $score = max(0, $score);

        $exposedList = implode(', ', $exposed);
        return Scoring::createMetric(
            'exposed_headers',
            Translator::t('security.exposed.name'),
            count($exposed),
            count($exposed) > 0 ? Translator::t('security.exposed.display.exposed', ['list' => $exposedList]) : Translator::t('security.exposed.display.ok'),
            $score,
            count($exposed) > 0
                ? Translator::t('security.exposed.desc.exposed', ['list' => implode('; ', $exposed)])
                : Translator::t('security.exposed.desc.ok'),
            count($exposed) > 0 ? Translator::t('security.exposed.recommend') : '',
            Translator::t('security.exposed.solution')
        );
    }

    public function checkSubresourceIntegrity(): array {
        preg_match_all('/<script[^>]+src=["\']https?:\/\/([^"\']+)["\'][^>]*>/i', $this->html, $matches, PREG_SET_ORDER);
        $external = 0;
        $withIntegrity = 0;
        $withoutIntegrity = [];

        foreach ($matches as $m) {
            $src = $m[1];
            if (str_contains($src, $this->host)) continue;
            $external++;
            if (str_contains($m[0], 'integrity=')) $withIntegrity++;
            else $withoutIntegrity[] = $src;
        }

        if ($external === 0) {
            return Scoring::createMetric(
                'sri',
                Translator::t('security.sri.name'),
                null,
                Translator::t('security.sri.display', ['withSri' => 0, 'total' => 0]),
                null,
                Translator::t('security.sri.desc.none'),
                '',
                Translator::t('security.sri.solution')
            );
        }

        $pct = (int) round(($withIntegrity / $external) * 100);
        return Scoring::createMetric(
            'sri',
            Translator::t('security.sri.name'),
            $pct,
            Translator::t('security.sri.display', ['withSri' => $withIntegrity, 'total' => $external]),
            $pct >= 80 ? 100 : ($pct >= 50 ? 70 : 40),
            Translator::t('security.sri.desc.ok', ['count' => $external - $withIntegrity]),
            $pct < 80 ? Translator::t('security.sri.recommend') : '',
            Translator::t('security.sri.solution'),
            ['external' => $external, 'withIntegrity' => $withIntegrity, 'withoutIntegrity' => array_slice($withoutIntegrity, 0, 5)]
        );
    }
}
