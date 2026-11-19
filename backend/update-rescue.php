<?php
/**
 * update-rescue.php — vuelta atrás de emergencia del actualizador.
 *
 * Vive en la raíz y el actualizador NUNCA lo mueve durante un cambio de
 * versión, y es 100 % autónomo (no carga nada de lib/ ni config/), así que
 * funciona aunque la versión recién instalada no arranque.
 *
 * Solo actúa si hay un cambio de versión sin finalizar (estado "switched")
 * y además:
 *   - se presenta el token de rescate de esa actualización (lo usa el
 *     panel de actualizaciones automáticamente), o
 *   - pasaron más de 15 minutos sin que la versión nueva se finalizara
 *     (si la versión nueva funciona, se finaliza sola en su primera
 *     petición y este script deja de poder actuar).
 *
 * Deshace exactamente los renombres registrados en el journal.
 */
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$root = __DIR__;
$candidates = [];
$envFile = "$root/.env";
if (is_file($envFile) && preg_match('/^\s*UPDATE_WORK_DIR\s*=\s*["\']?([^"\'\r\n#]+)/m', (string) file_get_contents($envFile), $m)) {
    $candidates[] = rtrim(trim($m[1]), '/');
}
$candidates[] = dirname($root) . '/imagina_audit_data/updates';
$candidates[] = "$root/storage/updates";

$json = isset($_GET['format']) && $_GET['format'] === 'json';
$respond = function (int $code, string $msg, bool $ok) use ($json) {
    http_response_code($code);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Rescate de actualización</title><body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 16px;color:#222">'
            . '<h2>' . ($ok ? '✅' : 'ℹ️') . ' Rescate de actualización</h2><p>' . htmlspecialchars($msg) . '</p>'
            . ($ok ? '<p><a href="/admin/updates">Volver al panel</a></p>' : '') . '</body>';
    }
    exit;
};

$workDir = null;
foreach ($candidates as $c) {
    if (is_file("$c/state.json")) { $workDir = $c; break; }
}
if (!$workDir) $respond(404, 'No hay ninguna actualización registrada.', false);

$lock = fopen("$workDir/update.lock", 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) $respond(409, 'Hay un paso de actualización ejecutándose. Inténtalo en unos segundos.', false);

$state = json_decode((string) file_get_contents("$workDir/state.json"), true);
if (!is_array($state) || ($state['status'] ?? '') !== 'switched') {
    $respond(409, 'No hay un cambio de versión pendiente de rescatar (la actualización ya terminó o no llegó a cambiar de versión).', false);
}
$token = (string) ($_GET['token'] ?? '');
$tokenOk = $token !== '' && !empty($state['rescueToken']) && hash_equals((string) $state['rescueToken'], $token);
$expired = (time() - (int) ($state['switchedAt'] ?? time())) > 900;
if (!$tokenOk && !$expired) {
    $respond(403, 'Para volver atrás antes de 15 minutos se necesita el token del panel de actualizaciones.', false);
}

$errors = [];
foreach (array_reverse((array) ($state['journal'] ?? [])) as $pair) {
    [$from, $to] = $pair;
    // Solo rutas dentro de la raíz o de la carpeta de trabajo.
    foreach ([$from, $to] as $p) {
        if (!str_starts_with($p, $root . '/') && !str_starts_with($p, $workDir . '/')) {
            $respond(500, 'El journal contiene rutas inesperadas; no se tocó nada.', false);
        }
    }
    if (!@rename($to, $from)) $errors[] = basename($to);
}
if (function_exists('opcache_reset')) @opcache_reset();
clearstatcache(true);

$state['status'] = 'rolled_back';
$state['error'] = 'La versión ' . ($state['toVersion'] ?? '?') . ' no arrancó; se volvió a la ' . ($state['fromVersion'] ?? '?') . ' con el rescate.'
    . ($errors ? ' No se pudieron devolver: ' . implode(', ', $errors) : '');
$state['finishedAt'] = time();
unset($state['opsToken'], $state['rescueToken']);
$state['log'][] = ['at' => time(), 'step' => 'rescue', 'level' => $errors ? 'error' : 'warn', 'msg' => $state['error']];
@unlink("$workDir/.finalize-pending");
file_put_contents("$workDir/state.json", json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$history = json_decode((string) @file_get_contents("$workDir/history.json"), true) ?: [];
array_unshift($history, ['id' => $state['id'] ?? '', 'kind' => $state['kind'] ?? 'manual', 'from' => $state['fromVersion'] ?? '', 'to' => $state['toVersion'] ?? '',
    'status' => 'rolled_back', 'startedAt' => $state['startedAt'] ?? null, 'finishedAt' => time(), 'error' => $state['error']]);
@file_put_contents("$workDir/history.json", json_encode(array_slice($history, 0, 30), JSON_UNESCAPED_UNICODE));

flock($lock, LOCK_UN);
$respond($errors ? 500 : 200, $state['error'], !$errors);
