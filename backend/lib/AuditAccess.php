<?php
/**
 * Helper de acceso compartido admin-o-dueño para endpoints que exponen
 * datos de un audit (lead-detail, snapshot, snapshot-report, waterfall,
 * checklist GET).
 *
 * Regla: accede el admin (Auth::checkAuth) o el user autenticado cuyo
 * audits.user_id coincide con el $_SESSION['user_id']. Cualquier otro
 * caso → 401.
 *
 * CSRF: si la request muta (POST/PUT/DELETE/PATCH), se valida contra el
 * token de quien sea que esté autenticado (admin o user). Si no hay
 * token válido → 403.
 */

class AuditAccess {
    /**
     * Autoriza el acceso al audit. Responde 401/403 si el caller no es
     * admin ni dueño del audit.
     */
    public static function require(string $auditId): void {
        $isAdmin = Auth::checkAuth();
        $user = null;
        if (!$isAdmin) {
            $user = UserAuth::checkAuth() ? UserAuth::currentUser() : null;
        }

        if (!$isAdmin && !$user) {
            Response::error(Translator::t('user_api.not_authenticated'), 401);
        }

        // Si no es admin, verificar que el audit le pertenece.
        if (!$isAdmin) {
            try {
                $db = Database::getInstance();
                $row = $db->queryOne(
                    "SELECT user_id FROM audits WHERE id = ?",
                    [$auditId]
                );
                if (!$row) {
                    Response::error(Translator::t('api.audit.not_found'), 404);
                }
                $ownerId = $row['user_id'] !== null ? (int) $row['user_id'] : null;
                if ($ownerId === null || $ownerId !== (int) $user['id']) {
                    Response::error(Translator::t('projects.not_owner'), 403);
                }
            } catch (Throwable $e) {
                Logger::error('AuditAccess::require falló: ' . $e->getMessage());
                Response::error(Translator::t('api.audit.fetch_error'), 500);
            }
        }

        // CSRF cuando aplica. Admin usa Auth::verifyCsrf (csrf_token), user
        // usa UserAuth::verifyCsrf (user_csrf_token).
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if ($isAdmin) {
                Auth::verifyCsrf();
            } else {
                UserAuth::verifyCsrf();
            }
        }
    }
}
