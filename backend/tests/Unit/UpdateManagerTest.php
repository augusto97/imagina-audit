<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/** Origen de releases falso: sirve zips armados en el test. */
final class FakeReleaseSource
{
    public int $downloads = 0;
    public bool $wrongSha = false;
    public function __construct(public array $release, public string $zipFile) {}
    public function latest(): ?array { return $this->release; }
    public function fetchText(string $url, int $max): string
    {
        $sha = $this->wrongSha ? str_repeat('0', 64) : hash_file('sha256', $this->zipFile);
        return "$sha  " . basename($this->zipFile) . "\n";
    }
    public function download(string $url, string $dest, int $max): void
    {
        $this->downloads++;
        copy($this->zipFile, $dest);
    }
}

/**
 * Orquestación del actualizador con un "deployer" falso (carpetas
 * temporales, health-check, backup y migraciones simulados).
 */
final class UpdateManagerTest extends TestCase
{
    private const NEW = '99.0.0';
    private string $base;
    private string $app;
    private string $work;
    private array $healthResult;
    private array $migrated = [];
    private float|false $diskFree = 50e9;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/upd-' . bin2hex(random_bytes(4));
        $this->app = $this->base . '/app';
        $this->work = $this->base . '/work';
        $this->healthResult = ['reachable' => true, 'ok' => true, 'detail' => 'ok'];
        $this->migrated = [];
        $this->diskFree = 50e9;

