<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Lector dotenv: el .env nunca se ejecuta como script, así que valores que
 * romperían `source` en bash tienen que leerse tal cual.
 */
final class EnvFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/envfile-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) if (is_file($f)) unlink($f);
        @rmdir($this->dir);
    }

    #[Test]
    public function parses_values_that_would_break_bash(): void
    {
        $env = EnvFile::parse(implode("\n", [
            'MAIL_FROM=Nombre <correo@dominio.com>',
            'APP_NAME=Mi App & Co; echo pwned',
            'PASS=abc#123',
            'URL=https://x.com/?a=b=c',
            'HASH=$2y$10$abcdefghijklmnopqrstuv',
            'WITH_COMMENT=valor # comentario',
            'EMPTY=',
            '  SPACED  =  con espacios  ',
        ]));
        $this->assertSame('Nombre <correo@dominio.com>', $env['MAIL_FROM']);
        $this->assertSame('Mi App & Co; echo pwned', $env['APP_NAME']);
        $this->assertSame('abc#123', $env['PASS']);
        $this->assertSame('https://x.com/?a=b=c', $env['URL']);
        $this->assertSame('$2y$10$abcdefghijklmnopqrstuv', $env['HASH']);
        $this->assertSame('valor', $env['WITH_COMMENT']);
        $this->assertSame('', $env['EMPTY']);
        $this->assertSame('con espacios', $env['SPACED']);
    }

    #[Test]
    public function handles_quotes_escapes_and_multiline(): void
    {
        $env = EnvFile::parse(
            "DQ=\"hola # no es comentario\" # este sí\n" .
            "ESC=\"comilla \\\" y barra \\\\ fin\"\n" .
            "SQ='literal \$HOME \\n sin escapes'\n" .
            "NL=\"linea1\\nlinea2\"\n" .
            "MULTI=\"primera\nsegunda\"\n" .
            "AFTER=ok\n"
        );
        $this->assertSame('hola # no es comentario', $env['DQ']);
        $this->assertSame('comilla " y barra \\ fin', $env['ESC']);
        $this->assertSame('literal $HOME \\n sin escapes', $env['SQ']);
        $this->assertSame("linea1\nlinea2", $env['NL']);
        $this->assertSame("primera\nsegunda", $env['MULTI']);
        $this->assertSame('ok', $env['AFTER']);
    }

    #[Test]
    public function tolerates_bom_crlf_export_comments_and_invalid_lines(): void
    {
        $env = EnvFile::parse("\xEF\xBB\xBF# comentario\r\nexport DB_HOST=localhost\r\n\r\nsin_igual\r\n1BAD=x\r\nGOOD.KEY=y\r\n");
        $this->assertSame(['DB_HOST' => 'localhost', 'GOOD.KEY' => 'y'], $env);
    }

    #[Test]
    public function quote_round_trips_through_parse(): void
    {
        foreach (['simple', 'con espacios', 'a#b', 'comilla"doble', "simple'quote", 'barra\\final', 'Nombre <a@b.co>', ''] as $v) {
            $parsed = EnvFile::parse('K=' . EnvFile::quote($v));
            $this->assertSame($v, $parsed['K'], "round-trip de [$v]");
        }
    }

    #[Test]
    public function repair_adds_missing_keys_without_touching_existing(): void
    {
        $env = $this->dir . '/.env';
        $example = $this->dir . '/.env.example';
        file_put_contents($env, "DB_DRIVER=mysql\nMAIL_FROM=Mi Nombre <yo@x.com>\nAPP_DEBUG=true");
        file_put_contents($example, "DB_DRIVER=sqlite\nAPP_DEBUG=false\nNEW_SETTING=valor por defecto\nCRON_SECRET_TOKEN=\nGOOGLE_PAGESPEED_API_KEY=\n");

        $added = EnvFile::repair($env, $example, 'v9.9.9');
        sort($added);
        $this->assertSame(['CRON_SECRET_TOKEN', 'GOOGLE_PAGESPEED_API_KEY', 'NEW_SETTING'], $added);

        $after = EnvFile::parseFile($env);
        $this->assertSame('mysql', $after['DB_DRIVER'], 'no pisa valores existentes');
        $this->assertSame('true', $after['APP_DEBUG']);
        $this->assertSame('Mi Nombre <yo@x.com>', $after['MAIL_FROM']);
        $this->assertSame('valor por defecto', $after['NEW_SETTING']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $after['CRON_SECRET_TOKEN'], 'secreto nuevo aleatorio');
        $this->assertSame('', $after['GOOGLE_PAGESPEED_API_KEY'], 'una API key vacía sigue vacía');
        $this->assertCount(1, glob($this->dir . '/.env.bak-*'), 'deja copia del .env');

        // Idempotente
        $this->assertSame([], EnvFile::repair($env, $example));
    }

    #[Test]
    public function repair_only_adds_variables_new_in_this_version(): void
    {
        $env = $this->dir . '/.env';
        $old = $this->dir . '/old.env.example';
        $new = $this->dir . '/.env.example';
        file_put_contents($env, "DB_DRIVER=mysql\n");
        file_put_contents($old, "DB_DRIVER=sqlite\nALLOWED_ORIGIN=https://ejemplo.com\nRATE_LIMIT_MAX_PER_HOUR=10\n");
        file_put_contents($new, "DB_DRIVER=sqlite\nALLOWED_ORIGIN=https://ejemplo.com\nRATE_LIMIT_MAX_PER_HOUR=10\nFEATURE_X=on\n");

        $this->assertSame(['FEATURE_X'], EnvFile::repair($env, $new, '', $old));
        $after = EnvFile::parseFile($env);
        $this->assertArrayNotHasKey('ALLOWED_ORIGIN', $after, 'no añade claves viejas que el admin omitió a propósito');
        $this->assertArrayNotHasKey('RATE_LIMIT_MAX_PER_HOUR', $after);
        $this->assertSame('on', $after['FEATURE_X']);
    }
}
