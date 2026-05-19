<?php
/**
 * POST /api/audit — Arranca una auditoría (con control de concurrencia).
 *
 * Flujo:
 *   1. Valida URL, rate limit, consulta cache.
 *   2. Si hay cache <24h → 200 con el AuditResult (camino rápido, sin cola).
 *   3. Reserva un auditId.
 *   4. QueueManager::enqueueOrStart():
 *      - Si hay slot libre (jobs 'running' < audit_max_concurrent):
 *        marca 'running' y responde 202 con { queued: false, auditId }.
 *      - Si no: encola como 'queued' y responde 202 con
 *        { queued: true, auditId, position, totalInQueue }.
 *   5. Cierra HTTP con fastcgi_finish_request().
 *   6. Solo si obtuvo slot 'running': ejecuta el audit. Al terminar,
 *      llama a QueueManager::drain() para procesar los siguientes
 *      de la cola hasta que no queden slots o se acabe el tiempo PHP.
 *      Los requests 'queued' simplemente salen — serán procesados por
 *      el request que libere el slot.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

// Sin set_time_limit grande — esta request solo encola y responde.
// El scan real corre en un worker separado (drain-queue.php).
ini_set('memory_limit', '128M');

$body = Response::getJsonBody();
$url = trim($body['url'] ?? '');
if (empty($url)) {
    Response::error(Translator::t('api.audit.url_required'));
}
try {
    $url = UrlValidator::validate($url);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage());
}

// Idioma activo — determina las traducciones del resultado. Se guarda en
// `audits.lang` para que el cache respete el idioma (pedir la misma URL en
// otro idioma dispara un audit nuevo).
$lang = strtolower(substr(trim($body['lang'] ?? ''), 0, 2));
if (!in_array($lang, Translator::supported(), true)) {
    $lang = Translator::DEFAULT_LANG;
}
Translator::setLang($lang);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Detectar quién dispara el audit: admin, user logged-in, o anónimo.
// Cada uno tiene su propia regla de throttle (abajo).
$isAdmin = Auth::checkAuth();
$authUser = !$isAdmin ? UserAuth::currentUser() : null;

// Una vez leídos los datos de sesión, la liberamos. Si NO la cerramos
// aquí, el lock de sesión se mantiene durante todo el procesamiento de
// esta request (cache lookup, queue insert, response) y, peor todavía
// pre-queue-only, durante todo el scan inline — bloqueando cualquier
// otra request del mismo browser (admin/dashboard, navegación, etc.).
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Resolver parámetros de cuota / rate-limit sin aplicarlos todavía —
// los aplicamos después del cache lookup, porque los cache hits no
// consumen cuota ni slots de IP (son gratis: no corren scan).
$maxPerHour = (int) env('RATE_LIMIT_MAX_PER_HOUR', '10');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE `key` = 'rate_limit_max_per_hour'");
    if ($row && is_numeric($row['value'])) $maxPerHour = (int) $row['value'];
} catch (Throwable $e) {}

// Early-fail: si el user no tiene plan asignado no puede auditar (ni
// cacheado tiene sentido porque no hay contexto donde registrarlo).
if ($authUser) {
    $plan = $authUser['plan'];
    if (!$plan) {
        Response::error(Translator::t('user_api.quota.no_plan'), 403);
    }
}

// Detectar proyecto matchable ANTES del cache lookup — lo necesitamos
// tanto para el flujo fresco como para atar un audit cacheado al proyecto
// si hacía falta. Model A: match por URL exacta normalizada.
$projectId = null;
if ($authUser) {
    $projectId = Project::findMatchingProject(Database::getInstance(), (int) $authUser['id'], $url);
}

// Housekeeping del rate limits, sin aplicar throttle todavía.
try {
    $db = Database::getInstance();
    $db->execute("DELETE FROM rate_limits WHERE request_time < ?", [$db->nowMinus(3600)]);
} catch (Throwable $e) { /* no crítico */ }