        // Instalación "actual"
        $this->write($this->app, [
            'index.html' => 'OLD-INDEX',
            'VERSION' => '2.7.0',
            'api/index.php' => '<?php // old',
            'api/update-migrate.php' => '<?php // old',
            'lib/Database.php' => '<?php // old',
            'lib/Migrator.php' => '<?php // old',
            'lib/UpdateManager.php' => '<?php // old',
            'assets/old.js' => 'old',
            'legacy-old-only' => 'archivo que la versión nueva ya no trae',
            'data/known-plugins.json' => '{}',
            'data/.installed' => '{"installed":true}',
            'database/migrations/0001_initial.sql' => '-- x',
            'database/audit.db' => 'SQLITE-LIVE-DATA',
            '.htaccess' => 'HTACCESS-OLD',
            '.env' => "DB_DRIVER=sqlite\nMAIL_FROM=Yo <yo@x.com>\n",
            'uploads/logo.png' => 'LOGO',
            'storage/backups/.keep' => '',
            'cache/x.json' => '{}',
            'user-file.txt' => 'archivo del usuario, no es de la app',
        ]);
        file_put_contents($this->app . '/manifest.json', json_encode([
            'version' => '2.7.0',
            'entries' => ['.htaccess', 'VERSION', 'api', 'assets', 'data', 'database', 'index.html', 'legacy-old-only', 'lib'],
            'htaccessSha256' => hash('sha256', 'HTACCESS-OLD'),
        ]));
    }

    protected function tearDown(): void
    {
        $this->rm($this->base);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function write(string $root, array $files): void
    {
        foreach ($files as $rel => $content) {
            $p = "$root/$rel";
            if (!is_dir(dirname($p))) mkdir(dirname($p), 0755, true);
            file_put_contents($p, $content);
        }
    }

    private function rm(string $dir): void
    {
        if (!file_exists($dir)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        rmdir($dir);
    }

    /** Arma un paquete de release válido (o alterado con $extra / $raw). */
    private function makeRelease(array $extra = [], ?string $raw = null): FakeReleaseSource
    {
        $files = [
            'index.html' => 'NEW-INDEX',
            'VERSION' => self::NEW,
            'api/index.php' => '<?php // new',
            'api/update-migrate.php' => '<?php // new',
            'lib/Database.php' => '<?php // new',
            'lib/Migrator.php' => '<?php // new',
            'lib/UpdateManager.php' => '<?php // new',
            'assets/new.js' => 'new',
            'data/known-plugins.json' => '{"new":1}',
            'database/migrations/0001_initial.sql' => '-- x',
            'database/migrations/0099_new.sql' => '-- y',
            '.htaccess' => 'HTACCESS-NEW',
            '.env.example' => "DB_DRIVER=sqlite\nNEW_OPTION=on\nUPDATER_SECRET=\n",
            'update-rescue.php' => '<?php // rescue new',
            'cache/.htaccess' => 'deny',
        ];
        $files['manifest.json'] = json_encode([
            'version' => self::NEW, 'entries' => array_values(array_unique(array_map(fn($f) => explode('/', $f)[0], array_keys($files)))),
            'htaccessSha256' => hash('sha256', 'HTACCESS-NEW'), 'uncompressedBytes' => 5000, 'fileCount' => 20,
        ]);
        $files = array_merge($files, $extra);

        $zipPath = $this->base . '/release-' . bin2hex(random_bytes(3)) . '.zip';
        if ($raw !== null) {
            file_put_contents($zipPath, $raw);
        } else {
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($files as $name => $content) $zip->addFromString($name, $content);
            $zip->close();
        }
        return new FakeReleaseSource([
            'version' => self::NEW, 'tag' => 'v' . self::NEW, 'name' => 'v' . self::NEW, 'body' => 'notas',
            'zip' => ['name' => basename($zipPath), 'url' => 'https://example.invalid/z.zip', 'size' => filesize($zipPath)],
            'sha256Url' => 'https://example.invalid/z.zip.sha256',
            'manifest' => ['version' => self::NEW, 'uncompressedBytes' => 5000, 'fileCount' => 20, 'notices' => ['aviso de prueba']],
        ], $zipPath);
    }

    private function manager(FakeReleaseSource $src, array $over = []): UpdateManager
    {
        return new UpdateManager($this->app, $this->work, $over + [
            'source' => $src,
            'backup' => function () {
                $p = $this->app . '/storage/backups/' . date('Ymd-His') . '-sqlite.sql.gz';
                file_put_contents($p, 'dump');
                return $p;
            },
            'health' => fn(string $v) => $this->healthResult,
            'diskFree' => fn(string $p) => $this->diskFree,
            'inodesFree' => fn(string $p) => null,
            'dbSize' => fn() => 1000,
            'migrate' => function (string $dir) { $this->migrated[] = $dir; return ['0099_new']; },
            'readyCheck' => fn(string $v) => null,
            'opcacheReset' => function () {},
        ]);
    }

    /** Corre pasos hasta que la actualización deja de estar "running". */
    private function runUntilSettled(UpdateManager $um): array
    {
        $state = $um->begin();
        for ($i = 0; $i < 15 && $state['status'] === 'running'; $i++) $state = $um->step();
        return $state;
    }

    private function assertNoLeftovers(): void
    {
        $this->assertSame([], glob($this->work . '/tmp/*') ?: [], 'no queda el zip');
        $staging = array_filter(glob($this->work . '/releases/*') ?: [], fn($d) => !str_contains(basename($d), '_prev-'));
        $this->assertSame([], array_values($staging), 'no queda la carpeta a medio extraer');
    }

    private function assertOldVersionIntact(): void
    {
        $this->assertSame('OLD-INDEX', file_get_contents($this->app . '/index.html'));
        $this->assertSame('2.7.0', file_get_contents($this->app . '/VERSION'));
        $this->assertFileExists($this->app . '/assets/old.js');
        $this->assertFileExists($this->app . '/legacy-old-only');
        $this->assertSame('SQLITE-LIVE-DATA', file_get_contents($this->app . '/database/audit.db'));
    }

    // ─── Casos ────────────────────────────────────────────────────────

    #[Test]
    public function updates_successfully_and_preserves_site_data(): void
    {
        $um = $this->manager($this->makeRelease());
        $state = $this->runUntilSettled($um);
        $this->assertSame('switched', $state['status'], json_encode($state['log'] ?? []));
        $this->assertTrue($state['verified']);

        $state = $um->step(); // finalize (en la vida real lo hace el código nuevo)
        $this->assertSame('completed', $state['status']);

        // Código nuevo en su lugar
        $this->assertSame('NEW-INDEX', file_get_contents($this->app . '/index.html'));
        $this->assertSame(self::NEW, file_get_contents($this->app . '/VERSION'));
        $this->assertFileExists($this->app . '/assets/new.js');
        $this->assertFileDoesNotExist($this->app . '/assets/old.js');
        $this->assertFileDoesNotExist($this->app . '/legacy-old-only', 'lo que la versión nueva ya no trae se retira');
        $this->assertSame('HTACCESS-NEW', file_get_contents($this->app . '/.htaccess'), '.htaccess sin tocar a mano → se actualiza');

        // Datos del sitio intactos
        $this->assertSame('LOGO', file_get_contents($this->app . '/uploads/logo.png'));
        $this->assertSame('archivo del usuario, no es de la app', file_get_contents($this->app . '/user-file.txt'));
        $this->assertSame('{"installed":true}', file_get_contents($this->app . '/data/.installed'));
        $this->assertSame('SQLITE-LIVE-DATA', file_get_contents($this->app . '/database/audit.db'), 'la base SQLite viva sigue en su sitio');
        $this->assertSame('{}', file_get_contents($this->app . '/cache/x.json'));
        $env = EnvFile::parseFile($this->app . '/.env');
        $this->assertSame('Yo <yo@x.com>', $env['MAIL_FROM']);
        $this->assertSame('on', $env['NEW_OPTION'], '.env reparado con la clave nueva');
        $this->assertNotSame('', $env['UPDATER_SECRET']);
        $this->assertSame('<?php // rescue new', file_get_contents($this->app . '/update-rescue.php'));

        // Migraciones con el paquete nuevo, ANTES del cambio, con backup previo
        $this->assertSame([$this->work . '/releases/' . self::NEW], $this->migrated);
        $this->assertCount(1, glob($this->app . '/storage/backups/pre-update-v2.7.0-to-v' . self::NEW . '-*'));

        // Versión anterior guardada para volver atrás; sin restos
        $prev = $um->latestPrevious();
        $this->assertSame('2.7.0', $prev['version']);
        $this->assertNoLeftovers();
        $this->assertSame('completed', $um->readHistory()[0]['status']);
    }

    #[Test]
    public function failed_health_check_rolls_back_automatically(): void
    {
        $this->healthResult = ['reachable' => true, 'ok' => false, 'detail' => 'HTTP 500'];
        $um = $this->manager($this->makeRelease());
        $state = $this->runUntilSettled($um);

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('Se restauró la versión 2.7.0', $state['error']);
        $this->assertOldVersionIntact();
        $this->assertNoLeftovers();
        $this->assertNull($um->latestPrevious(), 'no queda carpeta _prev vacía');
        $this->assertFalse($um->isBusy(), 'el candado se libera');
    }

    #[Test]
    public function insufficient_disk_space_does_not_download(): void
    {
        $this->diskFree = 1000.0;
        $src = $this->makeRelease();
        $state = $this->runUntilSettled($this->manager($src));

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('Libre', $state['error']);
        $this->assertSame(0, $src->downloads, 'no se descargó nada');
        $this->assertNoLeftovers();
        $this->assertOldVersionIntact();
    }

    #[Test]
    public function broken_zip_leaves_no_garbage(): void
    {
        $src = $this->makeRelease([], 'esto no es un zip');
        $state = $this->runUntilSettled($this->manager($src));

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('dañado', $state['error']);
        $this->assertNoLeftovers();
        $this->assertOldVersionIntact();
    }

    #[Test]
    public function sha256_mismatch_aborts_and_deletes_download(): void
    {
        $src = $this->makeRelease();
        $src->wrongSha = true;
        $state = $this->runUntilSettled($this->manager($src));

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('sha256', $state['error']);
        $this->assertNoLeftovers();
        $this->assertOldVersionIntact();
    }

    #[Test]
    public function zip_slip_is_rejected(): void
    {
        $state = $this->runUntilSettled($this->manager($this->makeRelease(['../../evil.php' => '<?php evil'])));

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('Ruta no permitida', $state['error']);
        $this->assertFileDoesNotExist($this->base . '/evil.php');
        $this->assertFileDoesNotExist(dirname($this->base) . '/evil.php');
        $this->assertNoLeftovers();
    }

    #[Test]
    public function incomplete_package_is_rejected(): void
    {
        $src = $this->makeRelease(['VERSION' => '1.0.0']); // dice ser otra versión
        $state = $this->runUntilSettled($this->manager($src));
        $this->assertSame('failed', $state['status']);
        $this->assertNoLeftovers();
        $this->assertOldVersionIntact();
    }

    #[Test]
    public function migration_failure_keeps_old_version_and_cleans_up(): void
    {
        $um = $this->manager($this->makeRelease(), ['migrate' => fn() => throw new RuntimeException('SQL roto')]);
        $state = $this->runUntilSettled($um);
        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('SQL roto', $state['error']);
        $this->assertOldVersionIntact();
        $this->assertNoLeftovers();
    }

    #[Test]
    public function only_one_update_at_a_time(): void
    {
        $um = $this->manager($this->makeRelease());
        $um->begin();
        $this->expectException(UpdateBusyException::class);
        $um->begin();
    }

    #[Test]
    public function hand_edited_htaccess_is_preserved(): void
    {
        file_put_contents($this->app . '/.htaccess', 'HTACCESS-EDITADO-A-MANO');
        $um = $this->manager($this->makeRelease());
        $state = $this->runUntilSettled($um);
        $this->assertSame('switched', $state['status']);
        $this->assertSame('HTACCESS-EDITADO-A-MANO', file_get_contents($this->app . '/.htaccess'));
        $this->assertSame('HTACCESS-NEW', file_get_contents($this->app . '/.htaccess.new'));
        $this->assertNotEmpty(array_filter($state['notices'], fn($n) => str_contains($n, '.htaccess')));
    }

    #[Test]
    public function unreachable_health_check_is_finalized_by_new_version(): void
    {
        $this->healthResult = ['reachable' => false, 'ok' => false, 'detail' => 'sin loopback'];
        $um = $this->manager($this->makeRelease());
        $state = $this->runUntilSettled($um);
        $this->assertSame('switched', $state['status']);
        $this->assertFalse($state['verified']);
        $this->assertNotEmpty($um->publicState($state)['rescueToken'] ?? null);

        // Primera petición de la versión nueva (bootstrap → maintenance)
        $um->maintenance();
        $this->assertSame('completed', $um->readState()['status']);
    }

    #[Test]
    public function rescue_script_reverts_an_unfinished_switch(): void
    {
        $this->healthResult = ['reachable' => false, 'ok' => false, 'detail' => 'sin loopback'];
        $um = $this->manager($this->makeRelease());
        $state = $this->runUntilSettled($um);
        $this->assertSame('NEW-INDEX', file_get_contents($this->app . '/index.html'));

        // El rescate es el script de la raíz, autónomo. Se ejecuta en otro
        // proceso PHP como lo haría el servidor web.
        copy(dirname(__DIR__, 2) . '/update-rescue.php', $this->app . '/update-rescue.php');
        file_put_contents($this->app . '/.env', "UPDATE_WORK_DIR={$this->work}\n", FILE_APPEND);
        $runner = $this->base . '/run-rescue.php';
        file_put_contents($runner, '<?php $_GET = ["token" => $argv[1], "format" => "json"]; include ' . var_export($this->app . '/update-rescue.php', true) . ';');

        $bad = shell_exec(PHP_BINARY . ' ' . escapeshellarg($runner) . ' tokenmalo');
        $this->assertStringContainsString('token', (string) $bad);
        $this->assertSame('NEW-INDEX', file_get_contents($this->app . '/index.html'), 'sin token no hace nada');

        $out = shell_exec(PHP_BINARY . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($state['rescueToken']));
        $this->assertStringContainsString('"success":true', (string) $out);
        $this->assertOldVersionIntact();
        $this->assertSame('rolled_back', $um->readState()['status']);
    }

    #[Test]
    public function manual_rollback_to_previous_version(): void
    {
        $um = $this->manager($this->makeRelease());
        $this->runUntilSettled($um);
        $um->step();
        $this->assertSame(self::NEW, file_get_contents($this->app . '/VERSION'));

        $state = $um->rollbackToPrevious();
        $this->assertSame('switched', $state['status'], json_encode($state['log'] ?? []));
        $um->step();
        $this->assertSame('completed', $um->readState()['status']);
        $this->assertOldVersionIntact();
        $this->assertSame('SQLITE-LIVE-DATA', file_get_contents($this->app . '/database/audit.db'));
        $this->assertSame('LOGO', file_get_contents($this->app . '/uploads/logo.png'));
    }

    #[Test]
    public function cancel_before_switch_cleans_up(): void
    {
        $um = $this->manager($this->makeRelease());
        $um->begin();
        $um->step(); // preflight
        $um->step(); // download
        $this->assertNotEmpty(glob($this->work . '/tmp/*'));
        $state = $um->cancel();
        $this->assertSame('cancelled', $state['status']);
        $this->assertNoLeftovers();
        $this->assertOldVersionIntact();
    }

    #[Test]
    public function prune_keeps_three_versions_and_five_db_copies(): void
    {
        $um = $this->manager($this->makeRelease());
        mkdir($this->work . '/releases', 0755, true);
        for ($i = 1; $i <= 6; $i++) {
            $d = $this->work . "/releases/_prev-2.$i.0-x";
            mkdir($d);
            touch($d, time() - 1000 + $i);
            $b = $this->app . "/storage/backups/pre-update-v2.$i.0-to-v2.9.0-x.sql.gz";
            file_put_contents($b, 'x');
            touch($b, time() - 1000 + $i);
            $e = $this->app . "/.env.bak-2026010$i";
            file_put_contents($e, 'x');
            touch($e, time() - 1000 + $i);
        }
        file_put_contents($this->base . '/../no-tocar-' . basename($this->base), 'x');
        $um->prune();
        $this->assertCount(3, glob($this->work . '/releases/_prev-*'));
        $this->assertCount(5, glob($this->app . '/storage/backups/pre-update-*'));
        $this->assertCount(3, glob($this->app . '/.env.bak-*'));
        $this->assertFileExists($this->work . '/releases/_prev-2.6.0-x', 'conserva las más recientes');
        @unlink($this->base . '/../no-tocar-' . basename($this->base));
    }

    #[Test]
    public function zip_path_validation(): void
    {
        foreach (['../x', 'a/../../x', '/etc/passwd', 'C:/x', "a\\b", "a\0b", ''] as $bad) {
            $this->assertFalse(UpdateManager::isSafeZipPath($bad), $bad);
        }
        foreach (['index.html', 'api/index.php', 'assets/a..b.js', '.htaccess'] as $ok) {
            $this->assertTrue(UpdateManager::isSafeZipPath($ok), $ok);
        }
    }
}
