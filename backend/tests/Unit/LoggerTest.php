<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verifica que Logger sanee PII antes de escribir al disco.
 * Usa reflection para invocar sanitizeString() directamente.
 */
final class LoggerTest extends TestCase
{
    private function sanitize(string $input): string
    {
        $ref = new ReflectionClass(Logger::class);
        $m = $ref->getMethod('sanitizeString');
        $m->setAccessible(true);
        return $m->invoke(null, $input);
    }

    private function sanitizeArray(array $input): array
    {
        $ref = new ReflectionClass(Logger::class);
        $m = $ref->getMethod('sanitizeArray');
        $m->setAccessible(true);
        return $m->invoke(null, $input);
    }

    #[Test]
    public function masks_emails_preserving_domain(): void
    {
        $out = $this->sanitize('Contacto: augusto@gmail.com y admin@empresa.co');
        $this->assertStringNotContainsString('augusto@gmail.com', $out);
        $this->assertStringContainsString('@gmail.com', $out);
        $this->assertStringContainsString('a***@gmail.com', $out);
        $this->assertStringContainsString('a***@empresa.co', $out);
    }

    #[Test]
    public function redacts_server_paths(): void
    {
        $out = $this->sanitize('Error en /home/user/imagina-audit/backend/lib/Auth.php línea 42');
        $this->assertStringNotContainsString('/home/user/imagina-audit', $out);
        $this->assertStringContainsString('[path]', $out);
        $this->assertStringContainsString('línea 42', $out);
    }

    #[Test]
    public function preserves_public_urls(): void
    {
        $out = $this->sanitize('Fetch a https://example.com/path failed');
        // Las URLs no deben ser tocadas por el filtro de paths
        $this->assertStringContainsString('https://example.com/path', $out);
    }

    #[Test]
    public function redacts_long_hex_tokens(): void
    {
        $token = str_repeat('a', 32);
        $out = $this->sanitize("token=$token foo");
        $this->assertStringNotContainsString($token, $out);
        $this->assertStringContainsString('[redacted]', $out);
    }

    #[Test]
    public function array_sanitize_masks_sensitive_keys(): void
    {
        $ctx = [
            'password' => 'super-secret',
            'api_key' => 'abc123',
            'harmless' => 'value',
            'nested' => [
                'token' => 'xyz',
                'email' => 'test@example.com',
            ],
        ];

        $out = $this->sanitizeArray($ctx);

        $this->assertSame('[redacted]', $out['password']);
        $this->assertSame('[redacted]', $out['api_key']);
        $this->assertSame('value', $out['harmless']);
        $this->assertSame('[redacted]', $out['nested']['token']);
        $this->assertStringContainsString('@example.com', $out['nested']['email']);
        $this->assertStringNotContainsString('test@example.com', $out['nested']['email']);
    }
}
