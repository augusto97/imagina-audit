<?php
/**
 * Verificaciones de seguridad específicas de WordPress (no vulnerabilidades).
 *
 * Sub-checker de SecurityAnalyzer. Aplica solo si el sitio es WordPress.
 */

class SecurityWpChecker {
    public function __construct(
        private string $url,
        private string $html,
        private array $headers,
        private string $host,
        private array $wpData = []
    ) {}

    public function checkDirectoryListing(): array {
        $listings = [];

        $dirs = ['/wp-content/uploads/', '/wp-content/plugins/'];
        foreach ($dirs as $dir) {
            $response = Fetcher::get($this->url . $dir, 5, true, 0);
            if ($response['statusCode'] === 200 && str_contains($response['body'], 'Index of')) {
                $listings[] = $dir;
            }
        }

        $score = empty($listings) ? 100 : max(0, 100 - (count($listings) * 30));

        return Scoring::createMetric(
            'directory_listing',
            'Listado de directorios',
            count($listings),
            count($listings) > 0 ? 'Activo en ' . count($listings) . ' directorios' : 'Desactivado',
            $score,
            count($listings) > 0
                ? 'El listado de directorios está activo en: ' . implode(', ', $listings) . '. Cualquiera puede ver los archivos.'
                : 'El listado de directorios está desactivado.',
            count($listings) > 0 ? 'Desactivar directory listing con "Options -Indexes" en .htaccess.' : '',
            'Desactivamos el listado de directorios y protegemos la estructura del sitio.'
        );
    }

