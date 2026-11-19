<?php
/**
 * Imagina Audit — instalador web de un solo archivo.
 *
 * Para servidores nuevos (hosting compartido / panel, sin terminal):
 *   1. Sube SOLO este archivo a la carpeta pública vacía de tu dominio.
 *   2. Ábrelo en el navegador: https://tu-dominio.com/installer.php
 *   3. Pulsa «Instalar»: descarga el último release de GitHub, verifica su
 *      sha256, lo descomprime y te lleva al asistente /setup (que crea el
 *      .env con secretos nuevos y la cuenta de administrador).
 *
 * Es seguro volver a abrirlo: si la app ya está instalada no toca nada.
 * Al terminar se borra a sí mismo.
 */
declare(strict_types=1);

const INSTALLER_REPO = 'augusto97/imagina-audit';
const REQUIRED_EXT = ['curl', 'zip', 'json', 'mbstring', 'openssl', 'pdo'];
const MIN_PHP = '8.0.0';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$root = __DIR__;
$installed = is_file("$root/index.html") && is_dir("$root/api") && is_dir("$root/lib");

function page(string $title, string $body): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title) . '</title>'
        . '<body style="font-family:system-ui,-apple-system,sans-serif;max-width:640px;margin:48px auto;padding:0 16px;color:#1f2937;line-height:1.5">'
        . '<h2>' . htmlspecialchars($title) . '</h2>' . $body . '</body></html>';
    exit;
}

/** GET HTTPS solo a hosts de GitHub, redirecciones validadas a mano. */
function gh_get(string $url, int $maxBytes, $sink = null): string
{
    for ($hop = 0; $hop < 6; $hop++) {
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (($p['scheme'] ?? '') !== 'https' || !($host === 'api.github.com' || $host === 'github.com' || str_ends_with($host, '.githubusercontent.com'))) {
            throw new RuntimeException("Host no permitido: $host");
        }
        $body = ''; $got = 0; $location = null; $tooBig = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 180,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['User-Agent: ImaginaAudit-Installer', 'Accept: application/vnd.github+json, application/octet-stream'],
            CURLOPT_HEADERFUNCTION => function ($ch, $l) use (&$location) { if (stripos($l, 'location:') === 0) $location = trim(substr($l, 9)); return strlen($l); },
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$got, &$tooBig, $maxBytes, $sink) {
                $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($code >= 300 && $code < 400) return strlen($chunk);
                $got += strlen($chunk);
                if ($got > $maxBytes) { $tooBig = true; return 0; }
                if ($sink) return fwrite($sink, $chunk);
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($tooBig) throw new RuntimeException('Descarga demasiado grande.');
        if ($code >= 300 && $code < 400 && $location) { $url = $location; continue; }
        if ($ok === false || $code !== 200) throw new RuntimeException("No se pudo descargar ($host, HTTP $code" . ($err ? ", $err" : '') . ').');
        return $body;
    }
    throw new RuntimeException('Demasiadas redirecciones.');
}

