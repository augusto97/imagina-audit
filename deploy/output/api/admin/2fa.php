<?php
/**
 * 2FA TOTP admin endpoint.
 *
 * GET  /admin/2fa.php                  → status { enabled, hasRecoveryCodes }
 * POST /admin/2fa.php?action=setup     → { secret, otpauthUri }
 *        genera un secret nuevo y su URI para QR. No lo guarda aún:
 *        el cliente debe pasar el secret + un código correcto al enable.
 * POST /admin/2fa.php?action=enable    → body { secret, code }
 *        valida el código contra el secret, guarda el secret en settings,
 *        genera y devuelve recovery codes (una sola vez).
 * POST /admin/2fa.php?action=disable   → body { password, code }
 *        exige password + TOTP válido o recovery code, luego limpia.
 *
 * Requiere auth admin + CSRF (Auth::requireAuth lo maneja).
 */

require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();

// ─── Helpers internos ───────────────────────────────────────────────

function twoFaGetSetting(string $key): ?string {
    global $db;
    try {
        $row = $db->queryOne("SELECT value FROM settings WHERE key = ?", [$key]);
        return $row ? (string) $row['value'] : null;
    } catch (Throwable $e) { return null; }
}

function twoFaSetSetting(string $key, string $value): void {
    global $db;
    $db->execute(
        "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
        [$key, $value]
    );
}

function twoFaDeleteSetting(string $key): void {
    global $db;
    $db->execute("DELETE FROM settings WHERE key = ?", [$key]);
}

function twoFaVerifyPassword(string $password): bool {
    $hash = twoFaGetSetting('admin_password_hash') ?? env('ADMIN_PASSWORD_HASH', '');
    if (empty($hash)) return false;
    return password_verify($password, $hash);
}

/**
 * Consume un recovery code si es válido. Devuelve true si coincide
 * con alguno de los hashes almacenados (y lo remueve del set).
 */
function twoFaConsumeRecoveryCode(string $code): bool {
    $stored = twoFaGetSetting('admin_2fa_recovery_codes');
    if (empty($stored)) return false;
    $hashes = json_decode($stored, true);
    if (!is_array($hashes)) return false;

    $targetHash = Totp::hashRecoveryCode($code);
    $idx = array_search($targetHash, $hashes, true);
    if ($idx === false) return false;

    array_splice($hashes, (int) $idx, 1);
    twoFaSetSetting('admin_2fa_recovery_codes', json_encode($hashes));
    return true;
}

// ─── GET status ─────────────────────────────────────────────────────

if ($method === 'GET') {
    $enabled = twoFaGetSetting('admin_2fa_enabled') === '1';
    $codes = twoFaGetSetting('admin_2fa_recovery_codes');
    $codesLeft = 0;
    if ($codes) {
        $decoded = json_decode($codes, true);
        if (is_array($decoded)) $codesLeft = count($decoded);
    }

    Response::success([
        'enabled'          => $enabled,
        'recoveryCodesLeft' => $codesLeft,
    ]);
}

// ─── POST actions ───────────────────────────────────────────────────

if ($method !== 'POST') {
    Response::error('Método no permitido', 405);
}

$action = $_GET['action'] ?? '';
$body = Response::getJsonBody();

// ─── action=setup ───────────────────────────────────────────────────
// Genera un secret nuevo (no lo guarda). El cliente lo usa para
// mostrar el QR y luego lo pasa a enable junto con un código válido.

if ($action === 'setup') {
    if (twoFaGetSetting('admin_2fa_enabled') === '1') {
        Response::error('El 2FA ya está habilitado. Desactívalo primero si quieres re-configurar.', 409);
    }

    $secret = Totp::generateSecret();
    $defaults = require dirname(__DIR__, 2) . '/config/defaults.php';
    $issuer = $defaults['company_name'] ?? 'Imagina Audit';
    $label = 'admin';

    $uri = Totp::otpauthUri($secret, $label, $issuer);

    Response::success([
        'secret'     => $secret,
        'otpauthUri' => $uri,
        'issuer'     => $issuer,
        'label'      => $label,
    ]);
}

// ─── action=enable ──────────────────────────────────────────────────

if ($action === 'enable') {
    if (twoFaGetSetting('admin_2fa_enabled') === '1') {
        Response::error('El 2FA ya está habilitado.', 409);
    }

    $secret = trim((string) ($body['secret'] ?? ''));
    $code = trim((string) ($body['code'] ?? ''));

    if (empty($secret) || empty($code)) {
        Response::error('secret y code son obligatorios', 400);
    }

    if (!Totp::verify($secret, $code)) {
        Response::error('Código inválido. Revisa la hora de tu dispositivo y vuelve a intentarlo.', 401);
    }

    // Generar recovery codes antes de guardar
    $recoveryCodes = Totp::generateRecoveryCodes(8);
    $hashes = array_map([Totp::class, 'hashRecoveryCode'], $recoveryCodes);

    twoFaSetSetting('admin_2fa_secret', $secret);
    twoFaSetSetting('admin_2fa_recovery_codes', json_encode($hashes));
    twoFaSetSetting('admin_2fa_enabled', '1');

    Logger::info('2FA habilitado para admin');

    Response::success([
        'enabled'       => true,
        'recoveryCodes' => $recoveryCodes,   // Se muestra una sola vez
    ]);
}

// ─── action=disable ─────────────────────────────────────────────────

if ($action === 'disable') {
    if (twoFaGetSetting('admin_2fa_enabled') !== '1') {
        Response::success(['enabled' => false]);
    }

    $password = (string) ($body['password'] ?? '');
    $code = trim((string) ($body['code'] ?? ''));

    if (empty($password) || empty($code)) {
        Response::error('password y code son obligatorios', 400);
    }

    if (!twoFaVerifyPassword($password)) {
        Response::error('Contraseña incorrecta', 401);
    }

    // Aceptar TOTP o recovery code
    $secret = twoFaGetSetting('admin_2fa_secret') ?? '';
    $totpValid = !empty($secret) && Totp::verify($secret, $code);
    $recoveryValid = !$totpValid && twoFaConsumeRecoveryCode($code);

    if (!$totpValid && !$recoveryValid) {
        Response::error('Código 2FA o recovery code inválido', 401);
    }

    twoFaDeleteSetting('admin_2fa_enabled');
    twoFaDeleteSetting('admin_2fa_secret');
    twoFaDeleteSetting('admin_2fa_recovery_codes');

    Logger::info('2FA deshabilitado para admin');

    Response::success(['enabled' => false]);
}

// ─── action=regenerate-recovery ─────────────────────────────────────
// Tira los recovery codes actuales y genera nuevos. Requiere TOTP válido
// para evitar que alguien con cookie de sesión robada los regenere.

if ($action === 'regenerate-recovery') {
    if (twoFaGetSetting('admin_2fa_enabled') !== '1') {
        Response::error('2FA no está habilitado', 409);
    }
    $code = trim((string) ($body['code'] ?? ''));
    $secret = twoFaGetSetting('admin_2fa_secret') ?? '';
    if (empty($code) || !Totp::verify($secret, $code)) {
        Response::error('Código TOTP inválido', 401);
    }

    $recoveryCodes = Totp::generateRecoveryCodes(8);
    $hashes = array_map([Totp::class, 'hashRecoveryCode'], $recoveryCodes);
    twoFaSetSetting('admin_2fa_recovery_codes', json_encode($hashes));

    Response::success(['recoveryCodes' => $recoveryCodes]);
}

Response::error('Acción desconocida', 400);
