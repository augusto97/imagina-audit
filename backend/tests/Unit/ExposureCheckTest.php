<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprobación real por HTTP de carpetas expuestas, contra un servidor PHP
 * embebido que protege unas carpetas y deja otras abiertas.
 */
final class ExposureCheckTest extends TestCase
{
    private string $root;
    /** @var resource|null */
    private $proc = null;
    private int $port;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/exposure-' . bin2hex(random_bytes(4));
        foreach (ExposureCheck::PRIVATE_DIRS as $d) mkdir("{$this->root}/$d", 0755, true);
        file_put_contents("{$this->root}/.env", "DB_DRIVER=sqlite\nSECRET=x\n");

        // Router: protege storage/ y lib/ (403), sirve el resto tal cual.
        $router = "{$this->root}/router.php";
        file_put_contents($router, '<?php
$p = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (preg_match("#^/(storage|lib)/#", $p)) { http_response_code(403); echo "Forbidden"; return true; }
return false;');
        $this->port = random_int(20000, 40000);
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", '-t', $this->root, $router];
        $this->proc = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $this->port, $e, $es, 0.1);
            if ($s) { fclose($s); break; }
            usleep(100000);
        }
    }

    protected function tearDown(): void
    {
        if ($this->proc) { proc_terminate($this->proc); proc_close($this->proc); }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        rmdir($this->root);
    }

    #[Test]
    public function detects_exposed_and_protected_folders_for_real(): void
    {
        $r = ExposureCheck::run($this->root, "http://127.0.0.1:{$this->port}", null, true);

        $this->assertTrue($r['reachable']);
        sort($r['protected']);
        $this->assertSame(['lib', 'storage'], $r['protected']);
        foreach (['cache', 'logs', 'config', 'data', 'database', 'analyzers'] as $d) {
            $this->assertContains($d, $r['exposed'], "$d debería detectarse como expuesta");
        }
        $this->assertTrue($r['envExposed'], 'el .env servido debe detectarse');
        $this->assertStringContainsString('deny all', (string) $r['nginxSnippet']);
        $this->assertStringNotContainsString('cron', (string) $r['nginxSnippet'], 'nunca bloquear cron/ (la cola se dispara por HTTP)');
        $this->assertSame([], glob("{$this->root}/*/imagina-probe-*"), 'no quedan archivos de prueba');
    }

    #[Test]
    public function unreachable_server_is_reported_not_as_safe(): void
    {
        $r = ExposureCheck::run($this->root, 'http://127.0.0.1:1', null, true);
        $this->assertFalse($r['reachable']);
        $this->assertSame([], $r['exposed']);
        $this->assertSame([], glob("{$this->root}/*/imagina-probe-*"));
    }
}
