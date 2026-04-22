<?php
/**
 * POST /api/admin/upload
 *
 * Upload de assets de branding (logo, logo colapsado, favicon).
 *
 * Body: multipart/form-data con
 *   - `type`: 'logo' | 'logo_collapsed' | 'favicon'
 *   - `file`: binario JPG o PNG (≤ 2 MB)
 *
 * El archivo se guarda en `public_html/uploads/` con nombre hasheado
 * (evita colisiones + cache-busting), y la setting correspondiente
 * (`logo_url`, `logo_collapsed_url`, `favicon_url`) se actualiza con
 * la ruta pública relativa (p. ej. `/uploads/logo-ab12cd34.png`).
 *
 * Nota de seguridad: solo aceptamos JPG y PNG — el MIME se valida con
 * finfo_file (no confiamos en Content-Type del cliente) y el nombre
 * se regenera por completo con un hash aleatorio.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$type = $_POST['type'] ?? '';
$validTypes = [
    'logo'           => 'logo_url',
    'logo_collapsed' => 'logo_collapsed_url',
    'favicon'        => 'favicon_url',
];
if (!isset($validTypes[$type])) {
    Response::error(Translator::t('admin_api.upload.asset_type_invalid'), 400);
}

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    Response::error(Translator::t('admin_api.upload.no_file'), 400);
}

$file = $_FILES['file'];
$maxBytes = 2 * 1024 * 1024; // 2 MB
if ($file['size'] > $maxBytes) {
    Response::error(Translator::t('admin_api.upload.file_too_big'), 400);
}

// Validar MIME real del contenido, no solo el Content-Type del cliente
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
];

// Favicon también acepta .ico, pero nos limitamos a PNG (mismas browsers lo renderizan bien)
if (!isset($allowed[$mime])) {
    Response::error(Translator::t('admin_api.upload.bad_format'), 400);
}
$ext = $allowed[$mime];

// Directorio de destino: una carpeta pública bajo la raíz del hosting.
// En el deploy, `public_html/audit/` es la raíz web, así que `uploads/`
// queda directamente accesible vía `/audit/uploads/...`.
$publicRoot = dirname(__DIR__, 2);       // backend/ → audit root
$uploadsDir = $publicRoot . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}
if (!is_dir($uploadsDir) || !is_writable($uploadsDir)) {
    Response::error(Translator::t('admin_api.upload.dir_error'), 500);
}

// Nombre seguro: type + hash random (evita colisiones y oculta info del uploader)
$hash = bin2hex(random_bytes(6));
$filename = "$type-$hash.$ext";
$destPath = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    Response::error(Translator::t('admin_api.upload.move_error'), 500);
}
@chmod($destPath, 0644);

// Ruta pública absoluta con detección del base path de la app.
// Si la app vive en subdominio (audit.ej.com) → $appBase = ''
// Si la app vive en subcarpeta (ej.com/audit) → $appBase = '/audit'
$script = $_SERVER['SCRIPT_NAME'] ?? '/api/admin/upload.php';
$appBase = rtrim(str_replace('\\', '/', dirname($script, 3)), '/');
if ($appBase === '' || $appBase === '.') $appBase = '';
$publicUrl = $appBase . '/uploads/' . $filename;

// Actualizar setting correspondiente
try {
    $db = Database::getInstance();
    $settingKey = $validTypes[$type];

    // Borrar archivo anterior (si existía) para no acumular basura.
    // Extraemos solo el nombre de archivo para evitar path traversal.
    $oldUrl = (string) $db->scalar("SELECT value FROM settings WHERE key = ?", [$settingKey]);
    if ($oldUrl !== '' && preg_match('#/uploads/([^/]+)$#', $oldUrl, $oldMatch)) {
        $oldPath = $uploadsDir . '/' . $oldMatch[1];
        if (is_file($oldPath)) @unlink($oldPath);
    }

    $db->execute(
        "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
        [$settingKey, $publicUrl]
    );
} catch (Throwable $e) {
    Logger::error('Error guardando URL de upload: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.upload.register_error'), 500);
}

Response::success([
    'url' => $publicUrl,
    'type' => $type,
    'filename' => $filename,
]);
