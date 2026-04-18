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
            'name' => 'Infraestructura',
            'icon' => 'server',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_infrastructure'],
            'metrics' => $metrics,
            'summary' => "Tu infraestructura tiene una puntuación de $score/100.",
            'salesMessage' => $defaults['sales_infrastructure'],
        ];
    }

    /**
     * Identifica el servidor web
     */
    private function checkServer(): array {
        $server = $this->headers['server'] ?? 'No detectado';
        $serverName = 'Desconocido';

        if (stripos($server, 'nginx') !== false) $serverName = 'Nginx';
        elseif (stripos($server, 'apache') !== false) $serverName = 'Apache';
        elseif (stripos($server, 'litespeed') !== false) $serverName = 'LiteSpeed';
        elseif (stripos($server, 'cloudflare') !== false) $serverName = 'Cloudflare';
        elseif (stripos($server, 'iis') !== false) $serverName = 'IIS';

        // No penalizar por el tipo de servidor, solo informativo
        return Scoring::createMetric(
            'server',
            'Servidor Web',
            $serverName,
            $serverName,
            null, // Informativo
            "Servidor web detectado: $serverName.",
            '',
            'Recomendamos LiteSpeed o Nginx para máximo rendimiento con WordPress.'
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
            'Protocolo HTTP',
            $version,
            "HTTP/$version",
            $score,
            $isHttp2Plus
                ? "El sitio usa HTTP/$version. Protocolo moderno con multiplexación y mejor rendimiento."
                : "El sitio usa HTTP/$version. HTTP/2 ofrece mejor rendimiento con carga en paralelo.",
            !$isHttp2Plus ? 'Habilitar HTTP/2 en el servidor para mejorar la velocidad de carga.' : '',
            'Configuramos HTTP/2 o HTTP/3 para máximo rendimiento.'
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
            'Tiempo de Respuesta (TTFB)',
            $this->ttfb,
            "{$ttfb}ms",
            $score,
            "El servidor responde en {$ttfb}ms. " .
            ($ttfb <= 500 ? 'Buen tiempo de respuesta.' : 'Se recomienda menos de 500ms.'),
            $ttfb > 500 ? 'Considerar un hosting más rápido o configurar cache de servidor.' : '',
            'Migramos tu sitio a hosting optimizado con cache de servidor avanzado.'
        );
    }

    /**
     * Detecta CDN
     */
    private function checkCdn(): array {
        $cdnDetected = null;

        if (isset($this->headers['cf-ray'])) $cdnDetected = 'Cloudflare';
        elseif (isset($this->headers['x-cdn'])) $cdnDetected = $this->headers['x-cdn'];
        elseif (isset($this->headers['x-cache']) && str_contains($this->headers['x-cache'], 'HIT')) $cdnDetected = 'CDN (cache activo)';
        elseif (isset($this->headers['x-served-by'])) $cdnDetected = 'CDN detectado';
        elseif (isset($this->headers['via']) && preg_match('/cloudfront|akamai|fastly|varnish/i', $this->headers['via'])) {
            $cdnDetected = 'CDN detectado';
        }

        $hasCdn = $cdnDetected !== null;

        return Scoring::createMetric(
            'cdn',
            'CDN (Red de distribución)',
            $hasCdn,
            $hasCdn ? $cdnDetected : 'No detectado',
            $hasCdn ? 100 : 30,
            $hasCdn
                ? "Se detectó CDN: $cdnDetected. El contenido se sirve desde servidores cercanos al usuario."
                : 'No se detectó un CDN. El contenido se sirve desde un solo servidor.',
            !$hasCdn ? 'Implementar un CDN como Cloudflare para mejorar velocidad y disponibilidad.' : '',
            'Configuramos Cloudflare CDN para que tu sitio cargue rápido en todo el mundo.'
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
            'Compresión del Servidor',
            $hasCompression,
            $hasCompression ? strtoupper($encoding) : 'Sin compresión',
            $hasCompression ? 100 : 20,
            $hasCompression
                ? "El servidor usa compresión $encoding. Reduce el tamaño de transferencia."
                : 'No se detectó compresión GZIP o Brotli. Los archivos se transfieren sin comprimir.',
            !$hasCompression ? 'Habilitar compresión GZIP o Brotli en la configuración del servidor.' : '',
            'Configuramos compresión Brotli/GZIP para máxima eficiencia.'
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
            'Versión PHP Expuesta',
            $isExposed,
            $isExposed ? $phpVersion : 'Oculta',
            $isExposed ? 40 : 100,
            $isExposed
                ? "La versión de PHP está expuesta: $phpVersion. Facilita que atacantes conozcan vulnerabilidades."
                : 'La versión de PHP está oculta. Buena práctica de seguridad.',
            $isExposed ? 'Ocultar el header X-Powered-By en la configuración de PHP.' : '',
            'Ocultamos toda información del servidor que pueda ser usada por atacantes.'
        );
    }

    /**
     * Detecta proveedor de hosting por IP
     */
    private function checkHosting(): array {
        $host = parse_url($this->url, PHP_URL_HOST);
        $ip = $host ? gethostbyname($host) : 'No resuelto';

        // Detectar algunos proveedores por reverse DNS
        $reverseDns = $ip !== $host ? @gethostbyaddr($ip) : '';
        $provider = 'No identificado';

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
            'Hosting / IP',
            $ip,
            "$provider ($ip)",
            null, // Informativo
            "IP del servidor: $ip. Proveedor detectado: $provider.",
            '',
            'Evaluamos tu hosting y recomendamos la mejor opción para WordPress.'
        );
    }
}
