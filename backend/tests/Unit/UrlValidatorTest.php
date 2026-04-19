<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class UrlValidatorTest extends TestCase
{
    #[Test]
    public function adds_https_scheme_when_missing(): void
    {
        $url = UrlValidator::validate('example.com');
        $this->assertStringStartsWith('https://', $url);
    }

    #[Test]
    public function preserves_path(): void
    {
        $url = UrlValidator::validate('https://example.com/blog/post-123');
        $this->assertStringEndsWith('/blog/post-123', $url);
    }

    #[Test]
    public function strips_trailing_slash_except_root(): void
    {
        $this->assertStringEndsWith('/', UrlValidator::validate('https://example.com/'));
        $this->assertStringEndsWith('/page', UrlValidator::validate('https://example.com/page/'));
    }

    public static function blockedHosts(): array
    {
        return [
            ['localhost'],
            ['http://localhost:8080'],
            ['http://127.0.0.1'],
            ['http://0.0.0.0'],
            ['http://10.0.0.1'],
            ['http://192.168.1.1'],
            ['http://172.16.0.1'],
            ['http://169.254.169.254'], // AWS/GCP/Azure metadata
        ];
    }

    #[Test]
    #[DataProvider('blockedHosts')]
    public function rejects_private_and_reserved_addresses(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlValidator::validate($url);
    }

    #[Test]
    public function rejects_empty_or_malformed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UrlValidator::validate('http://');
    }

    #[Test]
    public function extract_domain_strips_www_and_lowercases(): void
    {
        $this->assertSame('ejemplo.com', UrlValidator::extractDomain('https://www.EJEMPLO.com/path'));
        $this->assertSame('sub.ejemplo.com', UrlValidator::extractDomain('https://sub.ejemplo.com'));
    }

    #[Test]
    public function resolve_url_handles_relative_paths(): void
    {
        $base = 'https://example.com/blog/post';
        $this->assertSame('https://example.com/other', UrlValidator::resolveUrl($base, '/other'));
        $this->assertSame('https://other.com/x', UrlValidator::resolveUrl($base, 'https://other.com/x'));
        $this->assertSame('https://example.com/x', UrlValidator::resolveUrl($base, '//example.com/x'));
    }
}
