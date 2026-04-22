<?php
/**
 * Analiza la infraestructura del servidor: hosting, protocolo, CDN, compresión
 */

class InfrastructureAnalyzer {
    private string $url;
    private array $headers;
    private float $ttfb;
    private string $httpVersion;

    public function __construct(string $url, array $headers, float $ttfb = 0, string $httpVersion = '1.1') {
        $this->url = rtrim($url, '/');
        $this->headers = $headers;
        $this->ttfb = $ttfb;
        $this->httpVersion = $httpVersion;
    }

    /**
     * Ejecuta el análisis de infraestructura
     */
    public function analyze(): array {
        $metrics = [];

        // Servidor web
        $metrics[] = $this->checkServer();

        // Protocolo HTTP
        $metrics[] = $this->checkProtocol();

        // CDN
        $metrics[] = $this->checkCdn();

        // PHP expuesto
        $metrics[] = $this->checkPhpExposed();

        // IP y proveedor
        $metrics[] = $this->checkHosting();

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'infrastructure',
            'name' => Translator::t('modules.infrastructure.name'),
            'icon' => 'server',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_infrastructure'],
            'metrics' => $metrics,
            'summary' => Translator::t('infrastructure.summary', ['score' => $score]),
            'salesMessage' => $defaults['sales_infrastructure'] !== '' ? $defaults['sales_infrastructure'] : Translator::t('modules.sales.infrastructure'),
        ];
    }

    /**
     * Identifica el servidor web
     */
    private function checkServer(): array {
        $server = $this->headers['server'] ?? '';
        if ($server === '')              $serverName = Translator::t('infrastructure.server.display.none');
        elseif (stripos($server, 'nginx') !== false)      $serverName = 'Nginx';
        elseif (stripos($server, 'apache') !== false)     $serverName = 'Apache';
        elseif (stripos($server, 'litespeed') !== false)  $serverName = 'LiteSpeed';
        elseif (stripos($server, 'cloudflare') !== false) $serverName = 'Cloudflare';
        elseif (stripos($server, 'iis') !== false)        $serverName = 'IIS';
        else                                              $serverName = Translator::t('infrastructure.server.display.unknown');

        // No penalizar por el tipo de servidor, solo informativo
        return Scoring::createMetric(
            'server',
            Translator::t('infrastructure.server.name'),
            $serverName,
            $serverName,
            null, // Informativo
            Translator::t('infrastructure.server.desc', ['name' => $serverName]),
            '',
            Translator::t('infrastructure.server.solution')
        );
    }

    /**
     * Verifica la versión del protocolo HTTP
     */
    private function checkProtocol(): array {
        $version = $this->httpVersion;
        $isHttp2Plus = in_array($version, ['2', '2.0', '3']);
        $score = $isHttp2Plus ? 100 : 50;

        return Scoring::createMetric(
            'http_protocol',
            Translator::t('infrastructure.proto.name'),
            $version,
            Translator::t('infrastructure.proto.display', ['version' => $version]),
            $score,
            $isHttp2Plus
                ? Translator::t('infrastructure.proto.desc.modern', ['version' => $version])
                : Translator::t('infrastructure.proto.desc.old', ['version' => $version]),
            !$isHttp2Plus ? Translator::t('infrastructure.proto.recommend') : '',
            Translator::t('infrastructure.proto.solution')
        );
    }

    /**
     * Evalúa el TTFB
     */
    private function checkTtfb(): array {
        $ttfb = round($this->ttfb);
        $score = $ttfb <= 200 ? 100 : ($ttfb <= 500 ? 80 : ($ttfb <= 800 ? 50 : 20));

        return Scoring::createMetric(
            'ttfb',
            Translator::t('infrastructure.ttfb.name'),
            $this->ttfb,
            Translator::t('infrastructure.ttfb.display', ['ms' => $ttfb]),
            $score,
            Translator::t('infrastructure.ttfb.desc.prefix', ['ms' => $ttfb])
                . ($ttfb <= 500 ? Translator::t('infrastructure.ttfb.desc.good') : Translator::t('infrastructure.ttfb.desc.bad')),
            $ttfb > 500 ? Translator::t('infrastructure.ttfb.recommend') : '',
            Translator::t('infrastructure.ttfb.solution')
        );
    }

    /**
     * Detecta CDN
     */
    private function checkCdn(): array {
        $cdnDetected = null;

        if (isset($this->headers['cf-ray']))                                                       $cdnDetected = 'Cloudflare';
        elseif (isset($this->headers['x-cdn']))                                                    $cdnDetected = $this->headers['x-cdn'];
        elseif (isset($this->headers['x-cache']) && str_contains($this->headers['x-cache'], 'HIT')) $cdnDetected = Translator::t('infrastructure.cdn.detected.cache');
        elseif (isset($this->headers['x-served-by']))                                              $cdnDetected = Translator::t('infrastructure.cdn.detected.generic');
        elseif (isset($this->headers['via']) && preg_match('/cloudfront|akamai|fastly|varnish/i', $this->headers['via'])) {
            $cdnDetected = Translator::t('infrastructure.cdn.detected.generic');
        }

        $hasCdn = $cdnDetected !== null;

        return Scoring::createMetric(
            'cdn',
            Translator::t('infrastructure.cdn.name'),
            $hasCdn,
            $hasCdn
                ? Translator::t('infrastructure.cdn.display.ok', ['name' => $cdnDetected])
                : Translator::t('infrastructure.cdn.display.none'),
            $hasCdn ? 100 : 30,
            $hasCdn
                ? Translator::t('infrastructure.cdn.desc.ok', ['name' => $cdnDetected])
                : Translator::t('infrastructure.cdn.desc.none'),
            !$hasCdn ? Translator::t('infrastructure.cdn.recommend') : '',
            Translator::t('infrastructure.cdn.solution')
        );
    }

    /**
     * Verifica compresión
     */
    private function checkCompression(): array {
        $encoding = $this->headers['content-encoding'] ?? '';
        $hasCompression = !empty($encoding);

        return Scoring::createMetric(
            'compression',
            Translator::t('infrastructure.comp.name'),
            $hasCompression,
            $hasCompression
                ? Translator::t('infrastructure.comp.display.ok', ['encoding' => strtoupper($encoding)])
                : Translator::t('infrastructure.comp.display.none'),
            $hasCompression ? 100 : 20,
            $hasCompression
                ? Translator::t('infrastructure.comp.desc.ok', ['encoding' => $encoding])
                : Translator::t('infrastructure.comp.desc.none'),
            !$hasCompression ? Translator::t('infrastructure.comp.recommend') : '',
            Translator::t('infrastructure.comp.solution')
        );
    }

    /**
     * Verifica si PHP está expuesto
     */
    private function checkPhpExposed(): array {
        $phpVersion = $this->headers['x-powered-by'] ?? null;
        $isExposed = $phpVersion !== null;

        return Scoring::createMetric(
            'php_exposed',
            Translator::t('infrastructure.php.name'),
            $isExposed,
            $isExposed
                ? Translator::t('infrastructure.php.display.exposed', ['value' => $phpVersion])
                : Translator::t('infrastructure.php.display.ok'),
            $isExposed ? 40 : 100,
            $isExposed
                ? Translator::t('infrastructure.php.desc.exposed', ['value' => $phpVersion])
                : Translator::t('infrastructure.php.desc.ok'),
            $isExposed ? Translator::t('infrastructure.php.recommend') : '',
            Translator::t('infrastructure.php.solution')
        );
    }

    /**
     * Detecta proveedor de hosting por IP
     */
    private function checkHosting(): array {
        $host = parse_url($this->url, PHP_URL_HOST);
        $ip = $host ? gethostbyname($host) : Translator::t('infrastructure.host.ip.unresolved');

        // Detectar algunos proveedores por reverse DNS
        $reverseDns = $ip !== $host ? @gethostbyaddr($ip) : '';
        $provider = Translator::t('infrastructure.host.provider.unknown');

        if ($reverseDns) {
            if (str_contains($reverseDns, 'amazonaws.com')) $provider = 'AWS';
            elseif (str_contains($reverseDns, 'googleusercontent.com')) $provider = 'Google Cloud';
            elseif (str_contains($reverseDns, 'cloudflare')) $provider = 'Cloudflare';
            elseif (str_contains($reverseDns, 'digitalocean')) $provider = 'DigitalOcean';
            elseif (str_contains($reverseDns, 'siteground')) $provider = 'SiteGround';
            elseif (str_contains($reverseDns, 'godaddy')) $provider = 'GoDaddy';
            elseif (str_contains($reverseDns, 'hostgator')) $provider = 'HostGator';
            elseif (str_contains($reverseDns, 'bluehost')) $provider = 'Bluehost';
        }

        return Scoring::createMetric(
            'hosting',
            Translator::t('infrastructure.host.name'),
            $ip,
            Translator::t('infrastructure.host.display', ['provider' => $provider, 'ip' => $ip]),
            null, // Informativo
            Translator::t('infrastructure.host.desc', ['ip' => $ip, 'provider' => $provider]),
            '',
            Translator::t('infrastructure.host.solution')
        );
    }
}