// Cache lookup
$forceRefresh = !empty($body['forceRefresh']);
$cacheTtl = (int) env('CACHE_TTL_SECONDS', '86400');
try {
    $row = Database::getInstance()->queryOne("SELECT value FROM settings WHERE `key` = 'cache_ttl_seconds'");
    if ($row && is_numeric($row['value'])) $cacheTtl = (int) $row['value'];
} catch (Throwable $e) {}

try {
    if (!$forceRefresh) {
        $db = Database::getInstance();
        // Cache scope:
        //   - Admin: cualquier audit (útil para QA / testing de features).
        //   - User autenticado: solo audits propios o anónimos (NULL user_id).
        //     Nunca devolvemos al user A un audit que hizo el user B — evita
        //     leak de lead_email y otros datos sensibles entre cuentas.
        //   - Anónimo: solo audits anónimos. Los que tienen dueño no se
        //     sirven desde cache al público.
        $cacheThreshold = $db->nowMinus($cacheTtl);
        if ($isAdmin) {
            $cached = $db->queryOne(
                "SELECT * FROM audits
                 WHERE url = ? AND lang = ?
                   AND created_at > ? AND is_deleted = 0
                 ORDER BY created_at DESC LIMIT 1",
                [$url, $lang, $cacheThreshold]
            );
        } elseif ($authUser) {
            $cached = $db->queryOne(
                "SELECT * FROM audits
                 WHERE url = ? AND lang = ?
                   AND created_at > ? AND is_deleted = 0
                   AND (user_id = ? OR user_id IS NULL)
                 ORDER BY created_at DESC LIMIT 1",
                [$url, $lang, $cacheThreshold, (int) $authUser['id']]
            );
        } else {
            $cached = $db->queryOne(
                "SELECT * FROM audits
                 WHERE url = ? AND lang = ?
                   AND created_at > ? AND is_deleted = 0
                   AND user_id IS NULL
                 ORDER BY created_at DESC LIMIT 1",
                [$url, $lang, $cacheThreshold]
            );
        }

        if ($cached) {
            $result = JsonStore::decode($cached['result_json']);

            $leadName = trim($body['leadName'] ?? '');
            $leadEmail = trim($body['leadEmail'] ?? '');
            $leadWhatsapp = trim($body['leadWhatsapp'] ?? '');
            $leadCompany = trim($body['leadCompany'] ?? '');
            if ($leadName || $leadEmail || $leadWhatsapp || $leadCompany) {
                $db->execute(
                    "UPDATE audits SET lead_name = COALESCE(NULLIF(?, ''), lead_name), lead_email = COALESCE(NULLIF(?, ''), lead_email), lead_whatsapp = COALESCE(NULLIF(?, ''), lead_whatsapp), lead_company = COALESCE(NULLIF(?, ''), lead_company) WHERE id = ?",
                    [$leadName, $leadEmail, $leadWhatsapp, $leadCompany, $cached['id']]
                );
            }

            // Atribuir cache hit al user actual y al proyecto si corresponde.
            // Reclamamos audits anónimos (user_id era NULL) para que queden en
            // el histórico del user, y seteamos project_id si el proyecto
            // existía ahora pero no cuando se cacheó el audit.
            if ($authUser) {
                $cachedUserId = $cached['user_id'] !== null ? (int) $cached['user_id'] : null;
                $cachedProjectId = $cached['project_id'] !== null ? (int) $cached['project_id'] : null;
                $needsUserId = $cachedUserId === null;
                $needsProjectId = $projectId !== null && $cachedProjectId === null;
                if ($needsUserId || $needsProjectId) {
                    $db->execute(
                        "UPDATE audits SET user_id = COALESCE(user_id, ?), project_id = COALESCE(project_id, ?) WHERE id = ?",
                        [(int) $authUser['id'], $projectId, $cached['id']]
                    );
                    // Si acabamos de atar el audit a un proyecto, reconciliamos
                    // el checklist vivo para que el user lo vea actualizado al
                    // volver al proyecto.
                    if ($needsProjectId && $projectId !== null && is_array($result)) {
                        try {
                            Project::reconcileChecklist($db, $projectId, Project::flattenMetrics($result));
                        } catch (Throwable $e) {
                            Logger::warning('reconcileChecklist post-cache falló: ' . $e->getMessage());
                        }
                    }
                }
            }

            Response::success([
                'cached' => true,
                'auditId' => $cached['id'],
                'result' => $result,
            ]);
        }
    }
} catch (Throwable $e) {
    Logger::error('Error consultando cache: ' . $e->getMessage());
}

