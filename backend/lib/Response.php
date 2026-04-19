<?php
/**
 * Helper para respuestas JSON estandarizadas
 */

class Response {
    /**
     * Envía una respuesta JSON exitosa
     */
    public static function success(mixed $data = null, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Envía una respuesta JSON de error
     */
    public static function error(string $message, int $statusCode = 400, ?array $details = null): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => false,
            'error' => $message,
        ];
        if ($details !== null) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Envía headers CORS y de seguridad.
     *
     * Dos políticas según el endpoint:
     *
     * - Endpoints públicos (/api/audit, /api/config, /api/health, /api/compare,
     *   /api/history, /api/audit-status): CORS abierto (Allow-Origin: *) SIN
     *   credenciales. Permite que el widget embebible funcione desde cualquier
     *   dominio de cliente sin tener que listarlos previamente. Los endpoints
     *   están rate-limited por IP y no exponen datos privados.
     *
     * - Endpoints admin (/api/admin/*): whitelist estricta (env ALLOWED_ORIGIN o
     *   DB allowed_origins) con Allow-Credentials: true para que la cookie de
     *   sesión viaje. Si el Origin no matchea, no se envían cabeceras CORS y el
     *   navegador bloquea la petición.
     */
    public static function cors(): void {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isAdminEndpoint = str_contains($requestUri, '/admin/');

        if ($isAdminEndpoint) {
            self::applyAdminCors();
        } else {
            self::applyPublicCors();
        }

        // Headers de seguridad (aplican a todas las respuestas)
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
        // CSP restrictiva para respuestas JSON — no se renderiza nada en el navegador
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

        // HSTS solo sobre HTTPS
        $isHttps =
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Responder a preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * CORS para endpoints públicos (widget embebible y frontend público).
     * Abierto a cualquier origen SIN credenciales.
     */
    private static function applyPublicCors(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * CORS para endpoints admin. Solo dominios whitelisted, con credenciales.
     */
    private static function applyAdminCors(): void {
        $allowedOrigin = env('ALLOWED_ORIGIN', '');

        // La configuración en DB tiene prioridad sobre .env
        try {
            $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE key = 'allowed_origins'");
            if ($row && !empty($row['value'])) {
                $allowedOrigin = $row['value'];
            }
        } catch (Throwable $e) {}

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $allowedOrigin === '' ? [] : array_map('trim', explode(',', $allowedOrigin));

        if (!empty($requestOrigin) && in_array($requestOrigin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $requestOrigin");
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            header('Access-Control-Max-Age: 86400');
        }
        // Si no hay match: sin cabeceras CORS. Mismo-origen sigue funcionando normalmente.
    }

    /**
     * Obtiene el body JSON de la petición
     */
    public static function getJsonBody(): array {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('JSON inválido en el body de la petición', 400);
        }

        return $data ?? [];
    }
}
