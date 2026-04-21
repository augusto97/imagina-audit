<?php
/**
 * Almacén local de plugins de terceros (wp-snapshot, etc.).
 *
 * Resuelve dos problemas:
 *   1. Los repos de GitHub pueden desaparecer (cuenta borrada, repo
 *      privado). Mantenemos una copia local del último ZIP.
 *   2. Pedirle a un cliente que se descargue algo de GitHub levanta
 *      sospechas ("¿no es un virus?"). Mejor servir el ZIP desde
 *      nuestro propio dominio.
 *
 * Cada plugin se identifica por un slug ('wp-snapshot') y se almacena
 * en backend/storage/plugins/{slug}/{slug}-{version}.zip + un
 * latest.zip que es copia del más reciente para link estable.
 *
 * El metadata vive en la tabla `settings` bajo la clave
 * `plugin_vault_{slug}` como JSON.
 */

class PluginVault {
    private const STORAGE_SUBDIR = 'storage/plugins';

    /**
     * Catálogo de plugins gestionados. Source = GitHub repo
     * "owner/repo". Si el repo cambia de dueño basta editarlo aquí.
     */
    public static function catalog(): array {
        return [
            'wp-snapshot' => [
                'displayName' => 'wp-snapshot',
                'description' => 'Plugin de WordPress que genera un snapshot completo del sitio (plugins, BD, cron, seguridad) en un JSON. Necesario para el análisis interno de Imagina Audit.',
                'githubRepo'  => 'mrabro/wp-snapshot',
                'pluginFolder' => 'wp-snapshot',  // nombre limpio dentro del ZIP
            ],
        ];
    }

    /**
     * Path absoluto al directorio de un plugin. Se crea si no existe.
     */
    private static function pluginDir(string $slug): string {
        $base = dirname(__DIR__) . '/' . self::STORAGE_SUBDIR . '/' . $slug;
        if (!is_dir($base)) @mkdir($base, 0755, true);
        return $base;
    }

