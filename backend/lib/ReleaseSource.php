<?php
/**
 * ReleaseSource — lee el último release publicado en GitHub y descarga sus
 * archivos para el actualizador.
 *
 * Seguridad:
 *   - Solo HTTPS y solo hosts de GitHub (api.github.com, github.com,
 *     *.githubusercontent.com). Las redirecciones se siguen a mano y cada
 *     salto se valida contra esa lista (las descargas de assets redirigen
 *     a release-assets/objects.githubusercontent.com).
 *   - Tope de tamaño en cada descarga.
 *   - `UPDATE_API_BASE` (solo en .env) permite apuntar a otro servidor para
 *     pruebas; ese host se añade a la lista permitida.
 *
 * Formato esperado del release (lo arma .github/workflows/release.yml):
 *   tag vX.Y.Z  +  assets imagina-audit-X.Y.Z.zip, .zip.sha256, manifest.json
 */
class ReleaseSource
{
    private const DEFAULT_API = 'https://api.github.com';
    private const UA = 'ImaginaAudit-Updater';

    private string $repo;
    private string $apiBase;
    private string $token;
    /** @var string[] */
    private array $allowedHosts = ['api.github.com', 'github.com'];
    private bool $allowHttp = false;

    public function __construct(string $repo, ?string $apiBase = null, string $token = '')
    {
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            throw new InvalidArgumentException('Repositorio inválido (formato owner/repo).');
        }
        $this->repo = $repo;
        $this->token = $token;
        $base = rtrim((string) ($apiBase ?: self::DEFAULT_API), '/');
        $this->apiBase = $base;
        if ($base !== self::DEFAULT_API) {
            $host = strtolower((string) parse_url($base, PHP_URL_HOST));
            if ($host === '') throw new InvalidArgumentException('UPDATE_API_BASE inválido.');
            $this->allowedHosts[] = $host;
            // http:// solo se admite para un servidor de pruebas explícito.
            $this->allowHttp = str_starts_with($base, 'http://');
        }
    }

    public static function fromSettings(string $repo): self
    {
        $base = function_exists('env') ? env('UPDATE_API_BASE', '') : '';
        $token = function_exists('env') ? env('UPDATE_GITHUB_TOKEN', '') : '';
        return new self($repo, $base !== '' ? $base : null, $token);
    }

    public function repo(): string { return $this->repo; }

    /**
     * Último release publicado (no draft/prerelease), o null si el repo no
     * tiene ninguno. Lanza RuntimeException ante errores de red/formato.
     */
    public function latest(): ?array
    {
        [$status, $body] = $this->request("{$this->apiBase}/repos/{$this->repo}/releases/latest", 1_000_000, true);
        if ($status === 404) return null;
        if ($status !== 200) {
            $msg = json_decode($body, true)['message'] ?? '';
            throw new RuntimeException("GitHub respondió HTTP $status" . ($msg ? ": $msg" : ''));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('Respuesta de GitHub ilegible.');

        $tag = (string) ($data['tag_name'] ?? '');
        $version = ltrim($tag, 'vV');
        if (!AppVersion::isValid($version)) {
            throw new RuntimeException("El último release ($tag) no tiene una versión X.Y.Z válida.");
        }
        $assets = [];
        foreach ($data['assets'] ?? [] as $a) {
            $assets[(string) ($a['name'] ?? '')] = [
                'url' => (string) ($a['browser_download_url'] ?? ''),
                'size' => (int) ($a['size'] ?? 0),
            ];
        }
        $zipName = "imagina-audit-$version.zip";
        if (!isset($assets[$zipName], $assets["$zipName.sha256"])) {
            throw new RuntimeException("El release $tag no trae $zipName y su .sha256.");
        }

        $manifest = null;
        if (isset($assets['manifest.json'])) {
            $raw = $this->fetchText($assets['manifest.json']['url'], 200_000);
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || ($manifest['version'] ?? '') !== $version) {
                throw new RuntimeException('manifest.json del release no coincide con la versión.');
            }
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => (string) ($data['name'] ?? $tag),
            'body' => mb_substr((string) ($data['body'] ?? ''), 0, 20000),
            'publishedAt' => (string) ($data['published_at'] ?? ''),
            'htmlUrl' => (string) ($data['html_url'] ?? ''),
            'zip' => ['name' => $zipName] + $assets[$zipName],
            'sha256Url' => $assets["$zipName.sha256"]['url'],
            'manifest' => $manifest,
        ];
    }

    /** Descarga un archivo de texto pequeño (sha256, manifest). */
    public function fetchText(string $url, int $maxBytes): string
    {
        [$status, $body] = $this->request($url, $maxBytes, false);
        if ($status !== 200) throw new RuntimeException("Descarga fallida (HTTP $status): " . basename((string) parse_url($url, PHP_URL_PATH)));
        return $body;
    }

    /** Descarga a disco con tope de tamaño. Borra el archivo parcial si falla. */
    public function download(string $url, string $dest, int $maxBytes): void
    {
        $fh = @fopen($dest, 'wb');
        if (!$fh) throw new RuntimeException('No se pudo crear el archivo de descarga.');
        try {
            [$status] = $this->request($url, $maxBytes, false, $fh);
        } catch (Throwable $e) {
            fclose($fh);
            @unlink($dest);
            throw $e;
        }
        fclose($fh);
        if ($status !== 200) {
            @unlink($dest);
            throw new RuntimeException("Descarga fallida (HTTP $status).");
        }
    }

    /**
     * GET con redirecciones manuales validadas. Si $sink es un recurso, el
     * cuerpo se escribe ahí; si no, se devuelve.
     * @return array{0:int,1:string}
     */
    private function request(string $url, int $maxBytes, bool $api, $sink = null): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('La extensión curl no está disponible.');
        for ($hop = 0; $hop < 6; $hop++) {
            $this->assertAllowed($url);
            $headers = ['User-Agent: ' . self::UA, 'Accept: ' . ($api ? 'application/vnd.github+json' : 'application/octet-stream')];
            // El token solo viaja a la API de GitHub, nunca a los hosts de descarga.
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($this->token !== '' && $host === strtolower((string) parse_url($this->apiBase, PHP_URL_HOST))) {
                $headers[] = 'Authorization: Bearer ' . $this->token;
            }

            $body = '';
            $received = 0;
            $tooBig = false;
            $location = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROTOCOLS => $this->allowHttp ? (CURLPROTO_HTTPS | CURLPROTO_HTTP) : CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$location) {
                    if (stripos($line, 'location:') === 0) $location = trim(substr($line, 9));
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$received, &$tooBig, $maxBytes, $sink) {
                    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                    if ($code >= 300 && $code < 400) return strlen($chunk); // cuerpo de la redirección: descartar
                    $received += strlen($chunk);
                    if ($received > $maxBytes) { $tooBig = true; return 0; }
                    if ($sink) return fwrite($sink, $chunk);
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($tooBig) throw new RuntimeException('El archivo supera el tamaño máximo permitido.');
            if ($ok === false && !($status >= 300 && $status < 400)) {
                throw new RuntimeException('Error de red al contactar ' . $host . ($err ? ": $err" : ''));
            }
            if ($status >= 300 && $status < 400 && $location) {
                $url = $this->resolve($url, $location);
                continue;
            }
            return [$status, $body];
        }
        throw new RuntimeException('Demasiadas redirecciones.');
    }

    private function assertAllowed(string $url): void
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if ($scheme !== 'https' && !($this->allowHttp && $scheme === 'http')) {
            throw new RuntimeException('Solo se permiten descargas HTTPS.');
        }
        $ok = in_array($host, $this->allowedHosts, true) || str_ends_with($host, '.githubusercontent.com');
        if (!$ok) throw new RuntimeException("Host no permitido para actualizaciones: $host");
    }

    private function resolve(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) return $location;
        $p = parse_url($base);
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        return str_starts_with($location, '/') ? $origin . $location : $origin . '/' . $location;
    }
}
