<?php
/**
 * Autenticación y sesión de usuarios (cuentas creadas por el admin).
 *
 * Separada de Auth (admin) para que las dos sesiones puedan coexistir —
 * el admin mantiene `admin_authenticated`, el usuario `user_authenticated`
 * + `user_id`. Comparten el mismo PHPSESSID pero no se pisan.
 *
 * El flujo de login está protegido por:
 *   - Backoff progresivo por IP (0s → 2s → 5s → 15s → 60s → 5min).
 *   - Límite duro: 15 intentos fallidos en 15 min → IP bloqueada.
 *   - Cuenta deshabilitada (`is_active=0`) bloquea login aunque la
 *     password sea correcta.
 */

class UserAuth {
    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps =
                (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            session_start();
        }
    }

    /**
     * Valida credenciales y abre sesión. Devuelve el user array en éxito o
     * null si fallan credenciales / cuenta deshabilitada.
     */
    public static function login(string $email, string $password): ?array {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') return null;

        try {
            $db = Database::getInstance();
            $row = $db->queryOne(
                "SELECT id, email, password_hash, name, plan_id, is_active FROM users WHERE email = ?",
                [$email]
            );
            if (!$row) return null;
            if ((int) ($row['is_active'] ?? 0) !== 1) return null;
            if (!password_verify($password, (string) $row['password_hash'])) return null;

            self::ensureSession();
            session_regenerate_id(true);
            $_SESSION['user_authenticated'] = true;
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['user_login_time'] = time();
            $_SESSION['user_csrf_token'] = bin2hex(random_bytes(32));

            try {
                $db->execute("UPDATE users SET last_login_at = datetime('now') WHERE id = ?", [$row['id']]);
            } catch (Throwable $e) { /* no crítico */ }

            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'planId' => $row['plan_id'] !== null ? (int) $row['plan_id'] : null,
            ];
        } catch (Throwable $e) {
            Logger::error('UserAuth::login falló: ' . $e->getMessage());
            return null;
        }
    }

    /** Token CSRF específico del user (no se mezcla con el del admin). */
    public static function getCsrfToken(): string {
        self::ensureSession();
        if (empty($_SESSION['user_csrf_token'])) {
            $_SESSION['user_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['user_csrf_token'];
    }

    public static function verifyCsrf(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) return;

        self::ensureSession();
        $sessionToken = $_SESSION['user_csrf_token'] ?? '';
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken)) {
            Response::error(Translator::t('user_api.csrf_invalid'), 403);
        }
    }

    /**
     * ¿Hay un user logged-in y la sesión no expiró (30 días)?
     * Las sesiones de user viven más que las de admin porque es un
     * producto self-service — forzar relogin cada 8h sería molesto.
     */
    public static function checkAuth(): bool {
        self::ensureSession();
        if (empty($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
            return false;
        }
        $loginTime = $_SESSION['user_login_time'] ?? 0;
        if (time() - $loginTime > 30 * 24 * 3600) {
            self::logout();
            return false;
        }
        return true;
    }

    public static function requireAuth(): void {
        if (!self::checkAuth()) {
            Response::error(Translator::t('user_api.not_authenticated'), 401);
        }
        self::verifyCsrf();
    }

    /**
     * Devuelve el user activo con su plan (JOIN), o null si no hay sesión.
     * Se consulta la DB en cada llamada para que los cambios del admin
     * (deshabilitar cuenta, cambiar plan) surtan efecto en el próximo request.
     */
    public static function currentUser(): ?array {
        if (!self::checkAuth()) return null;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId === 0) return null;

        try {
            $db = Database::getInstance();
            $row = $db->queryOne(
                "SELECT u.id, u.email, u.name, u.plan_id, u.is_active,
                        p.name AS plan_name, p.monthly_limit AS plan_limit,
                        p.max_projects AS plan_max_projects, p.description AS plan_description
                 FROM users u
                 LEFT JOIN plans p ON p.id = u.plan_id
                 WHERE u.id = ?",
                [$userId]
            );
            if (!$row) {
                self::logout();
                return null;
            }
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                self::logout();
                return null;
            }
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'plan' => $row['plan_id'] !== null ? [
                    'id' => (int) $row['plan_id'],
                    'name' => $row['plan_name'],
                    'monthlyLimit' => (int) ($row['plan_limit'] ?? 0),
                    'maxProjects' => (int) ($row['plan_max_projects'] ?? 0),
                    'description' => $row['plan_description'],
                ] : null,
            ];
        } catch (Throwable $e) {
            Logger::error('UserAuth::currentUser falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cantidad de audits que este user lanzó en el mes calendario vigente
     * (desde el día 1 a las 00:00). Los audits con user_id=NULL (scans
     * públicos) no cuentan.
     */
    public static function currentMonthAuditCount(int $userId): int {
        try {
            $db = Database::getInstance();
            return (int) $db->scalar(
                "SELECT COUNT(*) FROM audits WHERE user_id = ? AND created_at >= datetime('now', 'start of month')",
                [$userId]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Cuota del user: {used, limit, remaining, unlimited}.
     * limit=0 se interpreta como "ilimitado" (flag explícito para el admin).
     */
    public static function quota(int $userId, int $monthlyLimit): array {
        $used = self::currentMonthAuditCount($userId);
        $unlimited = $monthlyLimit === 0;
        return [
            'used' => $used,
            'limit' => $monthlyLimit,
            'remaining' => $unlimited ? null : max(0, $monthlyLimit - $used),
            'unlimited' => $unlimited,
        ];
    }

    /** Cierra solo la sesión de user (deja intacta la de admin). */
    public static function logout(): void {
        self::ensureSession();
        unset(
            $_SESSION['user_authenticated'],
            $_SESSION['user_id'],
            $_SESSION['user_login_time'],
            $_SESSION['user_csrf_token']
        );
    }
}