function rrmdir(string $dir): void
{
    if (!file_exists($dir)) return;
    if (is_file($dir) || is_link($dir)) { @unlink($dir); return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($dir);
}

// ─── Ya instalada: no tocar nada ──────────────────────────────────────
if ($installed) {
    if (($_POST['action'] ?? '') === 'delete') {
        @unlink(__FILE__);
        header('Location: /admin');
        exit;
    }
    page('Imagina Audit ya está instalada', '<p>No se ha modificado nada. Por seguridad, borra este instalador.</p>'
        . '<form method="post"><input type="hidden" name="action" value="delete"><button style="padding:8px 16px">Borrar installer.php e ir al panel</button></form>');
}

// ─── Requisitos ───────────────────────────────────────────────────────
$checks = [];
$checks[] = ['PHP ' . PHP_VERSION . ' (mínimo ' . MIN_PHP . ')', version_compare(PHP_VERSION, MIN_PHP, '>=')];
foreach (REQUIRED_EXT as $e) $checks[] = ["Extensión $e", extension_loaded($e)];
$checks[] = ['Permiso de escritura en ' . $root, is_writable($root)];
$allOk = !in_array(false, array_column($checks, 1), true);

if (($_POST['action'] ?? '') !== 'install') {
    $list = '<ul>' . implode('', array_map(fn($c) => '<li>' . ($c[1] ? '✅' : '❌') . ' ' . htmlspecialchars($c[0]) . '</li>', $checks)) . '</ul>';
    $btn = $allOk
        ? '<form method="post"><input type="hidden" name="action" value="install"><button style="padding:10px 18px;font-size:16px">Instalar la última versión</button></form>'
        : '<p style="color:#b91c1c">Corrige los requisitos marcados con ❌ (desde tu panel de hosting) y recarga esta página.</p>';
    page('Instalar Imagina Audit', '<p>Se descargará la última versión publicada en GitHub (<code>' . INSTALLER_REPO . '</code>), se verificará su sha256 y se instalará en esta carpeta.</p>' . $list . $btn);
}
if (!$allOk) page('Requisitos incompletos', '<p>Vuelve atrás y revisa los requisitos.</p>');

// ─── Instalación ─────────────────────────────────────────────────────
@set_time_limit(600);
@ignore_user_abort(true);
$stamp = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
$zipPath = "$root/.installer-$stamp.zip";
$staging = "$root/.installer-staging-$stamp";

try {
    $rel = json_decode(gh_get('https://api.github.com/repos/' . INSTALLER_REPO . '/releases/latest', 1_000_000), true);
    $version = ltrim((string) ($rel['tag_name'] ?? ''), 'vV');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) throw new RuntimeException('El último release no tiene una versión válida.');
    $assets = [];
    foreach ($rel['assets'] ?? [] as $a) $assets[$a['name']] = $a;
    $zipName = "imagina-audit-$version.zip";
    if (!isset($assets[$zipName], $assets["$zipName.sha256"])) throw new RuntimeException("El release no trae $zipName y su .sha256.");

    $fh = fopen($zipPath, 'wb');
    gh_get($assets[$zipName]['browser_download_url'], 300 * 1024 * 1024, $fh);
    fclose($fh);
    $shaText = gh_get($assets["$zipName.sha256"]['browser_download_url'], 4096);
    if (!preg_match('/\b([a-f0-9]{64})\b/i', $shaText, $m) || !hash_equals(strtolower($m[1]), hash_file('sha256', $zipPath))) {
        throw new RuntimeException('La verificación sha256 falló: el paquete está corrupto o fue alterado.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('El zip está dañado.');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name === '' || $name[0] === '/' || str_contains($name, '\\') || str_contains($name, "\0") || in_array('..', explode('/', $name), true)) {
            $zip->close();
            throw new RuntimeException("Ruta no permitida dentro del zip: $name");
        }
    }
    mkdir($staging, 0755);
    if (!$zip->extractTo($staging)) { $zip->close(); throw new RuntimeException('No se pudo descomprimir (¿espacio en disco?).'); }
    $zip->close();
    foreach (['index.html', 'api/index.php', 'lib/Database.php', 'VERSION'] as $req) {
        if (!file_exists("$staging/$req")) throw new RuntimeException("Paquete incompleto: falta $req");
    }

    // Mover a la raíz. Lo que ya existía con el mismo nombre (p.ej. el
    // index.html de bienvenida del hosting) se aparta, no se borra.
    $backup = "$root/.installer-previous-$stamp";
    foreach (scandir($staging) as $e) {
        if ($e === '.' || $e === '..') continue;
        if (file_exists("$root/$e")) {
            if (!is_dir($backup)) mkdir($backup, 0755);
            rename("$root/$e", "$backup/$e");
        }
        if (!rename("$staging/$e", "$root/$e")) throw new RuntimeException("No se pudo mover $e a la carpeta pública.");
    }
    rrmdir($staging);
    @unlink($zipPath);
    @unlink(__FILE__);
    header('Location: /setup');
    page('Instalación completada', '<p>Versión ' . htmlspecialchars($version) . ' instalada. <a href="/setup">Continuar con el asistente</a>.</p>');
} catch (Throwable $e) {
    // No dejar basura: zip y carpeta a medio extraer.
    @unlink($zipPath);
    rrmdir($staging);
    page('No se pudo instalar', '<p style="color:#b91c1c">' . htmlspecialchars($e->getMessage()) . '</p><p>No se instaló nada. Puedes recargar para reintentar.</p>');
}
