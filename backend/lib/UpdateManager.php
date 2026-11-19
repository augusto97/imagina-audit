<?php
/**
 * UpdateManager — actualizador de la app desde GitHub Releases.
 *
 * Pensado para hosting compartido / panel (ServerAvatar, cPanel): sin root,
 * sin systemd, sin symlinks. La raíz web la fija el panel, así que en vez de
 * cambiar un symlink `current` se renombran las carpetas de código de la
 * raíz (rename() es atómico por entrada y el cambio completo dura ms).
 *
 *   <raíz web>/                      ← código activo + datos compartidos
 *      api/ lib/ assets/ ... VERSION manifest.json   (se reemplazan)
 *      .env cache/ logs/ uploads/ storage/            (NUNCA se tocan)
 *   <carpeta de trabajo>/            ← fuera de la raíz web si se puede
 *      releases/<versión>/           ← paquete nuevo, verificado
 *      releases/_prev-<versión>-…/   ← versión anterior (para volver atrás)
 *      tmp/  state.json  history.json  latest.json  update.lock
 *
 * La instalación avanza por pasos cortos (cada uno una petición HTTP) para
 * no chocar con los límites de tiempo del hosting:
 *   preflight → download → extract → backup → migrate → env → switch → finalize
 *
 * Reglas (errores reales de otro proyecto):
 *   - Sin espacio suficiente no se descarga nada.
 *   - Verificación sha256 obligatoria antes de extraer.
 *   - Si un paso falla antes del cambio, se borra el zip y la carpeta a
 *     medio extraer.
 *   - Migraciones ANTES del cambio y siempre con copia previa de la base,
 *     ejecutadas con el código NUEVO (api/update-migrate.php, en una
 *     petición aparte para no mezclar clases de dos versiones).
 *   - El `.env` se lee como dotenv (EnvFile) y se repara sin pisar valores.
 *   - Tras el cambio, health-check; si falla se deshace el cambio con el
 *     journal de renombres (todavía con el código viejo en memoria).
 *   - La poda de versiones viejas la hace la versión nueva al arrancar
 *     (marcador .prune-pending), no el paso del cambio.
 *   - Nunca toca la configuración del servidor web: si el .htaccess raíz
 *     fue modificado a mano, se conserva y se avisa.
 *   - Una sola instalación a la vez: candado lógico (state.json) + flock.
 */
class UpdateManager
{
    public const DEFAULT_REPO = 'augusto97/imagina-audit';
    public const KEEP_RELEASES = 3;
    public const KEEP_DB_BACKUPS = 5;
    public const KEEP_ENV_BACKUPS = 3;
    public const STALE_SECONDS = 1800;
    public const CHECK_INTERVAL = 3600;
    public const RESCUE_AFTER_SECONDS = 900;

    /** Entradas de la raíz que el actualizador nunca mueve ni borra. */
    public const PRESERVE = ['.env', 'cache', 'logs', 'uploads', 'storage', 'update-rescue.php', '.well-known', '.htaccess.new'];

    /** Archivos que debe traer un paquete válido. */
    private const REQUIRED_FILES = ['VERSION', 'manifest.json', 'index.html', 'api/index.php', 'api/update-migrate.php', 'lib/Database.php', 'lib/Migrator.php', 'lib/UpdateManager.php'];

    private const MAX_ZIP_BYTES = 300 * 1024 * 1024;
    private const MAX_UNCOMPRESSED_BYTES = 600 * 1024 * 1024;
    private const MAX_ENTRIES = 40000;

    private string $appRoot;
    private string $workDir;
    /** @var array<string,mixed> */
    private array $deps;
    private ?object $sourceInstance = null;

    /**
     * @param array{
     *   source?: object|Closure,      // ReleaseSource o fake: latest(), fetchText(), download()
     *   backup?: Closure,             // fn(): string  ruta del backup creado
     *   health?: Closure,             // fn(string $version): array{reachable:bool, ok:bool, detail:string}
     *   diskFree?: Closure,           // fn(string $path): float|false
     *   inodesFree?: Closure,         // fn(string $path): ?int
     *   dbSize?: Closure,             // fn(): int
     *   migrate?: ?Closure,           // fn(string $releaseDir): string[]  (null = externo)
     *   readyCheck?: Closure,         // fn(string $version): ?string  error o null
     *   opcacheReset?: Closure,
     *   now?: Closure,                // fn(): int
     * } $deps
     */
    public function __construct(string $appRoot, string $workDir, array $deps = [])
    {
        $this->appRoot = rtrim($appRoot, '/');
        $this->workDir = rtrim($workDir, '/');
        $this->deps = $deps + [
            'backup' => fn() => throw new RuntimeException('Backup no configurado'),
            'health' => fn(string $v) => ['reachable' => false, 'ok' => false, 'detail' => 'sin health-check'],
            'diskFree' => fn(string $p) => @disk_free_space($p),
            'inodesFree' => fn(string $p) => self::probeInodes($p),
            'dbSize' => fn() => 0,
            'migrate' => null,
            'readyCheck' => fn(string $v) => null,
            'opcacheReset' => function () { if (function_exists('opcache_reset')) @opcache_reset(); },
            'now' => fn() => time(),
        ];
    }

    // ─── Construcción para la app real ────────────────────────────────

    public static function forApp(): self
    {
        $appRoot = dirname(__DIR__);
        return new self($appRoot, self::workDirFor($appRoot), [
            'source' => fn() => ReleaseSource::fromSettings(self::configuredRepo()),
            'backup' => fn() => Backup::create(Database::getInstance()),
            'health' => fn(string $v) => self::httpHealth(QueueManager::appBaseUrl(), $v),
            'dbSize' => fn() => self::estimateDbSize(),
            'readyCheck' => function (string $v) {
                if (AppVersion::current() !== $v) return 'La versión en ejecución (' . AppVersion::current() . ") no es la esperada ($v).";
                $m = new Migrator(Database::getInstance());
                $m->bootstrap();
                $pending = $m->pending();
                return $pending ? 'Quedan ' . count($pending) . ' migraciones pendientes.' : null;
            },
        ]);
    }