    public function checkWpInfoFiles(): array {
        $files = ['/readme.html', '/license.txt', '/wp-config-sample.php'];
        $exposed = [];
        foreach ($files as $f) {
            $resp = Fetcher::head($this->url . $f, 3);
            if ($resp['statusCode'] === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_info_files', 'Archivos de información de WordPress', $count,
            $count === 0 ? 'Protegidos' : "$count archivos expuestos",
            $count === 0 ? 100 : 50,
            $count === 0
                ? 'Los archivos informativos de WordPress (readme.html, license.txt) están protegidos. Correcto.'
                : 'Archivos expuestos: ' . implode(', ', $exposed) . '. readme.html revela la versión exacta de WordPress facilitando ataques dirigidos.',
            $count > 0 ? 'Eliminar o bloquear acceso a estos archivos en .htaccess.' : '',
            'Eliminamos archivos informativos que revelan la versión de WordPress.',
            ['files' => $exposed]
        );
    }

    public function checkWpInstallFiles(): array {
        $files = ['/wp-admin/install.php', '/wp-admin/upgrade.php', '/wp-admin/install-helper.php'];
        $exposed = [];
        foreach ($files as $f) {
            $resp = Fetcher::head($this->url . $f, 3);
            if ($resp['statusCode'] === 200) $exposed[] = $f;
        }
        $count = count($exposed);

        return Scoring::createMetric(
            'wp_install_files', 'Archivos de instalación WordPress', $count,
            $count === 0 ? 'Protegidos' : "$count accesibles",
            $count === 0 ? 100 : 30,
            $count === 0
                ? 'Los archivos de instalación de WordPress están protegidos.'
                : 'Archivos de instalación accesibles: ' . implode(', ', $exposed) . '. Pueden ser explotados en ciertos escenarios.',
            $count > 0 ? 'Bloquear acceso a /wp-admin/install.php, /wp-admin/upgrade.php vía .htaccess después de la instalación.' : '',
            'Bloqueamos archivos de instalación después del setup inicial.',
            ['files' => $exposed]
        );
    }

    public function checkPhpInUploads(): array {
        $response = Fetcher::get($this->url . '/wp-content/uploads/', 5, true, 0);
        $foundPhp = [];

        if ($response['statusCode'] === 200 && str_contains($response['body'], 'Index of')) {
            preg_match_all('/href=["\']([^"\']+\.php)["\']/i', $response['body'], $matches);
            foreach ($matches[1] ?? [] as $phpFile) {
                if (!str_starts_with($phpFile, '?')) $foundPhp[] = $phpFile;
            }
        }

        $count = count($foundPhp);
        return Scoring::createMetric(
            'php_in_uploads', 'Archivos PHP en uploads', $count,
            $count === 0 ? 'Ninguno detectado' : "$count archivos PHP",
            $count === 0 ? 100 : 0,
            $count === 0
                ? 'No se detectaron archivos PHP en /wp-content/uploads/. Correcto.'
                : 'CRÍTICO: Se detectaron ' . $count . ' archivos PHP en /wp-content/uploads/. Esto es un fuerte indicador de malware o backdoor instalado por un atacante.',
            $count > 0 ? 'Revisar cada archivo PHP en uploads, escanear el sitio con un plugin de seguridad, cambiar todas las contraseñas, y agregar regla en .htaccess para bloquear ejecución de PHP en uploads.' : '',
            'Escaneamos y limpiamos malware de los directorios de uploads.',
            ['phpFiles' => array_slice($foundPhp, 0, 10)]
        );
    }

    public function checkRestApiEnumerationExtra(): array {
        $endpoints = [
            '/wp-json/wp/v2/users',
            '/wp-json/wp/v2/pages',
            '/wp-json/wp/v2/posts',
            '/wp-json/wp/v2/media',
        ];
        $exposed = [];

        foreach ($endpoints as $ep) {
            $resp = Fetcher::get($this->url . $ep, 3, true, 0);
            if ($resp['statusCode'] === 200) {
                $data = json_decode($resp['body'], true);
                if (is_array($data) && !empty($data)) {
                    $exposed[] = ['endpoint' => $ep, 'count' => count($data)];
                }
            }
        }

        $count = count($exposed);
        return Scoring::createMetric(
            'rest_api_enumeration', 'REST API — Enumeración', $count,
            $count === 0 ? 'Protegida' : count($exposed) . ' endpoints exponen datos',
            $count === 0 ? 100 : ($count >= 3 ? 30 : 50),
            $count === 0
                ? 'La REST API no expone datos públicamente. Correcto.'
                : 'La REST API expone datos en: ' . implode(', ', array_map(fn($e) => $e['endpoint'], $exposed)) . '. Facilita recolectar información del sitio.',
            $count > 0 ? 'Restringir acceso a endpoints REST API para usuarios no autenticados usando plugin de seguridad o código personalizado.' : '',
            'Protegemos la REST API de WordPress contra enumeración de datos.',
            ['endpoints' => $exposed]
        );
    }

    public function checkDefaultAdminUser(): array {
        $defaultUsers = ['admin', 'administrator', 'test', 'demo', 'user', 'wordpress'];
        $detectedUsers = [];

        $response = Fetcher::get($this->url . '/wp-json/wp/v2/users', 3, true, 0);
        if ($response['statusCode'] === 200) {
            $data = json_decode($response['body'], true);
            if (is_array($data)) {
                foreach ($data as $u) {
                    $slug = strtolower($u['slug'] ?? '');
                    if (in_array($slug, $defaultUsers)) {
                        $detectedUsers[] = $slug;
                    }
                }
            }
        }

        if (empty($detectedUsers)) {
            for ($i = 1; $i <= 3; $i++) {
                $resp = Fetcher::get($this->url . '/?author=' . $i, 3, false, 0);
                if (in_array($resp['statusCode'], [301, 302])) {
                    $location = $resp['headers']['location'] ?? '';
                    if (preg_match('#/author/([^/]+)/?#i', $location, $m)) {
                        $slug = strtolower($m[1]);
                        if (in_array($slug, $defaultUsers)) {
                            $detectedUsers[] = $slug;
                        }
                    }
                }
            }
        }

        $count = count(array_unique($detectedUsers));
        return Scoring::createMetric(
            'default_admin_user', 'Usuario admin por defecto', $count,
            $count === 0 ? 'Ninguno' : implode(', ', array_unique($detectedUsers)),
            $count === 0 ? 100 : 20,
            $count === 0
                ? 'No se detectaron nombres de usuario por defecto (admin, administrator, etc.).'
                : 'Usuarios con nombre predecible detectados: ' . implode(', ', array_unique($detectedUsers)) . '. Los atacantes apuntan a estos nombres para ataques de fuerza bruta.',
            $count > 0 ? 'Crear un nuevo usuario admin con nombre personalizado y eliminar el usuario por defecto.' : '',
            'Renombramos usuarios admin por defecto para prevenir ataques de fuerza bruta.',
            ['detected' => array_values(array_unique($detectedUsers))]
        );
    }

    public function checkSecurityPlugin(): array {
        $plugins = [
            'wordfence' => 'Wordfence',
            'sucuri' => 'Sucuri',
            'ithemes-security' => 'Solid Security (iThemes)',
            'better-wp-security' => 'Solid Security (iThemes)',
            'all-in-one-wp-security' => 'All In One WP Security',
            'really-simple-ssl' => 'Really Simple SSL',
            'defender-security' => 'Defender Security',
            'shield-security' => 'Shield Security',
            'jetpack' => 'Jetpack (incluye security)',
        ];

        $detected = [];
        foreach ($plugins as $slug => $name) {
            if (str_contains($this->html, "/$slug/") || stripos($this->html, $slug) !== false) {
                $detected[] = $name;
            }
        }
        $detected = array_unique($detected);
        $count = count($detected);

        return Scoring::createMetric(
            'security_plugin', 'Plugin de seguridad', $count,
            $count === 0 ? 'No detectado' : implode(', ', $detected),
            $count > 0 ? 100 : 70,
            $count > 0
                ? 'Se detectó plugin de seguridad: ' . implode(', ', $detected) . '. Ayuda a proteger contra malware, brute force y vulnerabilidades.'
                : 'No se detectó ningún plugin de seguridad instalado. Se recomienda usar Wordfence, Solid Security u otro.',
            $count === 0 ? 'Instalar un plugin de seguridad como Wordfence o Solid Security para protección adicional.' : '',
            'Instalamos y configuramos plugins de seguridad para protección en múltiples capas.',
            ['detected' => $detected]
        );
    }
}
