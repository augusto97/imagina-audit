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
     * Ejecuta una petición HTTP con validación anti-SSRF en cada hop.
     *
     * Los redirects se siguen manualmente (no con CURLOPT_FOLLOWLOCATION) para
     * re-validar cada URL destino contra SSRF. Esto cierra el hueco donde
     * evil.com redirige a 127.0.0.1 / 169.254.169.254 / etc.
     */
    private static function request(
        string $method,
        string $url,
        ?string $body,
        int $timeout,
        bool $followRedirects,
        int $retries
    ): array {
        $attempt = 0;
        $maxAttempts = 1 + $retries;
        $result = self::createResult(0, [], '', $url, 0);

        while ($attempt < $maxAttempts) {
            $attempt++;
            $result = self::followChain($method, $url, $body, $timeout, $followRedirects);

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
     * Sigue la cadena de redirects manualmente, validando SSRF en cada hop.
     */
    private static function followChain(
        string $method,
        string $url,
        ?string $body,
        int $timeout,
        bool $followRedirects
    ): array {
        $currentUrl = $url;
        $hops = 0;

        while (true) {
            $ssrf = self::validateUrlForRequest($currentUrl);
            if ($ssrf === null) {
                // Bloqueado por SSRF
                return self::createResult(0, [], '', $currentUrl, 0);
            }

            // Nunca dejamos que cURL siga redirects — los seguimos aquí
            $result = self::executeRequest(
                $method,
                $currentUrl,
                $body,
                $timeout,
                false,
                $ssrf['dnsResolve']
            );

            $status = $result['statusCode'];
            $isRedirect = $status >= 300 && $status < 400 && !empty($result['headers']['location']);

            if (!$followRedirects || !$isRedirect || $hops >= self::MAX_REDIRECTS) {
                return $result;
            }

            $nextUrl = self::resolveRedirectUrl($currentUrl, $result['headers']['location']);
            if ($nextUrl === null) {
                return $result; // Location inválida, devolvemos lo que tenemos
            }

            $currentUrl = $nextUrl;
            $hops++;
            // Solo GET para redirects (las normas HTTP permiten 307/308 preservar método,
            // pero en auditoría queremos simplicidad: leemos, no operamos)
            $method = 'GET';
            $body = null;
        }
    }

    /**
     * Valida una URL contra SSRF. Retorna null si debe bloquearse.
     * Si es válida, retorna ['host', 'port', 'resolvedIp', 'dnsResolve'].
     * dnsResolve es la cadena para CURLOPT_RESOLVE (previene DNS rebinding).
     */
    private static function validateUrlForRequest(string $url): ?array {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host']) || empty($parsed['scheme'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            Logger::warning("SSRF bloqueado: esquema no permitido $scheme");
            return null;
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        $resolvedIp = null;

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Es IP directa
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Logger::warning("SSRF bloqueado: IP privada $host");
                return null;
            }
        } else {
            // Resolver DNS y fijar la IP en cURL (anti DNS-rebinding dentro del request)
            $resolvedIp = gethostbyname($host);
            if ($resolvedIp === $host) {
                Logger::warning("SSRF bloqueado: no se pudo resolver $host");
                return null;
            }
            if (filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Logger::warning("SSRF bloqueado: $host resuelve a IP privada $resolvedIp");
                return null;
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'resolvedIp' => $resolvedIp,
            'dnsResolve' => $resolvedIp ? "$host:$port:$resolvedIp" : null,
        ];
    }

    /**
     * Resuelve una URL de redirect relativa o absoluta.
     * Retorna null si es malformada o usa esquema no permitido.
     */
    private static function resolveRedirectUrl(string $currentUrl, string $location): ?string {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        // URL absoluta
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        // Cualquier otro esquema absoluto (javascript:, data:, file:, ftp://...) → bloqueado
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) && !preg_match('#^//#', $location)) {
            Logger::warning("SSRF bloqueado: redirect a esquema no permitido: $location");
            return null;
        }

        $base = parse_url($currentUrl);
        if ($base === false || empty($base['host']) || empty($base['scheme'])) {
            return null;
        }

        // Protocol-relative: //host/path
        if (str_starts_with($location, '//')) {
            return $base['scheme'] . ':' . $location;
        }

        $authority = $base['scheme'] . '://' . $base['host'];
        if (!empty($base['port'])) {
            $authority .= ':' . $base['port'];
        }

        // Absolute path: /path
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        // Relative path: path or ../path
        $basePath = $base['path'] ?? '/';
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1) ?: '/';
        return $authority . $dir . $location;
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
     * Realiza múltiples GET en paralelo con curl_multi.
     *
     * Valida cada URL contra SSRF antes de firar y fija la IP resuelta
     * en CURLOPT_RESOLVE (anti DNS-rebinding). No sigue redirects: los
     * callers de este método asumen endpoints confiables (p. ej. APIs
     * de Google). Preserva las claves del array de entrada.
     *
     * @param array $urls Mapa clave → URL
     * @param int $timeout Timeout por petición en segundos
     * @return array Mapa clave → resultado (mismo shape que get())
     */
    public static function multiGet(array $urls, int $timeout = 30): array {
        if (empty($urls)) {
            return [];
        }

        $results = [];
        $handles = [];
        $allHeaders = [];
        $multi = curl_multi_init();

        foreach ($urls as $key => $url) {
            $ssrf = self::validateUrlForRequest($url);
            if ($ssrf === null) {
                $results[$key] = self::createResult(0, [], '', $url, 0);
                continue;
            }

            $ch = curl_init();
            $id = spl_object_id($ch);
            $allHeaders[$id] = [];

            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXFILESIZE => self::MAX_RESPONSE_SIZE,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json,text/html;q=0.9,*/*;q=0.8',
                    'Accept-Language: es,en;q=0.5',
                ],
                CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$allHeaders, $id) {
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2) {
                        $allHeaders[$id][strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return strlen($header);
                },
            ];
            if ($ssrf['dnsResolve'] !== null) {
                $opts[CURLOPT_RESOLVE] = [$ssrf['dnsResolve']];
            }

            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($multi, $ch);

            $handles[$key] = ['ch' => $ch, 'id' => $id, 'url' => $url, 'start' => microtime(true)];
        }

        // Ejecutar en paralelo
        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        // Recolectar resultados
        foreach ($handles as $key => $h) {
            $ch = $h['ch'];
            $body = curl_multi_getcontent($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $responseTime = round((microtime(true) - $h['start']) * 1000, 2);

            if (curl_errno($ch)) {
                Logger::warning('cURL multi error: ' . curl_error($ch), ['url' => $h['url']]);
                $statusCode = 0;
                $body = '';
            }

            $results[$key] = self::createResult(
                $statusCode,
                $allHeaders[$h['id']] ?? [],
                $body ?: '',
                $finalUrl ?: $h['url'],
                $responseTime
            );

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return $results;
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