// A partir de acá, sabemos que es cache miss y vamos a correr scan fresco.
// Aplicamos throttle:
//   - user: cuota mensual (free quota >= 1 o plan ilimitado)
//   - anon: IP rate-limit, y consumimos un slot
//   - admin: sin límites
try {
    if ($authUser) {
        $plan = $authUser['plan']; // ya validamos que existe arriba
        $quota = UserAuth::quota($authUser['id'], (int) $plan['monthlyLimit']);
        if (!$quota['unlimited'] && $quota['remaining'] !== null && $quota['remaining'] <= 0) {
            Response::error(Translator::t('user_api.quota.exceeded', [
                'used'  => $quota['used'],
                'limit' => $quota['limit'],
            ]), 429);
        }
    } elseif (!$isAdmin) {
        $db = Database::getInstance();
        $count = (int) $db->scalar(
            "SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND endpoint = 'audit'",
            [$ip]
        );
        if ($count >= $maxPerHour) {
            Response::error(Translator::t('api.audit.rate_limit'), 429);
        }
        $db->execute("INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, 'audit')", [$ip]);
    }
} catch (Throwable $e) {
    Logger::error('Error aplicando throttle: ' . $e->getMessage());
}

// Circuit breaker: si esta URL acaba de fallar, devolvemos el mismo error
// sin reprocesar. Evita loops de retry y ahorra cuota de APIs externas.
$recentError = QueueManager::findRecentFailure($url);
if ($recentError !== null && !$forceRefresh) {
    Response::error($recentError, 422);
}

// Encolar o ejecutar directo
$auditId = AuditOrchestrator::generateUuid();

// $projectId ya se calculó arriba (antes del cache lookup) para que el
// flujo cacheado también pudiera atar el audit al proyecto.

$leadData = [
    'leadName' => $body['leadName'] ?? '',
    'leadEmail' => $body['leadEmail'] ?? '',
    'leadWhatsapp' => $body['leadWhatsapp'] ?? '',
    'leadCompany' => $body['leadCompany'] ?? '',
    // userId y projectId se persisten en audits.* para que el histórico,
    // cuota mensual y timeline del proyecto sean exactos tanto si el audit
    // corre inline como si fue procesado por el drain worker.
    'userId' => $authUser ? $authUser['id'] : null,
    'projectId' => $projectId,
];

// Arquitectura queue-only: SIEMPRE encolamos, nunca corremos el scan
// inline en este worker web. El scan vive en drain-queue.php (cron +
// kick async), lo que libera el worker que atendió /audit en
// milisegundos. Beneficios:
//   - Admin / dashboard / cualquier otra navegación NO compite por
//     workers PHP-FPM ni por sesión PHP con un scan de 30-45s.
//   - Si el host es estrecho (1-2 workers) el panel sigue responsive.
//   - El scan tiene su propio tiempo / memoria / progreso aislado.
$slot = QueueManager::enqueue($auditId, $url, $leadData, $ip);
AuditProgress::queued($auditId, $slot['position'], QueueManager::queuedCount());

$responseBody = json_encode([
    'success' => true,
    'data' => [
        'cached' => false,
        'auditId' => $auditId,
        'queued' => true,
        'position' => $slot['position'],
        'totalInQueue' => QueueManager::queuedCount(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

http_response_code(202);
header('Content-Type: application/json; charset=utf-8');
header('Content-Length: ' . strlen($responseBody));
header('Connection: close');
echo $responseBody;

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    @ob_end_flush();
    @flush();
}

// Kick async al drain — fire-and-forget. Si shell_exec está disponible
// arranca un PHP CLI en background; si no, hace un curl HTTP con timeout
// muy bajo (el server-side script mantiene la ejecución gracias a
// ignore_user_abort). En el peor caso, el cron drena en <1 minuto.
QueueManager::kickDrain();
