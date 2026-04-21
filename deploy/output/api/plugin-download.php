<?php
/**
 * GET /api/plugin-download.php?slug=wp-snapshot
 *
 * Descarga pública del último ZIP del plugin. Sin autenticación —
 * el operador comparte este link con el cliente para que instale el
 * plugin sin tener que ir a GitHub.
 *
 * Si no hay un ZIP cacheado todavía, intenta descargarlo de GitHub en
 * el momento (primera vez). Si falla, redirige a GitHub como fallback.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Método no permitido');
}

$slug = (string) ($_GET['slug'] ?? '');
$catalog = PluginVault::catalog();
if (!isset($catalog[$slug])) {
    http_response_code(404);
    exit('Plugin no encontrado');
}

$path = PluginVault::getZipPath($slug);

// Primera vez: intentar descargar bajo demanda
if ($path === null) {
    set_time_limit(60);
    PluginVault::refresh($slug);
    $path = PluginVault::getZipPath($slug);
}

// Si aun así no hay ZIP local, redirigir a GitHub como fallback
if ($path === null || !is_file($path)) {
    $githubRepo = $catalog[$slug]['githubRepo'];
    header("Location: https://github.com/$githubRepo/archive/refs/heads/main.zip", true, 302);
    exit;
}

$meta = PluginVault::getMetadata($slug);
$version = $meta['version'] ?? 'latest';
$cleanVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
$downloadName = "$slug-$cleanVersion.zip";

// Servir el archivo binario
$size = filesize($path);
header('Content-Type: application/zip');
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Cache-Control: public, max-age=300, must-revalidate');
header('X-Content-Type-Options: nosniff');

// readfile en chunks por si el ZIP es grande (evita exceder memory_limit)
$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Error abriendo archivo');
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    @ob_flush();
    @flush();
}
fclose($fp);
exit;
