<?php
/**
 * Verificación de cabeceras HTTP de seguridad y SRI.
 *
 * Sub-checker de SecurityAnalyzer.
 */

class SecurityHeadersChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    public function checkSecurityHeaders(): array {
        $headersToCheck = [
            'x-content-type-options' => ['name' => 'X-Content-Type-Options', 'points' => 14],
            'x-frame-options' => ['name' => 'X-Frame-Options', 'points' => 14],
            'content-security-policy' => ['name' => 'Content-Security-Policy', 'points' => 14],
            'strict-transport-security' => ['name' => 'Strict-Transport-Security', 'points' => 14],
            'x-xss-protection' => ['name' => 'X-XSS-Protection', 'points' => 11],
            'referrer-policy' => ['name' => 'Referrer-Policy', 'points' => 11],
            'permissions-policy' => ['name' => 'Permissions-Policy', 'points' => 11],
        ];

        $present = [];
        $missing = [];
        $score = 0;

        foreach ($headersToCheck as $headerKey => $info) {
            if (isset($this->headers[$headerKey])) {
                $present[] = $info['name'];
                $score += $info['points'];
            } else {
                $missing[] = $info['name'];
            }
        }

        if (empty($missing)) {
            $score += 11;
        }

        $score = min(100, $score);

        return Scoring::createMetric(
            'security_headers',
            'Headers de seguridad HTTP',
            count($present),
            count($present) . '/' . count($headersToCheck) . ' headers presentes',
            $score,
            count($present) === count($headersToCheck)
                ? 'Todos los headers de seguridad están configurados correctamente.'
                : 'Faltan headers de seguridad: ' . implode(', ', $missing) . '.',
            empty($missing) ? '' : 'Agregar los headers de seguridad faltantes en la configuración del servidor.',
            'Configuramos todos los headers de seguridad HTTP recomendados.',
            ['present' => $present, 'missing' => $missing]
        );
    }

    public function checkExposedHeaders(): array {
        $exposed = [];
        $score = 100;

        if (isset($this->headers['server'])) {
            $server = $this->headers['server'];
            if (preg_match('/[\d.]+/', $server)) {
                $exposed[] = "Server: $server";
                $score -= 20;
            }
        }

        if (isset($this->headers['x-powered-by'])) {
            $exposed[] = 'X-Powered-By: ' . $this->headers['x-powered-by'];
            $score -= 20;
        }

        $score = max(0, $score);

        return Scoring::createMetric(
            'exposed_headers',
            'Headers de servidor expuestos',
            count($exposed),
            count($exposed) > 0 ? implode(', ', $exposed) : 'No expuestos',
            $score,
            count($exposed) > 0
                ? 'Se detectaron headers que exponen información del servidor: ' . implode('; ', $exposed)
                : 'No se detectaron headers que expongan información del servidor.',
            count($exposed) > 0 ? 'Ocultar la versión del servidor y el header X-Powered-By.' : '',
            'Ocultamos información del servidor para reducir la superficie de ataque.'
        );
    }

    public function checkSubresourceIntegrity(): array {
        preg_match_all('/<script[^>]+src=["\']https?:\/\/([^"\']+)["\'][^>]*>/i', $this->html, $matches, PREG_SET_ORDER);
        $external = 0;
        $withIntegrity = 0;
        $withoutIntegrity = [];

        foreach ($matches as $m) {
            $src = $m[1];
            if (str_contains($src, $this->host)) continue;
            $external++;
            if (str_contains($m[0], 'integrity=')) $withIntegrity++;
            else $withoutIntegrity[] = $src;
        }

        if ($external === 0) {
            return Scoring::createMetric(
                'sri', 'Subresource Integrity (SRI)', null, 'Sin scripts externos', null,
                'No se detectaron scripts externos. SRI no aplica.',
                '', 'Configuramos SRI en scripts de CDN para protección contra CDN comprometido.'
            );
        }

        $pct = (int) round(($withIntegrity / $external) * 100);
        return Scoring::createMetric(
            'sri', 'Subresource Integrity (SRI)', $pct, "$withIntegrity/$external con SRI",
            $pct >= 80 ? 100 : ($pct >= 50 ? 70 : 40),
            "De $external scripts externos, $withIntegrity tienen atributo integrity ($pct%). SRI protege contra CDNs comprometidos.",
            $pct < 80 ? 'Agregar atributo integrity="sha384-..." a todos los scripts cargados desde CDN externo.' : '',
            'Implementamos SRI en todos los recursos externos.',
            ['external' => $external, 'withIntegrity' => $withIntegrity, 'withoutIntegrity' => array_slice($withoutIntegrity, 0, 5)]
        );
    }
}
