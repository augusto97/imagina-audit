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
                'Certificado SSL',
                false,
                'No válido o no presente',
                0,
                'El sitio no tiene un certificado SSL válido. Los visitantes verán advertencias de seguridad.',
                'Instalar un certificado SSL (Let\'s Encrypt es gratuito).',
                'Instalamos y configuramos SSL gratuito con Let\'s Encrypt en tu hosting.'
            );
        }

        $days = $ssl['daysUntilExpiry'];
        $score = $days > 30 ? 100 : ($days > 7 ? 60 : 20);

        return Scoring::createMetric(
            'ssl_valid',
            'Certificado SSL',
            true,
            "Válido hasta {$ssl['validTo']} ({$days} días)",
            $score,
            $days > 30
                ? "Certificado SSL válido emitido por {$ssl['issuer']}. Expira el {$ssl['validTo']}."
                : "Certificado SSL próximo a expirar ({$days} días). Emitido por {$ssl['issuer']}.",
            $days <= 30 ? 'Renovar el certificado SSL antes de que expire.' : '',
            'Monitoreamos la expiración del SSL y lo renovamos automáticamente.',
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
            'Redirección HTTP → HTTPS',
            $redirectsToHttps,
            $redirectsToHttps ? 'Configurada correctamente' : 'No configurada',
            $redirectsToHttps ? 100 : 30,
            $redirectsToHttps
                ? 'HTTP redirige correctamente a HTTPS.'
                : 'HTTP no redirige a HTTPS. Los visitantes podrían acceder a la versión no segura.',
            $redirectsToHttps ? '' : 'Configurar redirección 301 de HTTP a HTTPS.',
            'Configuramos la redirección HTTPS y forzamos conexiones seguras.'
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
            'hsts_preload', 'HSTS Preload',
            $preloadReady ? 'ready' : ($hasHsts ? 'partial' : 'none'),
            $preloadReady ? 'Listo para preload' : ($hasHsts ? 'HSTS sin preload' : 'Sin HSTS'),
            $preloadReady ? 100 : ($hasHsts ? 70 : 40),
            $preloadReady
                ? 'HSTS completamente configurado con preload, includeSubDomains y max-age >= 1 año. Listo para enviar a hstspreload.org.'
                : ($hasHsts
                    ? 'HSTS presente pero falta preload/includeSubDomains/max-age suficiente para calificar al preload list de Chrome.'
                    : 'Sin HSTS. Configurar para forzar HTTPS y poder solicitar inclusión en el preload list.'),
            !$preloadReady ? 'Configurar: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload. Luego registrar en hstspreload.org' : '',
            'Configuramos HSTS con preload para máxima protección HTTPS.',
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
        return Scoring::createMetric(
            'weak_tls', 'Versiones TLS débiles', $count,
            $count === 0 ? 'Solo TLS 1.2+' : implode(', ', $weakFound) . ' habilitado',
            $count === 0 ? 100 : ($count === 1 ? 50 : 20),
            $count === 0
                ? 'El servidor solo acepta TLS 1.2 y superior. Correcto.'
                : 'El servidor acepta versiones TLS débiles: ' . implode(', ', $weakFound) . '. Son vulnerables a ataques como POODLE y BEAST.',
            $count > 0 ? 'Desactivar TLS 1.0 y 1.1 en la configuración del servidor. Solo habilitar TLS 1.2 y TLS 1.3.' : '',
            'Configuramos el servidor para aceptar solo versiones modernas de TLS.',
            ['weakVersions' => $weakFound]
        );
    }

    public function checkDnssec(): array {
        $parts = explode('.', $this->host);
        if (count($parts) < 2) {
            return Scoring::createMetric('dnssec', 'DNSSEC', null, 'N/A', null, 'Dominio inválido.', '', '');
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
            'dnssec', 'DNSSEC', $hasDnssec,
            $hasDnssec ? 'Habilitado' : 'No habilitado',
            $hasDnssec ? 100 : 60,
            $hasDnssec
                ? 'DNSSEC habilitado. Protege contra envenenamiento de caché DNS y redirecciones maliciosas.'
                : 'DNSSEC no habilitado. Sin firma DNS, es posible suplantar los registros DNS del dominio.',
            !$hasDnssec ? 'Habilitar DNSSEC en tu registrador o proveedor DNS (Cloudflare lo hace con 1 click).' : '',
            'Configuramos DNSSEC para proteger contra ataques de DNS.'
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
            'source_code_exposure', 'Exposición de código fuente', $count,
            $count === 0 ? 'Protegido' : "$count archivos expuestos",
            $count === 0 ? 100 : 0,
            $count === 0
                ? 'No se detectaron archivos de control de versiones expuestos (.git, .svn). Correcto.'
                : 'CRÍTICO: Archivos de control de versiones accesibles: ' . implode(', ', $found) . '. Un atacante puede descargar todo el código fuente incluyendo credenciales.',
            $count > 0 ? 'Bloquear acceso a /.git/, /.svn/, etc. en .htaccess o eliminar estos directorios del servidor web.' : '',
            'Protegemos contra fugas de código fuente y archivos de sistema.',
            ['files' => $found]
        );
    }
}
