<?php
/**
 * GET /api/plugin-vault/download.php?slug=wp-snapshot
 *
 * Descarga pública del ZIP cacheado del plugin. No requiere auth —
 * sirve el artefacto que el admin mantiene actualizado vía
 * /admin/plugin-vault.php + el cron `refresh-plugin-vault`.
 *
 * Responde con el ZIP "latest.zip" de la carpeta del plugin. Si el
 * slug no está en el catálogo, 404. Si aún no hay archivo cacheado
 * (primer install sin refresh corrido), 503 con un mensaje claro.
 */

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
// Solo slugs que están en el catálogo — evita path traversal por ?slug=../../
$catalog = PluginVault::catalog();
if ($slug === '' || !isset($catalog[$slug])) {
    Response::error(Translator::t('admin_api.plugin_vault.unknown_plugin'), 404);
}

$status = PluginVault::status($slug);
if (empty($status['fileExists']) || empty($status['absPath'])) {
    // Puede pasar en un deploy nuevo antes del primer refresh del cron.
    Response::error(Translator::t('plugin_vault.not_ready'), 503);
}

$absPath = (string) $status['absPath'];
$filename = (string) ($status['filename'] ?? (basename($absPath)));
$size = @filesize($absPath);

if ($size === false) {
    Response::error(Translator::t('plugin_vault.not_ready'), 503);
}

// Headers: descarga directa, sin cache en el browser porque el archivo
// cambia cuando el admin hace refresh.
header('Content-Type: application/zip');
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Volcar el archivo en chunks (evita cargar todo el ZIP en memoria
// aunque los de wp-snapshot son pequeños, <500 KB).
$fh = fopen($absPath, 'rb');
if ($fh === false) {
    Response::error(Translator::t('plugin_vault.not_ready'), 503);
}
while (!feof($fh)) {
    $chunk = fread($fh, 8192);
    if ($chunk === false) break;
    echo $chunk;
}
fclose($fh);
exit;
