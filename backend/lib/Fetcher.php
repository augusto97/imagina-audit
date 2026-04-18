<?php
/**
 * Wrapper seguro de cURL para peticiones HTTP externas
 * Incluye protección anti-SSRF, timeouts, límites de tamaño y reintentos
 */

class Fetcher {
    private const USER_AGENT = 'ImaginaAudit/1.0 (+https://imaginawp.com)';
    private const MAX_REDIRECTS = 5;
    private const MAX_RESPONSE_SIZE = 5 * 1024 * 1024; // 5MB
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Resultado de un fetch
     */
    public static function createResult(
        int $statusCode,
        array $headers,
        string $body,
        string $finalUrl,
        float $responseTime,
        string $httpVersion = ''
    ): array {
        return [
            'statusCode' => $statusCode,
            'headers' => $headers,
            'body' => $body,
            'finalUrl' => $finalUrl,
            'responseTime' => $responseTime,
            'httpVersion' => $httpVersion,
        ];
    }

    /**
     * Realiza un GET request
     * @param string $url URL a consultar
     * @param int $timeout Timeout en segundos
     * @param bool $followRedirects Si debe seguir redirecciones
     * @param int $retries Número de reintentos
     * @return array Resultado del fetch
     */
    public static function get(
        string $url,
        int $timeout = self::DEFAULT_TIMEOUT,
        bool $followRedirects = true,
        int $retries = 1
    ): array {
        return self::request('GET', $url, null, $timeout, $followRedirects, $retries);
    }

    /**
     * Realiza un HEAD request
     */
    public static function head(string $url, int $timeout = 5): array {
        return self::request('HEAD', $url, null, $timeout, false, 0);
    }

    /**
     * Realiza un POST request con body JSON
     */
    public static function post(string $url, array $data, int $timeout = self::DEFAULT_TIMEOUT): array {
        return self::request('POST', $url, json_encode($data), $timeout, true, 1);
    }

    /**
     * Ejecuta una petición HTTP
     */
    private static function request(
        string $method,
        string $url,
        ?string $body,
        int $timeout,
        bool $followRedirects,
        int $retries
    ): array {
        // Validar URL contra SSRF antes de realizar la petición
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return self::createResult(0, [], '', $url, 0);
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $resolvedIp = null;

        // Verificar IP privada si es una IP directa
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Logger::warning("SSRF bloqueado: IP privada $host");
                return self::createResult(0, [], '', $url, 0);
            }
        } else {
            // Resolver DNS y verificar la IP — forzar esta IP en cURL para prevenir DNS rebinding
            $resolvedIp = gethostbyname($host);
            if ($resolvedIp === $host) {
                Logger::warning("SSRF bloqueado: no se pudo resolver $host");
                return self::createResult(0, [], '', $url, 0);
            }
            if (filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Logger::warning("SSRF bloqueado: $host resuelve a IP privada $resolvedIp");
                return self::createResult(0, [], '', $url, 0);
            }
        }

        $attempt = 0;
        $maxAttempts = 1 + $retries;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $result = self::executeRequest($method, $url, $body, $timeout, $followRedirects, $resolvedIp ? "$host:$port:$resolvedIp" : null);

            if ($result['statusCode'] > 0) {
                return $result;
            }

            // Solo reintentar en timeout (statusCode === 0)
            if ($attempt < $maxAttempts) {
                usleep(500000); // Esperar 0.5s antes de reintentar
            }
        }

        return $result;
    }

    /**
     * Ejecuta la petición cURL
     */
    private static function executeRequest(
        string $method,
        string $url,
        ?string $body,
        int $timeout,
        bool $followRedirects,
        ?string $dnsResolve = null
    ): array {
        $ch = curl_init();
        $responseHeaders = [];

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '', // Aceptar gzip, deflate, br
            CURLOPT_MAXFILESIZE => self::MAX_RESPONSE_SIZE,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es,en;q=0.5',
            ],
            // Capturar headers de respuesta
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    $responseHeaders[$name] = $value;
                }
                return $len;
            },
        ];

        if ($dnsResolve !== null) {
            $opts[CURLOPT_RESOLVE] = [$dnsResolve];
        }

        curl_setopt_array($ch, $opts);

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
        }

        $startTime = microtime(true);
        $responseBody = curl_exec($ch);
        $endTime = microtime(true);

        $responseTime = round(($endTime - $startTime) * 1000, 2); // milisegundos
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $httpVersion = '';

        // Detectar versión HTTP
        $httpVersionCode = curl_getinfo($ch, CURLINFO_HTTP_VERSION);
        switch ($httpVersionCode) {
            case CURL_HTTP_VERSION_1_0: $httpVersion = '1.0'; break;
            case CURL_HTTP_VERSION_1_1: $httpVersion = '1.1'; break;
            case CURL_HTTP_VERSION_2_0: $httpVersion = '2'; break;
            case 3: $httpVersion = '3'; break; // CURL_HTTP_VERSION_3
            default: $httpVersion = '1.1';
        }

        if (curl_errno($ch)) {
            Logger::warning('cURL error: ' . curl_error($ch), ['url' => $url]);
            $statusCode = 0;
            $responseBody = '';
        }

        curl_close($ch);

        return self::createResult(
            $statusCode,
            $responseHeaders,
            $responseBody ?: '',
            $finalUrl ?: $url,
            $responseTime,
            $httpVersion
        );
    }

    /**
     * Obtiene información del certificado SSL de un dominio
     */
    public static function getSslInfo(string $host): array {
        $result = [
            'valid' => false,
            'issuer' => '',
            'validFrom' => '',
            'validTo' => '',
            'daysUntilExpiry' => 0,
            'protocol' => '',
        ];

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://$host:443",
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($socket === false) {
                return $result;
            }

            $params = stream_context_get_params($socket);
            fclose($socket);

            if (!isset($params['options']['ssl']['peer_certificate'])) {
                return $result;
            }

            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            if ($cert === false) {
                return $result;
            }

            $validFrom = $cert['validFrom_time_t'] ?? 0;
            $validTo = $cert['validTo_time_t'] ?? 0;
            $issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Desconocido';

            $result['valid'] = $validTo > time();
            $result['issuer'] = $issuer;
            $result['validFrom'] = date('Y-m-d', $validFrom);
            $result['validTo'] = date('Y-m-d', $validTo);
            $result['daysUntilExpiry'] = max(0, (int) floor(($validTo - time()) / 86400));

        } catch (Throwable $e) {
            Logger::warning("Error obteniendo SSL info para $host: " . $e->getMessage());
        }

        return $result;
    }
}
