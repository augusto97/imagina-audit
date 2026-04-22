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

    public function checkExposedEmail(): array {
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $this->html, $matches);
        $emails = array_unique($matches[0] ?? []);
        $realEmails = array_filter($emails, fn($e) => !str_contains($e, 'example.com') && !str_contains($e, 'wixpress') && !str_contains($e, 'schema.org'));
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
        $records = @dns_get_record('_dmarc.' . $this->host, DNS_TXT);
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
        $records = @dns_get_record($this->host, DNS_TXT);
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
                $row = $db->queryOne("SELECT value FROM settings WHERE key = 'google_pagespeed_api_key'");
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
