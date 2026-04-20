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
            'exposed_email', 'Email expuesto en texto plano', $count,
            $count === 0 ? 'No detectado' : "$count email(s) expuesto(s)",
            $count === 0 ? 100 : 50,
            $count === 0
                ? 'No se detectaron direcciones de email en texto plano. Correcto.'
                : "Se encontraron $count email(s) en texto plano: " . implode(', ', array_slice($realEmails, 0, 3)) . '. Los bots de spam rastrean la web buscando emails expuestos.',
            $count > 0 ? 'Ocultar los emails usando formularios de contacto o codificación JavaScript.' : '',
            'Protegemos los emails de contacto contra bots de spam.',
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

        return Scoring::createMetric(
            'dmarc', 'Registro DMARC', $hasDmarc,
            $hasDmarc ? 'Configurado' : 'No encontrado',
            $hasDmarc ? 100 : 40,
            $hasDmarc
                ? 'DMARC está configurado para este dominio. Protege contra suplantación de identidad por email.'
                : 'No se encontró registro DMARC. Sin DMARC, cualquiera puede enviar emails suplantando tu dominio (phishing/spoofing).',
            $hasDmarc ? '' : 'Configurar un registro DMARC en el DNS del dominio para proteger contra suplantación de email.',
            'Configuramos DMARC, SPF y DKIM para proteger tu dominio contra phishing.',
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
            'spf', 'Registro SPF', $hasSpf,
            $hasSpf ? 'Configurado' : 'No encontrado',
            $hasSpf ? 100 : 50,
            $hasSpf
                ? 'SPF configurado. Especifica qué servidores pueden enviar email en nombre del dominio.'
                : 'No se encontró SPF. Cualquiera puede enviar email suplantando tu dominio.',
            $hasSpf ? '' : 'Configurar un registro SPF (TXT) que liste los servidores autorizados a enviar email.',
            'Configuramos SPF, DKIM y DMARC para proteger tu dominio.',
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
                'safe_browsing', 'Google Safe Browsing', null, 'Sin API key',
                null,
                'No se pudo verificar Google Safe Browsing (requiere API key de Google). La misma key de PageSpeed funciona.',
                '', 'Monitoreamos que tu sitio no aparezca en listas negras de Google.'
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
                    'safe_browsing', 'Google Safe Browsing', null, 'Error en API',
                    null,
                    'No se pudo consultar Google Safe Browsing (error ' . $response['statusCode'] . ').',
                    '', 'Monitoreamos que tu sitio no aparezca en listas negras.'
                );
            }

            $data = json_decode($response['body'], true);
            $threats = $data['matches'] ?? [];
            $isSafe = empty($threats);

            if ($isSafe) {
                return Scoring::createMetric(
                    'safe_browsing', 'Google Safe Browsing', true, 'Sitio seguro',
                    100,
                    'El sitio NO aparece en la lista negra de Google Safe Browsing. No se detectó malware, phishing ni software no deseado.',
                    '', 'Monitoreamos continuamente que tu sitio no sea marcado como peligroso.'
                );
            }

            $threatTypes = array_map(fn($t) => $t['threatType'] ?? 'Unknown', $threats);
            $threatLabels = [
                'MALWARE' => 'Malware',
                'SOCIAL_ENGINEERING' => 'Phishing/Ingeniería social',
                'UNWANTED_SOFTWARE' => 'Software no deseado',
                'POTENTIALLY_HARMFUL_APPLICATION' => 'Aplicación peligrosa',
            ];
            $labels = array_map(fn($t) => $threatLabels[$t] ?? $t, $threatTypes);

            return Scoring::createMetric(
                'safe_browsing', 'Google Safe Browsing', false,
                'EN LISTA NEGRA',
                0,
                'ALERTA: El sitio está marcado como peligroso por Google: ' . implode(', ', $labels) . '. Google muestra una advertencia roja a los usuarios que intentan visitarlo.',
                'Limpiar el sitio de malware/contenido malicioso y solicitar una revisión en Google Search Console.',
                'Limpiamos sitios infectados y solicitamos la remoción de la lista negra de Google.',
                ['threats' => $threats, 'threatTypes' => $threatTypes]
            );
        } catch (Throwable $e) {
            return Scoring::createMetric(
                'safe_browsing', 'Google Safe Browsing', null, 'Error',
                null,
                'No se pudo verificar: ' . $e->getMessage(),
                '', 'Monitoreamos que tu sitio no aparezca en listas negras.'
            );
        }
    }
}
