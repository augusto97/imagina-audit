<?php
/**
 * Autenticación y sesión admin
 */

class Auth {
    /**
     * Inicia la sesión PHP si no está activa
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Verifica si el admin está autenticado
     */
    public static function isAuthenticated(): bool {
        self::startSession();
        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }

    /**
     * Intenta autenticar con una contraseña
     */
    public static function login(string $password): bool {
        $hash = env('ADMIN_PASSWORD_HASH', '');

        // Intentar obtener el hash desde la DB si no está en .env
        if (empty($hash)) {
            try {
                $db = Database::getInstance();
                $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_password_hash'");
                if ($row) {
                    $hash = $row['value'];
                }
            } catch (Throwable $e) {
                Logger::error('Error al obtener hash de admin desde DB: ' . $e->getMessage());
            }
        }

        if (empty($hash)) {
            Logger::error('No hay contraseña de admin configurada');
            return false;
        }

        if (password_verify($password, $hash)) {
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_login_time'] = time();
            return true;
        }

        return false;
    }

    /**
     * Cierra la sesión admin
     */
    public static function logout(): void {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Requiere autenticación o responde con error 401
     */
    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            Response::error('No autorizado', 401);
        }
    }
}
