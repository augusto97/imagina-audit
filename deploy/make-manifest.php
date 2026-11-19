<?php
/**
 * Genera manifest.json en la raíz del paquete (deploy/output). Lo usa el
 * actualizador para saber qué entradas son código de la app (las únicas que
 * mueve al cambiar de versión), qué requisitos tiene la versión y si el
 * .htaccess raíz fue modificado a mano.
 *
 * Uso: php deploy/make-manifest.php deploy/output
 */
$dir = rtrim($argv[1] ?? '', '/');
if ($dir === '' || !is_dir($dir)) { fwrite(STDERR, "Uso: php make-manifest.php <dir>\n"); exit(1); }

$version = trim((string) @file_get_contents("$dir/VERSION"));
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) { fwrite(STDERR, "VERSION inválida: '$version'\n"); exit(1); }

// Entradas que el actualizador NUNCA mueve (datos del sitio, no código).
$shared = ['.env', 'cache', 'logs', 'uploads', 'storage', 'manifest.json'];
$entries = [];
foreach (scandir($dir) as $e) {
    if ($e === '.' || $e === '..' || in_array($e, $shared, true) || str_starts_with($e, '.env.') && $e !== '.env.example') continue;
    $entries[] = $e;
}
sort($entries);

$files = 0; $bytes = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if ($f->isFile()) { $files++; $bytes += $f->getSize(); } }

// Avisos por versión (cambios que el admin debe hacer a mano en el servidor)
$noticesFile = __DIR__ . '/release-notices.json';
$all = is_file($noticesFile) ? (json_decode((string) file_get_contents($noticesFile), true) ?: []) : [];
$cfg = $all[$version] ?? [];

$manifest = [
    'name' => 'imagina-audit',
    'version' => $version,
    'builtAt' => gmdate('c'),
    'minPhp' => '8.0.0',
    'requiredExtensions' => ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'zip'],
    'entries' => $entries,
    'fileCount' => $files + 1,
    'uncompressedBytes' => $bytes,
    'htaccessSha256' => is_file("$dir/.htaccess") ? hash_file('sha256', "$dir/.htaccess") : null,
    'autoInstall' => $cfg['autoInstall'] ?? true,
    'notices' => array_values($cfg['notices'] ?? []),
];
file_put_contents("$dir/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "manifest.json: v$version, " . count($entries) . " entradas, $files archivos\n";
