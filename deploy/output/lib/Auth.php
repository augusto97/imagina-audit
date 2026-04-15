<?php
/**
 * Autenticación y sesión admin
 * Sesión PHP estándar con expiración de 8 horas
 */

class Auth {
    /**
     * Inicia la sesión PHP si no está activa
     */
    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Intenta autenticar con una contraseña
     */
    public static function login(string $password): bool {
        // Obtener hash desde la DB
        $hash = '';
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE key = 'admin_password_hash'");
            if ($row) {
                $hash = $row['value'];
            }
        } catch (Throwable $e) {
            Logger::error('Error al obtener hash de admin desde DB: ' . $e->getMessage());
        }

        // Fallback a .env
        if (empty($hash)) {
            $hash = env('ADMIN_PASSWORD_HASH', '');
        }

        if (empty($hash)) {
            Logger::error('No hay contraseña de admin configurada');
            return false;
        }

        if (password_verify($password, $hash)) {
            self::ensureSession();
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_login_time'] = time();
            return true;
        }

        return false;
    }

    /**
     * Verifica si el admin está autenticado y la sesión no expiró (8 horas)
     */
    public static function checkAuth(): bool {
        self::ensureSession();

        if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            return false;
        }

        // Verificar expiración de 8 horas
        $loginTime = $_SESSION['admin_login_time'] ?? 0;
        if (time() - $loginTime > 8 * 3600) {
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * Requiere autenticación o responde con error 401
     */
    public static function requireAuth(): void {
        if (!self::checkAuth()) {
            Response::error('No autorizado', 401);
        }
    }

    /**
     * Cierra la sesión admin
     */
    public static function logout(): void {
        self::ensureSession();
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
}
