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
     * Envía headers CORS
     */
    public static function cors(): void {
        $allowedOrigin = env('ALLOWED_ORIGIN', '*');
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($allowedOrigin === '*') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $allowed = array_map('trim', explode(',', $allowedOrigin));
            if (!empty($requestOrigin) && in_array($requestOrigin, $allowed, true)) {
                header("Access-Control-Allow-Origin: $requestOrigin");
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            } else {
                header("Access-Control-Allow-Origin: {$allowed[0]}");
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Headers de seguridad
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // Responder a preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
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
