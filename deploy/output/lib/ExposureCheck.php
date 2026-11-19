<?php
/**
 * ExposureCheck — comprueba de verdad (no con avisos genéricos) si las
 * carpetas internas de la app se pueden descargar desde internet.
 *
 * Funciona igual con Apache, Nginx o LiteSpeed porque no lee la
 * configuración del servidor: deja un archivo de prueba con un token
 * aleatorio en cada carpeta interna y lo pide por HTTP a la propia URL
 * pública de la app, como lo haría cualquier visitante. Si el servidor
 * devuelve el token, esa carpeta está expuesta. También prueba el `.env`.
 *
 * Los archivos de prueba se borran siempre (finally). El resultado se
 * cachea unas horas; el panel puede forzar una comprobación nueva.
 */
final class ExposureCheck
{
    /**
     * Carpetas que NUNCA deben servirse por HTTP. No incluye uploads/
     * (logos públicos), cron/ (la app llama a /cron/drain-queue.php por HTTP
     * para procesar la cola) ni locales/ (textos públicos).
     */
    public const PRIVATE_DIRS = ['storage', 'cache', 'logs', 'lib', 'config', 'data', 'database', 'analyzers'];

    private const CACHE_SECONDS = 6 * 3600;

    /**
     * @return array{checkedAt:int, base:string, reachable:bool, server:string, exposed:string[], protected:string[], skipped:string[], envExposed:bool, nginxSnippet:?string}
     */
    public static function run(string $appRoot, string $baseUrl, ?string $cacheFile = null, bool $force = false): array
    {
        if (!$force && $cacheFile && is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && (time() - (int) ($cached['checkedAt'] ?? 0)) < self::CACHE_SECONDS && ($cached['base'] ?? '') === $baseUrl) {
                return $cached;
            }
        }

        $base = rtrim($baseUrl, '/');
        $probes = [];   // dir => [path, token]
        $skipped = [];
        foreach (self::PRIVATE_DIRS as $dir) {
            $abs = "$appRoot/$dir";
            if (!is_dir($abs) || !is_writable($abs)) { $skipped[] = $dir; continue; }
            $token = bin2hex(random_bytes(16));
            $file = "$abs/imagina-probe-" . bin2hex(random_bytes(6)) . '.txt';
            if (@file_put_contents($file, $token) === false) { $skipped[] = $dir; continue; }
            $probes[$dir] = [$file, $token];
        }

        $result = [
            'checkedAt' => time(), 'base' => $base, 'reachable' => false, 'server' => '',
            'exposed' => [], 'protected' => [], 'skipped' => $skipped, 'envExposed' => false, 'nginxSnippet' => null,
        ];

        try {
            if (!function_exists('curl_multi_init')) return $result;
            $mh = curl_multi_init();
            $handles = [];
            $servers = [];
            $add = function (string $key, string $url) use ($mh, &$handles, &$servers) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
                    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$servers, $key) {
                        if (stripos($line, 'server:') === 0) $servers[$key] = trim(substr($line, 7));
                        return strlen($line);
                    },
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$key] = $ch;
            };
            foreach ($probes as $dir => [$file]) $add($dir, "$base/$dir/" . basename($file));
            $add('.env', "$base/.env");

            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) curl_multi_select($mh, 0.2);
            } while ($running && $status === CURLM_OK);

            foreach ($handles as $key => $ch) {
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $body = (string) curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($code > 0) $result['reachable'] = true;
                if ($key === '.env') {
                    // Expuesto si devuelve algo con forma de .env (no guardamos el contenido).
                    $result['envExposed'] = $code === 200 && preg_match('/^\s*[A-Z][A-Z0-9_]*\s*=/m', $body) === 1;
                    continue;
                }
                if ($code === 200 && trim($body) === $probes[$key][1]) {
                    $result['exposed'][] = $key;
                } elseif ($code > 0) {
                    $result['protected'][] = $key;
                } else {
                    $result['skipped'][] = $key;
                }
            }
            curl_multi_close($mh);
            $result['server'] = self::serverName($servers);
        } finally {
            foreach ($probes as [$file]) @unlink($file);
        }

        if ($result['exposed'] || $result['envExposed']) {
            $dirs = self::PRIVATE_DIRS;
            $result['nginxSnippet'] = "location ~ ^/(" . implode('|', $dirs) . ")/ {\n    deny all;\n    return 403;\n}\nlocation ~ /\\.env { deny all; return 403; }";
        }
        if ($cacheFile) {
            @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_SLASHES));
        }
        return $result;
    }

    private static function serverName(array $headers): string
    {
        $raw = strtolower(implode(' ', $headers) . ' ' . ($_SERVER['SERVER_SOFTWARE'] ?? ''));
        foreach (['openlitespeed' => 'OpenLiteSpeed', 'litespeed' => 'LiteSpeed', 'nginx' => 'Nginx', 'apache' => 'Apache', 'cloudflare' => 'Cloudflare'] as $needle => $label) {
            if (str_contains($raw, $needle)) return $label;
        }
        return $headers ? (string) reset($headers) : '';
    }
}
