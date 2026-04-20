<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class JsonStoreTest extends TestCase
{
    #[Test]
    public function encode_produces_gzip_output(): void
    {
        $encoded = JsonStore::encode(['foo' => 'bar']);

        $this->assertNotSame('', $encoded);
        // Magic bytes de gzip
        $this->assertSame("\x1f\x8b", substr($encoded, 0, 2));
    }

    #[Test]
    public function roundtrip_preserves_data(): void
    {
        $data = [
            'id' => 'abc-123',
            'score' => 80,
            'modules' => [['id' => 'security', 'metrics' => ['a', 'b', 'c']]],
            'unicode' => 'Pérdida estimada €50',
        ];

        $roundtripped = JsonStore::decode(JsonStore::encode($data));

        $this->assertSame($data, $roundtripped);
    }

    #[Test]
    public function decode_handles_legacy_plain_json(): void
    {
        $data = ['legacy' => true, 'nested' => ['x' => 1]];
        $plainJson = json_encode($data);

        $this->assertSame($data, JsonStore::decode($plainJson));
    }

    #[Test]
    public function decode_null_and_empty_return_null(): void
    {
        $this->assertNull(JsonStore::decode(null));
        $this->assertNull(JsonStore::decode(''));
    }

    #[Test]
    public function decode_invalid_bytes_return_null(): void
    {
        $this->assertNull(JsonStore::decode('not valid json or gzip'));
        // Magic bytes de gzip pero payload corrupto
        $this->assertNull(JsonStore::decode("\x1f\x8bGARBAGE"));
    }

    #[Test]
    public function compression_reduces_size_on_realistic_payload(): void
    {
        // Simular un result_json típico con repetición de strings
        $data = [
            'modules' => array_map(fn($i) => [
                'id' => "mod_$i",
                'name' => 'Módulo de análisis número ' . $i,
                'metrics' => array_fill(0, 12, [
                    'description' => 'Descripción detallada del problema detectado',
                    'recommendation' => 'Optimizar la configuración del recurso',
                    'imaginaSolution' => 'Imagina WP resuelve esto mediante cache avanzado',
                ]),
            ], range(1, 8)),
        ];

        $plain = json_encode($data);
        $compressed = JsonStore::encode($data);

        $this->assertLessThan(strlen($plain), strlen($compressed));
        // En payloads realistas con repetición, gzip baja al menos al 50%
        $this->assertLessThan(strlen($plain) * 0.5, strlen($compressed));
    }
}
