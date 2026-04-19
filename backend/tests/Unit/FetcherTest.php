<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests para los helpers internos de Fetcher — sin tocar red.
 * Usa reflection para invocar los métodos privados de validación y resolución.
 */
final class FetcherTest extends TestCase
{
    private function invoke(string $method, array $args): mixed
    {
        $ref = new ReflectionClass(Fetcher::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    public static function blockedUrls(): array
    {
        return [
            'localhost'   => ['http://localhost'],
            'loopback v4' => ['http://127.0.0.1'],
            'private 10'  => ['http://10.0.0.1'],
            'private 192' => ['http://192.168.1.1'],
            'metadata aws'=> ['http://169.254.169.254/latest/meta-data/'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme'  => ['ftp://example.com/file'],
            'empty'       => [''],
            'malformed'   => ['not-a-url'],
        ];
    }

    #[Test]
    #[DataProvider('blockedUrls')]
    public function validate_url_rejects_unsafe_targets(string $url): void
    {
        $this->assertNull($this->invoke('validateUrlForRequest', [$url]));
    }

    #[Test]
    public function validate_url_accepts_public_https(): void
    {
        $result = $this->invoke('validateUrlForRequest', ['https://www.google.com/']);
        $this->assertIsArray($result);
        $this->assertSame('www.google.com', $result['host']);
        $this->assertSame(443, $result['port']);
    }

    public static function redirectCases(): array
    {
        $base = 'https://example.com/blog/post';
        return [
            'absolute http'          => [$base, 'https://otro.com/ruta', 'https://otro.com/ruta'],
            'absolute path'          => [$base, '/nuevo', 'https://example.com/nuevo'],
            'protocol-relative'      => [$base, '//foo.com/bar', 'https://foo.com/bar'],
            'relative sibling'       => [$base, 'sibling', 'https://example.com/blog/sibling'],
            'empty location'         => [$base, '', null],
            'file scheme blocked'    => [$base, 'file:///etc/passwd', null],
            'javascript blocked'     => [$base, 'javascript:alert(1)', null],
        ];
    }

    #[Test]
    #[DataProvider('redirectCases')]
    public function redirect_resolution_handles_relatives_and_blocks_schemes(
        string $base,
        string $location,
        ?string $expected
    ): void {
        $result = $this->invoke('resolveRedirectUrl', [$base, $location]);
        $this->assertSame($expected, $result);
    }
}