    /**
     * Metadata del plugin guardada en settings: { version, publishedAt,
     * downloadedAt, checkedAt, filename, sizeBytes, sha256, source }.
     */
    public static function getMetadata(string $slug): ?array {
        try {
            $db = Database::getInstance();
            $row = $db->queryOne("SELECT value FROM settings WHERE key = ?", ["plugin_vault_$slug"]);
            if (!$row) return null;
            $decoded = json_decode((string) $row['value'], true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Verifica el path del archivo cacheado y devuelve metadata extendida
     * para la UI: incluye fileExists y publicUrl.
     */
    public static function status(string $slug): array {
        $catalog = self::catalog();
        $info = $catalog[$slug] ?? null;
        if ($info === null) {
            return ['error' => 'plugin desconocido'];
        }

        $meta = self::getMetadata($slug);
        $filename = $meta['filename'] ?? '';
        $absPath = $filename ? self::pluginDir($slug) . '/' . $filename : '';
        $fileExists = $filename && is_file($absPath);

        return [
            'slug'        => $slug,
            'displayName' => $info['displayName'],
            'description' => $info['description'],
            'githubRepo'  => $info['githubRepo'],
            'githubUrl'   => 'https://github.com/' . $info['githubRepo'],
            'version'     => $meta['version'] ?? null,
            'publishedAt' => $meta['publishedAt'] ?? null,
            'downloadedAt' => $meta['downloadedAt'] ?? null,
            'checkedAt'   => $meta['checkedAt'] ?? null,
            'sizeBytes'   => $meta['sizeBytes'] ?? null,
            'sha256'      => $meta['sha256'] ?? null,
            'source'      => $meta['source'] ?? null,
            'fileExists'  => $fileExists,
            'publicUrl'   => '/api/plugin-download.php?slug=' . urlencode($slug),
        ];
    }

    /**
     * Consulta GitHub releases. Devuelve { tag, name, publishedAt,
     * zipUrl } o null si no hay release.
     */
    public static function fetchLatestRelease(string $slug): ?array {
        $info = self::catalog()[$slug] ?? null;
        if ($info === null) return null;

        $apiUrl = "https://api.github.com/repos/{$info['githubRepo']}/releases/latest";
        try {
            $resp = Fetcher::get($apiUrl, 15, true, 1);
        } catch (Throwable $e) {
            Logger::warning("PluginVault.$slug fetchLatestRelease falló: " . $e->getMessage());
            return null;
        }

        if (($resp['statusCode'] ?? 0) !== 200) {
            // Sin releases — caemos a zipball del default branch
            return self::fetchDefaultBranchZip($slug);
        }

        $data = json_decode($resp['body'] ?? '', true);
        if (!is_array($data)) return null;

        // Preferimos un asset .zip de la release (ZIP "limpio" con folder
        // correcto). Si no hay, usamos zipball_url (autogenerado por GitHub
        // con folder {owner}-{repo}-{sha}).
        $zipUrl = null;
        foreach ($data['assets'] ?? [] as $asset) {
            if (str_ends_with(strtolower($asset['name'] ?? ''), '.zip')) {
                $zipUrl = $asset['browser_download_url'] ?? null;
                break;
            }
        }
        if ($zipUrl === null) {
            $zipUrl = $data['zipball_url'] ?? null;
        }

        return [
            'tag'         => $data['tag_name'] ?? '',
            'name'        => $data['name'] ?? '',
            'publishedAt' => $data['published_at'] ?? '',
            'zipUrl'      => $zipUrl,
            'source'      => 'release',
        ];
    }

    /**
     * Fallback para repos sin releases: descargar el default branch.
     */
    private static function fetchDefaultBranchZip(string $slug): ?array {
        $info = self::catalog()[$slug] ?? null;
        if ($info === null) return null;

        // Pedir info del repo para conocer el default_branch
        $repoUrl = "https://api.github.com/repos/{$info['githubRepo']}";
        try {
            $resp = Fetcher::get($repoUrl, 10, true, 1);
        } catch (Throwable $e) {
            return null;
        }
        if (($resp['statusCode'] ?? 0) !== 200) return null;
        $data = json_decode($resp['body'] ?? '', true);
        $branch = $data['default_branch'] ?? 'main';
        $sha = ''; // sin tag, usamos timestamp

        return [
            'tag'         => 'branch-' . $branch . '-' . date('Ymd'),
            'name'        => "$branch branch",
            'publishedAt' => $data['pushed_at'] ?? date('c'),
            'zipUrl'      => "https://api.github.com/repos/{$info['githubRepo']}/zipball/$branch",
            'source'      => 'branch',
            '_sha'        => $sha,
        ];
    }

    /**
     * Descarga el ZIP, lo normaliza (folder limpio) y lo guarda. Actualiza
     * la metadata en settings. Devuelve la nueva metadata o null si falló.
     *
     * @param bool $force Si true, descarga aunque la versión ya esté.
     */
    public static function refresh(string $slug, bool $force = false): ?array {
        $info = self::catalog()[$slug] ?? null;
        if ($info === null) return null;

        $latest = self::fetchLatestRelease($slug);

        // Marcar el chequeo aunque no se descargue (para auditar)
        $existing = self::getMetadata($slug) ?? [];
        $existing['checkedAt'] = date('c');
        self::saveMetadata($slug, $existing);

        if ($latest === null || empty($latest['zipUrl'])) {
            Logger::warning("PluginVault.$slug refresh: sin release ni branch disponibles");
            return null;
        }

        $latestVersion = $latest['tag'];
        $currentVersion = $existing['version'] ?? null;

        if (!$force && $currentVersion === $latestVersion) {
            // Ya tenemos esta versión, nada que hacer
            return self::status($slug);
        }

        // Descargar el ZIP
        try {
            $resp = Fetcher::get($latest['zipUrl'], 60, true, 1);
        } catch (Throwable $e) {
            Logger::error("PluginVault.$slug descarga falló: " . $e->getMessage());
            return null;
        }
        if (($resp['statusCode'] ?? 0) !== 200) {
            Logger::error("PluginVault.$slug descarga HTTP {$resp['statusCode']}");
            return null;
        }

        $rawZip = (string) $resp['body'];
        if (strlen($rawZip) < 1024) {
            Logger::error("PluginVault.$slug ZIP demasiado pequeño (" . strlen($rawZip) . ' bytes)');
            return null;
        }

        // Normalizar el ZIP: GitHub auto-genera folders como
        // "owner-repo-sha7chars/", lo reescribimos a "{pluginFolder}/"
        $pluginFolder = $info['pluginFolder'];
        $normalized = self::normalizeZip($rawZip, $pluginFolder);
        if ($normalized === null) {
            Logger::error("PluginVault.$slug normalización ZIP falló");
            return null;
        }

        $cleanVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $latestVersion);
        $filename = "$slug-$cleanVersion.zip";
        $dir = self::pluginDir($slug);
        $absPath = $dir . '/' . $filename;

        if (file_put_contents($absPath, $normalized) === false) {
            Logger::error("PluginVault.$slug no pudo escribir $absPath");
            return null;
        }
        @chmod($absPath, 0644);

        // Copia "latest.zip" para link público estable
        @copy($absPath, $dir . '/latest.zip');

        // Limpiar versiones viejas (mantener las 3 más recientes)
        self::pruneOldVersions($dir, $slug, 3);

        $newMeta = [
            'version'      => $latestVersion,
            'publishedAt'  => $latest['publishedAt'],
            'downloadedAt' => date('c'),
            'checkedAt'    => date('c'),
            'filename'     => $filename,
            'sizeBytes'    => strlen($normalized),
            'sha256'       => hash('sha256', $normalized),
            'source'       => $latest['source'],
        ];
        self::saveMetadata($slug, $newMeta);

        Logger::info("PluginVault.$slug actualizado a $latestVersion ({$newMeta['sizeBytes']} bytes)");
        return self::status($slug);
    }

    /**
     * Reescribe el ZIP cambiando el nombre del folder raíz por $newFolder.
     * Esto es necesario porque GitHub genera folders con el SHA y
     * WordPress espera un folder con el nombre del plugin.
     */
    private static function normalizeZip(string $zipBytes, string $newFolder): ?string {
        $tmpIn = tempnam(sys_get_temp_dir(), 'pv-in');
        $tmpOut = tempnam(sys_get_temp_dir(), 'pv-out');
        file_put_contents($tmpIn, $zipBytes);

        $in = new ZipArchive();
        if ($in->open($tmpIn) !== true) {
            @unlink($tmpIn); @unlink($tmpOut);
            return null;
        }

        // Detectar el folder raíz original (todas las entries comparten prefijo)
        $rootPrefix = null;
        for ($i = 0; $i < $in->numFiles; $i++) {
            $name = $in->getNameIndex($i);
            if ($name === false) continue;
            $firstSlash = strpos($name, '/');
            if ($firstSlash === false) continue;
            $prefix = substr($name, 0, $firstSlash + 1);
            if ($rootPrefix === null) {
                $rootPrefix = $prefix;
            } elseif ($rootPrefix !== $prefix) {
                // Multi-root, no podemos normalizar
                $rootPrefix = '';
                break;
            }
        }

        $out = new ZipArchive();
        if ($out->open($tmpOut, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $in->close();
            @unlink($tmpIn); @unlink($tmpOut);
            return null;
        }

        for ($i = 0; $i < $in->numFiles; $i++) {
            $name = $in->getNameIndex($i);
            if ($name === false) continue;
            $contents = $in->getFromIndex($i);
            if ($contents === false) continue;

            // Reescribir el prefijo
            if ($rootPrefix !== null && $rootPrefix !== '' && str_starts_with($name, $rootPrefix)) {
                $newName = $newFolder . '/' . substr($name, strlen($rootPrefix));
            } else {
                $newName = $newFolder . '/' . $name;
            }

            // Saltar entries que serían el directorio en sí
            if ($newName === $newFolder . '/') continue;

            $out->addFromString($newName, $contents);
        }
        $in->close();
        $out->close();

        $bytes = file_get_contents($tmpOut);
        @unlink($tmpIn);
        @unlink($tmpOut);
        return $bytes !== false ? $bytes : null;
    }

    /**
     * Borra ZIPs viejos del directorio, manteniendo solo los $keep más
     * recientes (excluyendo latest.zip).
     */
    private static function pruneOldVersions(string $dir, string $slug, int $keep): void {
        $files = glob($dir . "/$slug-*.zip") ?: [];
        if (count($files) <= $keep) return;
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    private static function saveMetadata(string $slug, array $meta): void {
        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                ["plugin_vault_$slug", json_encode($meta, JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $e) {
            Logger::warning("PluginVault.$slug saveMetadata falló: " . $e->getMessage());
        }
    }

    /**
     * Retorna el path absoluto al ZIP servible (latest.zip si existe,
     * fallback al filename guardado en metadata).
     */
    public static function getZipPath(string $slug): ?string {
        $dir = self::pluginDir($slug);
        $latest = $dir . '/latest.zip';
        if (is_file($latest)) return $latest;

        $meta = self::getMetadata($slug);
        if (!$meta || empty($meta['filename'])) return null;
        $abs = $dir . '/' . $meta['filename'];
        return is_file($abs) ? $abs : null;
    }
}
