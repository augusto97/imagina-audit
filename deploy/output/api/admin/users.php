<?php
/**
 * CRUD de usuarios (admin-only). No hay auto-registro: el admin crea las
 * cuentas aquí y le comparte las credenciales al dueño.
 *
 *   GET    /admin/users.php[?search=X&plan=N&active=yes|no]
 *          Lista paginada con uso del mes y nombre del plan.
 *   POST   /admin/users.php
 *          Crea user. Body: {email, password, name?, planId?, isActive?}.
 *          password debe tener >= 10 chars; se hashea con bcrypt.
 *   PUT    /admin/users.php
 *          Body: {id, email?, name?, planId?, isActive?, password?}.
 *          Todos los campos son opcionales salvo id; si llega `password`
 *          (>= 10 chars) se rehashea.
 *   DELETE /admin/users.php?id=N
 *          Borra el user. Los audits con user_id=N quedan user_id=NULL
 *          (FK ON DELETE SET NULL) para no perder el histórico del admin.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $planFilter = isset($_GET['plan']) ? (int) $_GET['plan'] : null;
    $activeFilter = $_GET['active'] ?? 'any';  // any|yes|no

    $where = '1=1';
    $params = [];
    if ($search !== '') {
        $where .= " AND (u.email LIKE ? OR u.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($planFilter !== null && $planFilter > 0) {
        $where .= " AND u.plan_id = ?";
        $params[] = $planFilter;
    }
    if ($activeFilter === 'yes') {
        $where .= " AND u.is_active = 1";
    } elseif ($activeFilter === 'no') {
        $where .= " AND u.is_active = 0";
    }

    try {
        $rows = $db->query(
            "SELECT u.id, u.email, u.name, u.plan_id, u.is_active, u.created_at, u.last_login_at,
                    p.name AS plan_name, p.monthly_limit AS plan_limit,
                    (SELECT COUNT(*) FROM audits a WHERE a.user_id = u.id
                         AND a.created_at >= datetime('now', 'start of month')) AS month_used
             FROM users u
             LEFT JOIN plans p ON p.id = u.plan_id
             WHERE $where
             ORDER BY u.created_at DESC",
            $params
        );
        $users = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'email' => $r['email'],
            'name' => $r['name'],
            'planId' => $r['plan_id'] !== null ? (int) $r['plan_id'] : null,
            'planName' => $r['plan_name'],
            'planLimit' => $r['plan_limit'] !== null ? (int) $r['plan_limit'] : null,
            'isActive' => (int) $r['is_active'] === 1,
            'monthUsed' => (int) $r['month_used'],
            'createdAt' => $r['created_at'],
            'lastLoginAt' => $r['last_login_at'],
        ], $rows);
        Response::success(['users' => $users, 'total' => count($users)]);
    } catch (Throwable $e) {
        Logger::error('admin/users GET falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.users.fetch_error'), 500);
    }
}

/**
 * Valida formato + unicidad del email. Retorna el email normalizado o null.
 * Si duplicado, responde 409 y termina el request (no vuelve).
 */
function validateUserEmail(Database $db, string $emailRaw, ?int $excludeId = null): string {
    $email = strtolower(trim($emailRaw));
    if ($email === '') {
        Response::error(Translator::t('admin_api.users.email_required'), 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error(Translator::t('admin_api.users.email_invalid'), 400);
    }
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $existing = $db->queryOne($sql, $params);
    if ($existing) {
        Response::error(Translator::t('admin_api.users.email_exists'), 409);
    }
    return $email;
}

if ($method === 'POST') {
    $body = Response::getJsonBody();
    $email = validateUserEmail($db, (string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $name = trim((string) ($body['name'] ?? ''));
    $planId = isset($body['planId']) ? (int) $body['planId'] : null;
    $isActive = array_key_exists('isActive', $body) ? (!empty($body['isActive']) ? 1 : 0) : 1;

    if (strlen($password) < 10) {
        Response::error(Translator::t('admin_api.users.password_too_short'), 400);
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->execute(
            "INSERT INTO users (email, password_hash, name, plan_id, is_active) VALUES (?, ?, ?, ?, ?)",
            [$email, $hash, $name !== '' ? $name : null, $planId, $isActive]
        );
        Response::success(['id' => (int) $db->lastInsertId()], 201);
    } catch (Throwable $e) {
        Logger::error('admin/users POST falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.users.create_error'), 500);
    }
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $id = (int) ($body['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('admin_api.common.id_required'), 400);

    try {
        $existing = $db->queryOne("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$existing) Response::error(Translator::t('admin_api.users.not_found'), 404);

        $fields = [];
        $params = [];

        if (array_key_exists('email', $body)) {
            $email = validateUserEmail($db, (string) $body['email'], $id);
            $fields[] = "email = ?";
            $params[] = $email;
        }
        if (array_key_exists('name', $body)) {
            $name = trim((string) $body['name']);
            $fields[] = "name = ?";
            $params[] = $name !== '' ? $name : null;
        }
        if (array_key_exists('planId', $body)) {
            $fields[] = "plan_id = ?";
            $params[] = $body['planId'] !== null && $body['planId'] !== '' ? (int) $body['planId'] : null;
        }
        if (array_key_exists('isActive', $body)) {
            $fields[] = "is_active = ?";
            $params[] = !empty($body['isActive']) ? 1 : 0;
        }
        if (array_key_exists('password', $body) && (string) $body['password'] !== '') {
            $password = (string) $body['password'];
            if (strlen($password) < 10) {
                Response::error(Translator::t('admin_api.users.password_too_short'), 400);
            }
            $fields[] = "password_hash = ?";
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            Response::success(['ok' => true]);
        }

        $params[] = $id;
        $db->execute("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/users PUT falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.users.update_error'), 500);
    }
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id === 0) Response::error(Translator::t('admin_api.common.id_required'), 400);

    try {
        // SQLite no aplica ON DELETE SET NULL automáticamente — lo hacemos explícito
        $db->execute("UPDATE audits SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("DELETE FROM users WHERE id = ?", [$id]);
        Response::success(['ok' => true]);
    } catch (Throwable $e) {
        Logger::error('admin/users DELETE falló: ' . $e->getMessage());
        Response::error(Translator::t('admin_api.users.delete_error'), 500);
    }
}

Response::error(Translator::t('api.common.method_not_allowed'), 405);