    public static function configuredRepo(): string
    {
        try {
            $v = trim((string) Database::getInstance()->scalar("SELECT value FROM settings WHERE `key` = 'update_repo'"));
            if ($v !== '' && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $v)) return $v;
        } catch (Throwable $e) {}
        return self::DEFAULT_REPO;
    }

    public static function autoInstallEnabled(): bool
    {
        try {
            return (string) Database::getInstance()->scalar("SELECT value FROM settings WHERE `key` = 'update_auto_install'") === '1';
        } catch (Throwable $e) { return false; }
    }

    /** Candidatas a carpeta de trabajo, en orden de preferencia. */
    public static function candidateWorkDirs(string $appRoot): array
    {
        $out = [];
        $env = function_exists('env') ? env('UPDATE_WORK_DIR', '') : '';
        if ($env !== '') $out[] = rtrim($env, '/');
        $out[] = dirname($appRoot) . '/imagina_audit_data/updates';
        $out[] = $appRoot . '/storage/updates';
        return $out;
    }

    /**
     * Carpeta de trabajo: fuera de la raíz web si es escribible y está en el
     * mismo sistema de archivos (rename() entre ambas debe funcionar); si no,
     * storage/updates (bloqueada por .htaccess y por deploy/nginx.conf).
     */
    public static function workDirFor(string $appRoot): string
    {
        $cands = self::candidateWorkDirs($appRoot);
        foreach ($cands as $i => $dir) {
            // Si ya hay una actualización registrada en una candidata, seguir usándola.
            if (is_file("$dir/state.json")) return $dir;
        }
        foreach ($cands as $dir) {
            if ((is_dir($dir) || @mkdir($dir, 0755, true)) && is_writable($dir) && self::renameProbe($dir, $appRoot)) {
                return $dir;
            }
        }
        return end($cands);
    }

    private static function renameProbe(string $dir, string $appRoot): bool
    {
        $name = '.upd-probe-' . bin2hex(random_bytes(4));
        $a = "$dir/$name";
        $b = "$appRoot/$name";
        if (@file_put_contents($a, 'x') === false) return false;
        $ok = @rename($a, $b) && @rename($b, $a);
        @unlink($a); @unlink($b);
        return $ok;
    }

    // ─── Rutas ─────────────────────────────────────────────────────────

    public function workDir(): string { return $this->workDir; }
    private function releasesDir(): string { return $this->workDir . '/releases'; }
    private function tmpDir(): string { return $this->workDir . '/tmp'; }
    private function statePath(): string { return $this->workDir . '/state.json'; }
    private function marker(string $name): string { return $this->workDir . '/.' . $name . '-pending'; }

    private function source(): object
    {
        if ($this->sourceInstance) return $this->sourceInstance;
        $s = $this->deps['source'] ?? null;
        if ($s instanceof Closure) $s = $s();
        if (!is_object($s)) throw new RuntimeException('Origen de releases no configurado.');
        return $this->sourceInstance = $s;
    }

    private function now(): int { return (int) ($this->deps['now'])(); }

    // ─── Estado ────────────────────────────────────────────────────────

    public function readState(): array
    {
        $raw = @file_get_contents($this->statePath());
        $s = $raw ? json_decode($raw, true) : null;
        return is_array($s) ? $s : ['status' => 'idle'];
    }

    private function writeState(array $state): array
    {
        $this->ensureDir($this->workDir);
        $state['updatedAt'] = $this->now();
        $tmp = $this->statePath() . '.tmp.' . bin2hex(random_bytes(3));
        file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (!@rename($tmp, $this->statePath())) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo guardar el estado de la actualización.');
        }
        return $state;
    }

    private function log(array &$state, string $msg, string $level = 'info'): void
    {
        $state['log'][] = ['at' => $this->now(), 'step' => $state['step'] ?? '', 'level' => $level, 'msg' => $msg];
        if (count($state['log']) > 200) $state['log'] = array_slice($state['log'], -200);
    }

    /** ¿Hay una actualización en curso (no abandonada)? */
    public function isBusy(?array $state = null): bool
    {
        $state ??= $this->readState();
        $active = in_array($state['status'] ?? 'idle', ['running', 'switched'], true);
        if (!$active) return false;
        // 'switched' nunca se considera abandonado: requiere finalizar o rescatar.
        if (($state['status'] ?? '') === 'switched') return true;
        return ($this->now() - (int) ($state['updatedAt'] ?? 0)) < self::STALE_SECONDS;
    }

    /** Ejecuta $fn con flock exclusivo no bloqueante (un paso a la vez). */
    private function withLock(callable $fn)
    {
        $this->ensureDir($this->workDir);
        $fh = fopen($this->workDir . '/update.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            if ($fh) fclose($fh);
            throw new UpdateBusyException('Ya hay un paso de actualización ejecutándose. Espera unos segundos.');
        }
        try {
            return $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    // ─── Consulta de releases ──────────────────────────────────────────

    /** Último release (cacheado 1 h salvo $force). */
    public function checkLatest(bool $force = false): array
    {
        $path = $this->workDir . '/latest.json';
        $cached = json_decode((string) @file_get_contents($path), true);
        if (!$force && is_array($cached) && ($this->now() - (int) ($cached['checkedAt'] ?? 0)) < self::CHECK_INTERVAL) {
            return $cached;
        }
        $out = ['checkedAt' => $this->now(), 'release' => $cached['release'] ?? null, 'error' => null];
        try {
            $out['release'] = $this->source()->latest();
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        $this->ensureDir($this->workDir);
        @file_put_contents($path, json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $out;
    }

    public function status(): array
    {
        $installed = AppVersion::current();
        $latest = json_decode((string) @file_get_contents($this->workDir . '/latest.json'), true) ?: null;
        $rel = $latest['release'] ?? null;
        $state = $this->readState();
        $prev = $this->latestPrevious();
        return [
            'installed' => $installed,
            'deployed' => $this->isDeployedLayout(),
            'latest' => $latest,
            'updateAvailable' => $rel && AppVersion::isNewer((string) $rel['version'], $installed),
            'state' => $this->publicState($state),
            'busy' => $this->isBusy($state),
            'previous' => $prev ? ['version' => $prev['version']] : null,
            'history' => array_slice($this->readHistory(), 0, 20),
            'workDir' => $this->workDir,
            'workDirInsideWebRoot' => str_starts_with($this->workDir, $this->appRoot . '/'),
        ];
    }

    /** Estado apto para el navegador del admin (oculta el journal y tokens que no tocan). */
    public function publicState(array $s): array
    {
        $pub = $s;
        unset($pub['journal'], $pub['release']['body']);
        $needsOps = ($s['status'] ?? '') === 'running' && ($s['step'] ?? '') === 'migrate';
        if (!$needsOps) unset($pub['opsToken']);
        if (($s['status'] ?? '') !== 'switched') unset($pub['rescueToken']);
        return $pub;
    }

    private function isDeployedLayout(): bool
    {
        return is_file($this->appRoot . '/index.html') && is_dir($this->appRoot . '/api') && is_dir($this->appRoot . '/lib');
    }

    // ─── Ciclo de instalación ─────────────────────────────────────────

    /** Arranca la instalación del último release (ya consultado). */
    public function begin(string $kind = 'manual'): array
    {
        return $this->withLock(function () use ($kind) {
            $state = $this->readState();
            if ($this->isBusy($state)) throw new UpdateBusyException('Ya hay una actualización en curso.');
            // Restos de una actualización abandonada antes del cambio
            if (($state['status'] ?? '') === 'running') $this->cleanupStaging($state);

            $latest = $this->checkLatest(false);
            $rel = $latest['release'] ?? null;
            $installed = AppVersion::current();
            if (!$rel) throw new RuntimeException($latest['error'] ?? 'No hay releases publicados.');
            if (!AppVersion::isNewer($rel['version'], $installed)) {
                throw new RuntimeException("Ya tienes la última versión ($installed).");
            }
            $state = [
                'id' => bin2hex(random_bytes(6)),
                'kind' => $kind,
                'status' => 'running',
                'step' => 'preflight',
                'fromVersion' => $installed,
                'toVersion' => $rel['version'],
                'release' => $rel,
                'opsToken' => bin2hex(random_bytes(24)),
                'startedAt' => $this->now(),
                'checks' => [],
                'notices' => array_values($rel['manifest']['notices'] ?? []),
                'log' => [],
                'error' => null,
            ];
            $this->log($state, "Inicio de la actualización {$installed} → {$rel['version']}");
            return $this->writeState($state);
        });
    }

    /** Ejecuta el paso pendiente. */
    public function step(): array
    {
        return $this->withLock(function () {
            $state = $this->readState();
            $status = $state['status'] ?? 'idle';
            if ($status === 'switched') return $this->doFinalize($state);
            // Terminal (p. ej. la versión nueva ya finalizó sola al arrancar): idempotente.
            if (in_array($status, ['completed', 'failed', 'cancelled'], true)) return $state;
            if ($status !== 'running') throw new RuntimeException('No hay ninguna actualización en curso.');

            @set_time_limit(300);
            @ignore_user_abort(true);
            $step = $state['step'];
            try {
                switch ($step) {
                    case 'preflight': return $this->doPreflight($state);
                    case 'download':  return $this->doDownload($state);
                    case 'extract':   return $this->doExtract($state);
                    case 'backup':    return $this->doBackup($state);
                    case 'migrate':
                        if (!$this->deps['migrate']) return $state; // lo hace api/update-migrate.php
                        try {
                            $applied = ($this->deps['migrate'])($this->stagingDir($state));
                        } catch (Throwable $e) {
                            return $this->fail($state, 'Las migraciones fallaron: ' . $e->getMessage() . ' — la versión instalada sigue activa; hay una copia de la base previa en Backups.');
                        }
                        return $this->afterMigrate($state, (array) $applied);
                    case 'env':       return $this->doEnv($state);
                    case 'switch':    return $this->doSwitch($state);
                }
                throw new RuntimeException("Paso desconocido: $step");
            } catch (UpdateBusyException $e) {
                throw $e;
            } catch (Throwable $e) {
                $state = $this->readState();
                if (($state['status'] ?? '') === 'running') return $this->fail($state, $e->getMessage());
                throw $e;
            }
        });
    }

    private function stagingDir(array $state): string
    {
        return $this->releasesDir() . '/' . $state['toVersion'];
    }

    private function zipPath(array $state): string
    {
        return $this->tmpDir() . '/imagina-audit-' . $state['toVersion'] . '.zip';
    }

    // 1. Requisitos, permisos y espacio — sin descargar nada.
    private function doPreflight(array $state): array
    {
        $rel = $state['release'];
        $manifest = $rel['manifest'] ?? [];
        $checks = [];
        $add = function (string $name, bool $ok, string $detail, bool $blocking = true) use (&$checks) {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'blocking' => $blocking];
        };

        $add('layout', $this->isDeployedLayout(), $this->isDeployedLayout() ? 'Instalación desplegada detectada' : 'Esta carpeta no parece una instalación desplegada');

        $minPhp = (string) ($manifest['minPhp'] ?? '8.0.0');
        $add('php', version_compare(PHP_VERSION, $minPhp, '>='), 'PHP ' . PHP_VERSION . " (mínimo $minPhp)");

        $exts = array_unique(array_merge(['curl', 'zip', 'json'], (array) ($manifest['requiredExtensions'] ?? [])));
        $missing = array_values(array_filter($exts, fn($e) => !extension_loaded($e)));
        $add('extensions', !$missing, $missing ? 'Faltan extensiones: ' . implode(', ', $missing) : 'Extensiones OK');

        // Permisos: renombrar exige escribir en la raíz y en cada carpeta que se mueve.
        $notWritable = [];
        if (!is_writable($this->appRoot)) $notWritable[] = '(raíz)';
        foreach ((array) ($manifest['entries'] ?? []) as $e) {
            $p = $this->appRoot . '/' . $e;
            if (is_dir($p) && !is_writable($p)) $notWritable[] = $e;
        }
        $add('permissions', !$notWritable, $notWritable ? 'Sin permiso de escritura: ' . implode(', ', $notWritable) : 'Permisos de escritura OK');

        $this->ensureDir($this->tmpDir());
        $this->ensureDir($this->releasesDir());
        $probe = self::renameProbe($this->tmpDir(), $this->appRoot);
        $add('workdir', $probe, $probe ? 'Carpeta de trabajo: ' . $this->workDir : 'No se puede mover archivos entre la carpeta de trabajo y la raíz web (¿otro disco?). Define UPDATE_WORK_DIR en .env.');

        // Espacio libre: zip + extraído + copia de la base + margen.
        $zipBytes = (int) ($rel['zip']['size'] ?? 0);
        $unc = (int) ($manifest['uncompressedBytes'] ?? max($zipBytes * 4, 20 * 1024 * 1024));
        $db = (int) ($this->deps['dbSize'])();
        $need = $zipBytes + $unc + $db + 25 * 1024 * 1024;
        $free = ($this->deps['diskFree'])($this->workDir);
        if ($free === false || $free === null) {
            $add('disk', true, 'Espacio libre no verificable en este servidor', false);
        } else {
            $add('disk', $free >= $need, 'Libre ' . self::fmtBytes((float) $free) . ', necesario ' . self::fmtBytes((float) $need));
        }

        $files = (int) ($manifest['fileCount'] ?? 2000);
        $inodes = ($this->deps['inodesFree'])($this->workDir);
        if ($inodes === null) {
            $add('inodes', true, 'Inodos no verificables (requiere shell_exec); la limpieza ante fallos sigue activa', false);
        } else {
            $needI = (int) ($files * 1.2) + 500;
            $add('inodes', $inodes >= $needI, "Inodos libres $inodes, necesarios $needI");
        }

        $state['checks'] = $checks;
        $failed = array_filter($checks, fn($c) => !$c['ok'] && $c['blocking']);
        if ($failed) {
            return $this->fail($state, 'No se cumplen los requisitos: ' . implode(' · ', array_column($failed, 'detail')), false);
        }
        $this->log($state, 'Requisitos OK');
        $state['step'] = 'download';
        return $this->writeState($state);
    }

    // 2. Descarga + sha256.
    private function doDownload(array $state): array
    {
        $rel = $state['release'];
        $zip = $this->zipPath($state);
        $this->ensureDir($this->tmpDir());
        @unlink($zip);
        $size = (int) ($rel['zip']['size'] ?? 0);
        $max = $size > 0 ? min(self::MAX_ZIP_BYTES, (int) ($size * 1.05) + 4096) : self::MAX_ZIP_BYTES;

        $src = $this->source();
        $src->download($rel['zip']['url'], $zip, $max);
        if ($size > 0 && filesize($zip) !== $size) {
            @unlink($zip);
            throw new RuntimeException('El tamaño descargado no coincide con el del release.');
        }
        $shaText = (string) $src->fetchText($rel['sha256Url'], 4096);
        if (!preg_match('/\b([a-f0-9]{64})\b/i', $shaText, $m)) {
            @unlink($zip);
            throw new RuntimeException('El archivo .sha256 del release no es válido.');
        }
        $actual = hash_file('sha256', $zip);
        if (!hash_equals(strtolower($m[1]), $actual)) {
            @unlink($zip);
            throw new RuntimeException('La verificación sha256 falló: el paquete descargado está corrupto o fue alterado.');
        }
        $state['sha256'] = $actual;
        $this->log($state, 'Descargado y verificado (sha256 ' . substr($actual, 0, 12) . '…)');
        $state['step'] = 'extract';
        return $this->writeState($state);
    }

    // 3. Extracción segura a releases/<versión>.
    private function doExtract(array $state): array
    {
        $zipPath = $this->zipPath($state);
        $final = $this->stagingDir($state);
        $partial = $final . '.partial';
        $this->rrmdir($partial);
        $this->rrmdir($final);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('El zip está dañado y no se puede abrir.');
        }
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) throw new RuntimeException('El zip tiene demasiadas entradas.');
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $name = (string) ($st['name'] ?? '');
                if (!self::isSafeZipPath($name)) throw new RuntimeException("Ruta no permitida dentro del zip: $name");
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX) {
                    if ((($attr >> 16) & 0170000) === 0120000) throw new RuntimeException("El zip contiene un enlace simbólico: $name");
                }
                $total += (int) ($st['size'] ?? 0);
                if ($total > self::MAX_UNCOMPRESSED_BYTES) throw new RuntimeException('El contenido descomprimido es demasiado grande.');
            }
            $this->ensureDir($partial);
            if (!$zip->extractTo($partial)) throw new RuntimeException('No se pudo descomprimir el paquete (¿disco lleno?).');
        } finally {
            $zip->close();
        }

        foreach (self::REQUIRED_FILES as $req) {
            if (!file_exists("$partial/$req")) throw new RuntimeException("Paquete incompleto: falta $req");
        }
        $v = trim((string) file_get_contents("$partial/VERSION"));
        $mf = json_decode((string) file_get_contents("$partial/manifest.json"), true);
        if ($v !== $state['toVersion'] || ($mf['version'] ?? '') !== $state['toVersion']) {
            throw new RuntimeException("El paquete dice ser la versión '$v', se esperaba {$state['toVersion']}.");
        }
        if (!@rename($partial, $final)) throw new RuntimeException('No se pudo preparar la carpeta de la versión.');
        @unlink($zipPath);
        $this->log($state, 'Paquete descomprimido y validado');
        $state['step'] = 'backup';
        return $this->writeState($state);
    }

    public static function isSafeZipPath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) return false;
        if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) return false;
        foreach (explode('/', $name) as $seg) {
            if ($seg === '..') return false;
        }
        return true;
    }

    // 4. Copia de la base.
    private function doBackup(array $state): array
    {
        $path = (string) ($this->deps['backup'])();
        if ($path === '' || !is_file($path)) throw new RuntimeException('No se pudo crear la copia de la base de datos.');
        $named = dirname($path) . '/pre-update-v' . $state['fromVersion'] . '-to-v' . $state['toVersion'] . '-' . basename($path);
        if (@rename($path, $named)) $path = $named;
        $state['backupPath'] = $path;
        $this->log($state, 'Copia de la base: ' . basename($path));
        $state['step'] = 'migrate';
        return $this->writeState($state);
    }

    /** Llamado por api/update-migrate.php (código nuevo) o por el paso en proceso. */
    public function afterExternalMigrate(string $token, array $applied, ?string $error): array
    {
        return $this->withLock(function () use ($token, $applied, $error) {
            $state = $this->readState();
            if (!$this->validOpsToken($state, $token) || ($state['step'] ?? '') !== 'migrate') {
                throw new RuntimeException('Token o paso inválido.');
            }
            if ($error !== null) {
                return $this->fail($state, "Las migraciones fallaron: $error — la versión instalada sigue activa; hay una copia de la base previa en Backups.");
            }
            return $this->afterMigrate($state, $applied);
        });
    }

    public function validOpsToken(array $state, string $token): bool
    {
        return ($state['status'] ?? '') === 'running'
            && !empty($state['opsToken']) && $token !== ''
            && hash_equals((string) $state['opsToken'], $token)
            && ($this->now() - (int) ($state['updatedAt'] ?? 0)) < self::STALE_SECONDS;
    }

    private function afterMigrate(array $state, array $applied): array
    {
        $state['migrationsApplied'] = array_values($applied);
        $this->log($state, $applied ? 'Migraciones aplicadas: ' . implode(', ', $applied) : 'Sin migraciones nuevas');
        $state['step'] = 'env';
        return $this->writeState($state);
    }

    // 6. Reparar .env (añadir claves nuevas sin pisar las existentes).
    private function doEnv(array $state): array
    {
        $env = $this->appRoot . '/.env';
        $example = $this->stagingDir($state) . '/.env.example';
        if (is_file($env)) {
            // Solo las variables que esta versión introduce (comparando con el
            // .env.example de la versión instalada), nunca todo el ejemplo.
            $added = EnvFile::repair($env, $example, 'v' . $state['toVersion'], $this->appRoot . '/.env.example');
            $state['envAdded'] = $added;
            $this->log($state, $added ? '.env: añadidas ' . implode(', ', $added) : '.env sin cambios');
        } else {
            $this->log($state, 'No hay .env en la raíz (configuración por entorno): sin cambios');
        }
        $state['step'] = 'switch';
        return $this->writeState($state);
    }

    // 7. Cambio de versión + health-check (rollback automático si falla).
    private function doSwitch(array $state): array
    {
        $src = $this->stagingDir($state);
        if (!is_dir($src)) throw new RuntimeException('No está la carpeta de la versión a instalar.');
        $state = $this->swapIn($state, $src, $state['fromVersion']);
        return $this->verifyAfterSwitch($state);
    }

    private function swapIn(array $state, string $src, string $fromVersion): array
    {
        $prev = $this->releasesDir() . '/_prev-' . $fromVersion . '-' . date('Ymd-His', $this->now()) . '-' . bin2hex(random_bytes(2));
        $this->ensureDir($prev);

        $old = json_decode((string) @file_get_contents($this->appRoot . '/manifest.json'), true) ?: [];
        $new = [];
        foreach (scandir($src) ?: [] as $e) {
            if ($e === '.' || $e === '..' || self::isPreserved($e)) continue;
            $new[] = $e;
        }

        // .htaccess raíz: si fue editado a mano, se conserva y se avisa.
        $kept = [];
        if (in_array('.htaccess', $new, true) && is_file($this->appRoot . '/.htaccess')) {
            $cur = hash_file('sha256', $this->appRoot . '/.htaccess');
            $incoming = hash_file('sha256', "$src/.htaccess");
            $shipped = $old['htaccessSha256'] ?? null;
            if ($cur !== $incoming && $cur !== $shipped) {
                $new = array_values(array_diff($new, ['.htaccess']));
                $kept[] = '.htaccess';
                @copy("$src/.htaccess", $this->appRoot . '/.htaccess.new');
                $state['notices'][] = 'Tu .htaccess de la raíz fue modificado a mano: se conservó. La versión nueva trae uno distinto en .htaccess.new; revísalo y combínalo si hace falta.';
            }
        }

        // Entradas que la versión nueva ya no trae (solo si eran de la app).
        $removed = [];
        foreach ((array) ($old['entries'] ?? []) as $e) {
            if (!in_array($e, $new, true) && !in_array($e, $kept, true) && !self::isPreserved($e) && $e !== '' && !str_contains($e, '/') && file_exists($this->appRoot . "/$e")) {
                $removed[] = $e;
            }
        }

        // Marca de instalación vive dentro de data/ (código): llevarla.
        if (is_file($this->appRoot . '/data/.installed') && is_dir("$src/data") && !is_file("$src/data/.installed")) {
            @copy($this->appRoot . '/data/.installed', "$src/data/.installed");
        }

        // Orden: index.html y api al final (lo primero que pide un navegador).
        usort($new, fn($a, $b) => self::swapRank($a) <=> self::swapRank($b));

        $journal = [];
        $mv = function (string $from, string $to) use (&$journal) {
            if (!@rename($from, $to)) {
                throw new RuntimeException('No se pudo mover ' . basename($from) . ' (' . (error_get_last()['message'] ?? 'rename falló') . ')');
            }
            $journal[] = [$from, $to];
        };
        try {
            foreach ($new as $e) {
                $live = $this->appRoot . "/$e";
                if (file_exists($live) || is_link($live)) $mv($live, "$prev/$e");
                $mv("$src/$e", $live);
                if ($e === 'database') {
                    // SQLite dentro de database/ (fallback): conservar el archivo vivo.
                    foreach (glob("$prev/database/*") ?: [] as $f) {
                        if (is_file($f) && preg_match('/\.(db|sqlite|sqlite3)(-wal|-shm|-journal)?$/', $f)) {
                            $target = "$live/" . basename($f);
                            if (!file_exists($target)) $mv($f, $target);
                        }
                    }
                }
            }
            foreach ($removed as $e) $mv($this->appRoot . "/$e", "$prev/$e");
        } catch (Throwable $e) {
            $this->reverseJournal($journal);
            $this->rrmdir($prev);
            throw new RuntimeException('El cambio de versión no se completó y se deshizo: ' . $e->getMessage());
        }

        $state['journal'] = $journal;
        $state['prevDir'] = $prev;
        $state['switchedAt'] = $this->now();
        $state['status'] = 'switched';
        $state['verified'] = false;
        $state['rescueToken'] = bin2hex(random_bytes(24));
        $state['step'] = 'finalize';
        $this->log($state, 'Cambio de versión hecho (' . count($new) . ' entradas)');
        // Guardar YA: si el proceso muere aquí, update-rescue.php puede volver atrás.
        $state = $this->writeState($state);
        ($this->deps['opcacheReset'])();
        clearstatcache(true);
        @touch($this->marker('prune'));
        return $state;
    }

    private function verifyAfterSwitch(array $state): array
    {
        $h = ($this->deps['health'])($state['toVersion']);
        if (!empty($h['reachable']) && empty($h['ok'])) {
            $this->reverseJournal($state['journal'] ?? []);
            ($this->deps['opcacheReset'])();
            $this->rrmdir((string) $state['prevDir']);
            $state['status'] = 'running'; // para que fail() limpie lo que queda
            return $this->fail($state, 'La versión nueva no pasó la verificación (' . ($h['detail'] ?? '') . '). Se restauró la versión ' . $state['fromVersion'] . ' automáticamente.');
        }
        if (!empty($h['ok'])) {
            $state['verified'] = true;
            $this->log($state, 'Verificación OK: ' . ($h['detail'] ?? ''));
        } else {
            $this->log($state, 'La app no pudo llamarse a sí misma para verificar (' . ($h['detail'] ?? '') . '). La nueva versión se verificará en su primera petición.', 'warn');
        }
        @touch($this->marker('finalize'));
        return $this->writeState($state);
    }

    // 8. Finalizar — lo ejecuta el código NUEVO.
    private function doFinalize(array $state): array
    {
        if (($state['status'] ?? '') !== 'switched') return $state;
        $err = ($this->deps['readyCheck'])((string) $state['toVersion']);
        if ($err !== null) {
            // Si responde la versión vieja (OPcache, etc.) no finalizamos todavía.
            $this->log($state, "Aún no se puede finalizar: $err", 'warn');
            return $this->writeState($state);
        }
        // Refrescar el script de rescate con el de la versión nueva.
        $rescue = $this->stagingDir($state) . '/update-rescue.php';
        if (is_file($rescue)) @copy($rescue, $this->appRoot . '/update-rescue.php');
        $this->rrmdir($this->stagingDir($state));

        $state['status'] = 'completed';
        $state['finishedAt'] = $this->now();
        unset($state['opsToken'], $state['rescueToken']);
        $this->log($state, 'Actualización completada a la versión ' . $state['toVersion']);
        $this->appendHistory($state);
        @unlink($this->marker('finalize'));
        $state = $this->writeState($state);
        $this->prune();
        return $state;
    }

    /** Cancela una actualización que aún no cambió de versión. */
    public function cancel(): array
    {
        return $this->withLock(function () {
            $state = $this->readState();
            if (($state['status'] ?? '') !== 'running') throw new RuntimeException('No hay una actualización cancelable.');
            return $this->fail($state, 'Cancelada por el administrador.', true, 'cancelled');
        });
    }

    /**
     * Vuelve a la versión anterior conservada (releases/_prev-*). La base de
     * datos no se toca: las migraciones son aditivas; la copia previa a la
     * actualización sigue en Backups por si hace falta.
     */
    public function rollbackToPrevious(): array
    {
        return $this->withLock(function () {
            $state = $this->readState();
            if ($this->isBusy($state)) throw new UpdateBusyException('Hay una actualización en curso.');
            $prev = $this->latestPrevious();
            if (!$prev) throw new RuntimeException('No hay una versión anterior guardada.');
            $installed = AppVersion::current();
            $state = [
                'id' => bin2hex(random_bytes(6)),
                'kind' => 'rollback',
                'status' => 'running',
                'step' => 'switch',
                'fromVersion' => $installed,
                'toVersion' => $prev['version'],
                'release' => ['version' => $prev['version']],
                'startedAt' => $this->now(),
                'notices' => [],
                'log' => [],
                'error' => null,
            ];
            $this->log($state, "Volviendo de $installed a {$prev['version']}");
            $state = $this->writeState($state);
            try {
                $state = $this->swapIn($state, $prev['dir'], $installed);
                // La carpeta _prev quedó vacía (su contenido volvió a la raíz).
                $state = $this->verifyAfterSwitch($state);
                if (($state['status'] ?? '') === 'switched') $this->rrmdirIfOnlyPreserved($prev['dir']);
                return $state;
            } catch (Throwable $e) {
                return $this->fail($this->readState(), $e->getMessage());
            }
        });
    }

    /** Versión anterior más reciente disponible para volver atrás. */
    public function latestPrevious(): ?array
    {
        $dirs = glob($this->releasesDir() . '/_prev-*', GLOB_ONLYDIR) ?: [];
        usort($dirs, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach ($dirs as $d) {
            $v = trim((string) @file_get_contents("$d/VERSION"));
            if (AppVersion::isValid($v) && is_file("$d/index.html") && is_dir("$d/api")) {
                return ['version' => $v, 'dir' => $d];
            }
        }
        return null;
    }

    // ─── Fallos, limpieza y poda ──────────────────────────────────────

    private function fail(array $state, string $message, bool $cleanup = true, string $status = 'failed'): array
    {
        if ($cleanup) $this->cleanupStaging($state);
        $state['status'] = $status;
        $state['error'] = $message;
        $state['finishedAt'] = $this->now();
        unset($state['opsToken'], $state['rescueToken']);
        $this->log($state, $message, 'error');
        $this->appendHistory($state);
        return $this->writeState($state);
    }

    /** Borra el zip y la carpeta (completa o a medio extraer) de la versión en curso. */
    private function cleanupStaging(array $state): void
    {
        if (empty($state['toVersion']) || ($state['kind'] ?? '') === 'rollback') return;
        @unlink($this->zipPath($state));
        $this->rrmdir($this->stagingDir($state) . '.partial');
        $this->rrmdir($this->stagingDir($state));
    }

    /** Tareas que hace la versión nueva al arrancar (ver boot()). */
    public function maintenance(): void
    {
        try {
            $this->withLock(function () {
                $state = $this->readState();
                if (($state['status'] ?? '') === 'switched' && is_file($this->marker('finalize'))) {
                    $this->doFinalize($state);
                }
            });
        } catch (UpdateBusyException $e) {
            return; // otro request lo está haciendo
        }
        if (is_file($this->marker('prune')) && !$this->isBusy()) $this->prune();
    }

    public function prune(): void
    {
        @unlink($this->marker('prune'));
        $state = $this->readState();
        $active = $this->isBusy($state);

        // Versiones anteriores: conservar las KEEP_RELEASES más recientes.
        $prevs = glob($this->releasesDir() . '/_prev-*', GLOB_ONLYDIR) ?: [];
        usort($prevs, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($prevs, self::KEEP_RELEASES) as $d) {
            if ($active && $d === ($state['prevDir'] ?? null)) continue;
            $this->rrmdir($d);
        }
        // Carpetas de staging huérfanas y zips viejos (si no hay actualización en curso).
        if (!$active) {
            foreach (glob($this->releasesDir() . '/*', GLOB_ONLYDIR) ?: [] as $d) {
                if (!str_starts_with(basename($d), '_prev-')) $this->rrmdir($d);
            }
            foreach (glob($this->tmpDir() . '/*') ?: [] as $f) @unlink($f);
        }
        // Copias de la base previas a actualizaciones: KEEP_DB_BACKUPS.
        $bdir = $this->appRoot . '/storage/backups';
        $baks = glob("$bdir/pre-update-*") ?: [];
        usort($baks, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($baks, self::KEEP_DB_BACKUPS) as $f) @unlink($f);
        // Copias del .env que deja la reparación: KEEP_ENV_BACKUPS.
        $envs = glob($this->appRoot . '/.env.bak-*') ?: [];
        usort($envs, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($envs, self::KEEP_ENV_BACKUPS) as $f) @unlink($f);
    }

    /**
     * Gancho barato para bootstrap: solo hace algo si hay un marcador
     * pendiente (dos is_file por request en el caso normal).
     */
    public static function boot(): void
    {
        $appRoot = dirname(__DIR__);
        foreach (self::candidateWorkDirs($appRoot) as $wd) {
            if (is_file("$wd/.finalize-pending") || is_file("$wd/.prune-pending")) {
                try {
                    (new self($appRoot, $wd, self::forApp()->deps))->maintenance();
                } catch (Throwable $e) {
                    if (class_exists('Logger')) Logger::error('Updater boot: ' . $e->getMessage());
                }
                return;
            }
        }
    }

    // ─── Instalación automática (cron) ────────────────────────────────

    /**
     * Llamado desde el cron de la cola: consulta GitHub como mucho 1 vez por
     * hora y, si la instalación automática está activa y hay versión nueva,
     * dispara api/update-auto.php por HTTP (corre en PHP-FPM, no en CLI).
     */
    public static function cronTick(): void
    {
        try {
            $um = self::forApp();
            if (!$um->isDeployedLayout()) return;
            $latest = $um->checkLatest(false);
            $rel = $latest['release'] ?? null;
            if (!$rel || !AppVersion::isNewer($rel['version'], AppVersion::current())) return;
            if (!self::autoInstallEnabled() || ($rel['manifest']['autoInstall'] ?? true) === false) return;
            if ($um->isBusy() || $um->autoAttempted($rel['version'])) return;

            $token = QueueManager::ensureCronToken();
            if ($token === '') return;
            $ch = curl_init(QueueManager::appBaseUrl() . '/api/update-auto.php?token=' . urlencode($token));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 2000, CURLOPT_TIMEOUT_MS => 2500,
                CURLOPT_NOSIGNAL => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            @curl_exec($ch);
            @curl_close($ch);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::warning('Updater cronTick: ' . $e->getMessage());
        }
    }

    public function autoAttempted(string $version): bool
    {
        $f = json_decode((string) @file_get_contents($this->workDir . '/auto.json'), true) ?: [];
        return isset($f[$version]);
    }

    private function markAutoAttempt(string $version): void
    {
        $f = json_decode((string) @file_get_contents($this->workDir . '/auto.json'), true) ?: [];
        $f[$version] = $this->now();
        @file_put_contents($this->workDir . '/auto.json', json_encode($f));
    }

    /**
     * Ejecuta la instalación completa sin navegador. $externalMigrate recibe
     * el opsToken y debe ejecutar api/update-migrate.php (petición aparte).
     * Se intenta UNA vez por versión: si falla, queda avisado en el panel.
     */
    public function runAutomatic(callable $externalMigrate): array
    {
        $latest = $this->checkLatest(true);
        $v = $latest['release']['version'] ?? null;
        if ($v) $this->markAutoAttempt($v);
        $state = $this->begin('auto');
        for ($i = 0; $i < 20 && ($state['status'] ?? '') === 'running'; $i++) {
            if ($state['step'] === 'migrate' && !$this->deps['migrate']) {
                $externalMigrate((string) $state['opsToken']);
                $state = $this->readState();
                if (($state['status'] ?? '') === 'running' && $state['step'] === 'migrate') {
                    $state = $this->withLock(fn() => $this->fail($this->readState(), 'No se pudieron ejecutar las migraciones por HTTP (auto-llamada).'));
                }
                continue;
            }
            $state = $this->step();
        }
        return $state;
    }

    // ─── Utilidades ───────────────────────────────────────────────────

    public static function isPreserved(string $entry): bool
    {
        if (in_array($entry, self::PRESERVE, true)) return true;
        return str_starts_with($entry, '.env') && $entry !== '.env.example';
    }

    private static function swapRank(string $e): int
    {
        return match ($e) { 'index.html' => 3, 'api' => 2, 'assets' => 1, default => 0 };
    }

    private function reverseJournal(array $journal): void
    {
        $errors = [];
        foreach (array_reverse($journal) as [$from, $to]) {
            if (!@rename($to, $from)) $errors[] = basename($to);
        }
        if ($errors && class_exists('Logger')) Logger::error('Updater: no se pudo revertir: ' . implode(', ', $errors));
    }

    private function appendHistory(array $state): void
    {
        $h = $this->readHistory();
        array_unshift($h, [
            'id' => $state['id'] ?? '',
            'kind' => $state['kind'] ?? 'manual',
            'from' => $state['fromVersion'] ?? '',
            'to' => $state['toVersion'] ?? '',
            'status' => $state['status'] ?? '',
            'startedAt' => $state['startedAt'] ?? null,
            'finishedAt' => $this->now(),
            'error' => $state['error'] ?? null,
        ]);
        @file_put_contents($this->workDir . '/history.json', json_encode(array_slice($h, 0, 30), JSON_UNESCAPED_UNICODE));
    }

    public function readHistory(): array
    {
        return json_decode((string) @file_get_contents($this->workDir . '/history.json'), true) ?: [];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear la carpeta $dir");
        }
    }

    /** Borrado recursivo, SOLO dentro de la carpeta de trabajo. */
    private function rrmdir(string $dir): void
    {
        if ($dir === '' || !file_exists($dir)) return;
        $real = realpath($dir);
        $base = realpath($this->workDir);
        if ($real === false || $base === false || !str_starts_with($real, $base . '/')) {
            throw new RuntimeException('Se evitó borrar una ruta fuera de la carpeta de trabajo.');
        }
        if (is_link($dir) || is_file($dir)) { @unlink($dir); return; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($real);
    }

    private function rrmdirIfOnlyPreserved(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $e) {
            if ($e !== '.' && $e !== '..' && !self::isPreserved($e)) return;
        }
        $this->rrmdir($dir);
    }

    public static function probeInodes(string $path): ?int
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!function_exists('shell_exec') || in_array('shell_exec', $disabled, true)) return null;
        $out = @shell_exec('df -Pi ' . escapeshellarg($path) . ' 2>/dev/null');
        if (!$out) return null;
        $lines = preg_split('/\n/', trim($out));
        $cols = preg_split('/\s+/', (string) end($lines));
        return isset($cols[3]) && ctype_digit($cols[3]) ? (int) $cols[3] : null;
    }

    private static function estimateDbSize(): int
    {
        try {
            $db = Database::getInstance();
            if ($db->driver() === 'sqlite') {
                return (int) $db->scalar('PRAGMA page_count') * (int) $db->scalar('PRAGMA page_size');
            }
            return (int) $db->scalar('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()');
        } catch (Throwable $e) {
            return 50 * 1024 * 1024;
        }
    }

    /**
     * Health-check por HTTP a la propia app. reachable=false si ni siquiera
     * hubo respuesta (hosting que no permite auto-llamadas): en ese caso la
     * versión nueva se verifica sola en su primera petición.
     */
    public static function httpHealth(string $base, string $version): array
    {
        $url = rtrim($base, '/') . '/api/health.php?ready=1&expect=' . urlencode($version) . '&_=' . bin2hex(random_bytes(4));
        $detail = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($code === 0) {
                $detail = $err ?: 'sin respuesta';
                if ($attempt < 3) { sleep(1); continue; }
                return ['reachable' => false, 'ok' => false, 'detail' => $detail];
            }
            $json = json_decode((string) $body, true);
            $data = $json['data'] ?? [];
            if ($code === 200 && ($data['ready'] ?? false) === true && ($data['version'] ?? '') === $version) {
                return ['reachable' => true, 'ok' => true, 'detail' => "health $code, v$version"];
            }
            $detail = "HTTP $code" . (isset($data['version']) ? ', versión ' . $data['version'] : '') . (isset($data['problems']) ? ', ' . implode('; ', (array) $data['problems']) : '');
            // OPcache puede tardar ~2 s en revalidar: reintentar antes de dar por fallida.
            if ($attempt < 3) sleep(2);
        }
        return ['reachable' => true, 'ok' => false, 'detail' => $detail];
    }

    private static function fmtBytes(float $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return round($b, 1) . ' ' . $u[$i];
    }
}

class UpdateBusyException extends RuntimeException {}
