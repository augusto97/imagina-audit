<?php
/**
 * Verificaciones de reputación: email expuesto, registros DNS (SPF/DMARC)
 * y Google Safe Browsing.
 *
 * Sub-checker de SecurityAnalyzer.
 */

class SecurityReputationChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    /**
     * Devuelve el dominio "registrable" — el de segundo nivel del host
     * actual. SPF/DMARC viven en el apex, no en el subdominio www. Si
     * consultáramos TXT www.dominio.com tendríamos un falso negativo
     * para cualquier sitio con www (que son la mayoría).
     * Esto es heurístico: maneja .com.co, .co.uk con array de TLDs
     * compuestos comunes; resto, últimos 2 segments.
     */
    private function registrableDomain(): string {
        $host = strtolower($this->host);
        $parts = explode('.', $host);
        $n = count($parts);
        if ($n <= 2) return $host;
        $compound = ['co.uk', 'com.co', 'com.ar', 'com.mx', 'com.br', 'com.au', 'co.nz', 'co.jp', 'com.es', 'com.pe', 'com.ve', 'com.uy', 'com.do', 'com.ec'];
        $last2 = $parts[$n - 2] . '.' . $parts[$n - 1];
        if (in_array($last2, $compound, true) && $n >= 3) {
            return $parts[$n - 3] . '.' . $last2;
        }
        return $last2;
    }

    public function checkExposedEmail(): array {
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $this->html, $matches);
        $emails = array_unique($matches[0] ?? []);
        // Filtrar falsos positivos: TLDs de imagen (logo@2x.png), placeholders,
        // y assets retina típicos.
        $imageTlds = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico'];
        $realEmails = array_filter($emails, function($e) use ($imageTlds) {
            $lower = strtolower($e);
            if (str_contains($lower, 'example.com') || str_contains($lower, 'wixpress') || str_contains($lower, 'schema.org')) return false;
            $tld = strtolower(substr($e, strrpos($e, '.') + 1));
            if (in_array($tld, $imageTlds, true)) return false;
            return true;
        });
        $realEmails = array_values($realEmails);
        $count = count($realEmails);

        return Scoring::createMetric(
            'exposed_email',
            Translator::t('security.email.name'),
            $count,
            $count === 0 ? Translator::t('security.email.display.ok') : Translator::t('security.email.display.exposed', ['count' => $count]),
            $count === 0 ? 100 : 50,
            $count === 0
                ? Translator::t('security.email.desc.ok')
                : Translator::t('security.email.desc.exposed', ['count' => $count, 'list' => implode(', ', array_slice($realEmails, 0, 3))]),
            $count > 0 ? Translator::t('security.email.recommend') : '',
            Translator::t('security.email.solution'),
            ['emails' => array_slice($realEmails, 0, 5)]
        );
    }

    public function checkDmarc(): array {
        // DMARC vive en _dmarc.<dominio-apex>. Si el host actual es
        // www.dominio.com, consultar _dmarc.www.dominio.com da NX en el
        // 99% de los casos → falso "sin DMARC" para casi todo sitio.
        $apex = $this->registrableDomain();
        $records = @dns_get_record('_dmarc.' . $apex, DNS_TXT);
        $hasDmarc = false;
        $dmarcValue = '';

        if ($records) {
            foreach ($records as $r) {
                $txt = $r['txt'] ?? '';
                if (stripos($txt, 'v=DMARC1') !== false) {
                    $hasDmarc = true;
                    $dmarcValue = $txt;
                    break;
                }
            }
        }

        // Extraer policy (p=) del registro DMARC para mostrarlo en el display
        $policy = 'none';
        if ($hasDmarc && preg_match('/p=([a-z]+)/i', $dmarcValue, $pm)) {
            $policy = strtolower($pm[1]);
        }

        return Scoring::createMetric(
            'dmarc',
            Translator::t('security.dmarc.name'),
            $hasDmarc,
            $hasDmarc ? Translator::t('security.dmarc.display.ok', ['policy' => $policy]) : Translator::t('security.dmarc.display.none'),
            $hasDmarc ? 100 : 40,
            $hasDmarc
                ? Translator::t('security.dmarc.desc.ok', ['policy' => $policy])
                : Translator::t('security.dmarc.desc.none'),
            $hasDmarc ? '' : Translator::t('security.dmarc.recommend'),
            Translator::t('security.dmarc.solution'),
            ['value' => $dmarcValue]
        );
    }

    public function checkSpf(): array {
        // SPF también vive en el apex — mismo motivo que DMARC.
        $records = @dns_get_record($this->registrableDomain(), DNS_TXT);
        $hasSpf = false;
        $spfValue = '';

        if ($records) {
            foreach ($records as $r) {
                $txt = $r['txt'] ?? '';
                if (stripos($txt, 'v=spf1') !== false) {
                    $hasSpf = true;
                    $spfValue = $txt;
                    break;
                }
            }
        }

        return Scoring::createMetric(
            'spf',
            Translator::t('security.spf.name'),
            $hasSpf,
            $hasSpf ? Translator::t('security.spf.display.ok') : Translator::t('security.spf.display.none'),
            $hasSpf ? 100 : 50,
            $hasSpf ? Translator::t('security.spf.desc.ok') : Translator::t('security.spf.desc.none'),
            $hasSpf ? '' : Translator::t('security.spf.recommend'),
            Translator::t('security.spf.solution'),
            ['value' => $spfValue]
        );
    }

    public function checkSafeBrowsing(): array {
        // Usa la misma key de Google (PageSpeed) — si no hay, devuelve métrica informativa
        $apiKey = env('GOOGLE_PAGESPEED_API_KEY', '');
        if (empty($apiKey)) {
            try {
                $db = Database::getInstance();
                $row = $db->queryOne("SELECT value FROM settings WHERE `key` = 'google_pagespeed_api_key'");
                if ($row && !empty($row['value'])) $apiKey = $row['value'];
            } catch (Throwable $e) {}
        }

        if (empty($apiKey)) {
            return Scoring::createMetric(
                'safe_browsing',
                Translator::t('security.sb.name'),
                null,
                Translator::t('security.sb.display.na'),
                null,
                Translator::t('security.sb.desc.na'),
                '',
                Translator::t('security.sb.solution')
            );
        }

        $url = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . urlencode($apiKey);
        $requestBody = [
            'client' => ['clientId' => 'imagina-audit', 'clientVersion' => '1.0'],
            'threatInfo' => [
                'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                'platformTypes' => ['ANY_PLATFORM'],
                'threatEntryTypes' => ['URL'],
                'threatEntries' => [['url' => $this->url]],
            ],
        ];

        try {
            $response = Fetcher::post($url, $requestBody, 5);

            if ($response['statusCode'] !== 200) {
                return Scoring::createMetric(
                    'safe_browsing',
                    Translator::t('security.sb.name'),
                    null,
                    Translator::t('security.sb.display.na'),
                    null,
                    Translator::t('security.sb.desc.na'),
                    '',
                    Translator::t('security.sb.solution')
                );
            }

            $data = json_decode($response['body'], true);
            $threats = $data['matches'] ?? [];
            $isSafe = empty($threats);

            if ($isSafe) {
                return Scoring::createMetric(
                    'safe_browsing',
                    Translator::t('security.sb.name'),
                    true,
                    Translator::t('security.sb.display.ok'),
                    100,
                    Translator::t('security.sb.desc.ok'),
                    '',
                    Translator::t('security.sb.solution')
                );
            }

            $threatTypes = array_map(fn($t) => $t['threatType'] ?? 'Unknown', $threats);

            return Scoring::createMetric(
                'safe_browsing',
                Translator::t('security.sb.name'),
                false,
                Translator::t('security.sb.display.bad'),
                0,
                Translator::t('security.sb.desc.bad'),
                Translator::t('security.sb.recommend'),
                Translator::t('security.sb.solution'),
                ['threats' => $threats, 'threatTypes' => $threatTypes]
            );
        } catch (Throwable $e) {
            return Scoring::createMetric(
                'safe_browsing',
                Translator::t('security.sb.name'),
                null,
                Translator::t('security.sb.display.na'),
                null,
                Translator::t('security.sb.desc.na'),
                '',
                Translator::t('security.sb.solution')
            );
        }
    }
}
