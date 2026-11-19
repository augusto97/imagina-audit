<?php
/**
 * EnvFile — lector dotenv robusto + reparación de `.env` en actualizaciones.
 *
 * El `.env` NUNCA se ejecuta como script (nada de `source`): un valor como
 * `MAIL_FROM=Nombre <correo>` sin comillas rompería bash. Aquí se parsea
 * línea a línea con estas reglas:
 *
 *   - Ignora líneas vacías y comentarios (`# ...`), BOM y finales CRLF.
 *   - Acepta el prefijo `export KEY=...`.
 *   - Comillas dobles: admite escapes \" \\ \n \r \t y valores multilínea;
 *     lo que va después de la comilla de cierre se ignora (comentarios).
 *   - Comillas simples: literal, sin escapes.
 *   - Sin comillas: el valor llega hasta ` #` (comentario en línea) y se
 *     recorta. `=` dentro del valor se conserva (`URL=a?b=c`).
 *   - Claves inválidas se ignoran en vez de romper la carga.
 *
 * Compatible con lo que escribe EnvWriter (que entrecomilla valores con
 * espacios, #, comillas o barras).
 */
final class EnvFile
{
    /** @return array<string,string> */
    public static function parse(string $content): array
    {
        // BOM + normalizar saltos de línea
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $out = [];
        $n = count($lines);

        for ($i = 0; $i < $n; $i++) {
            $line = ltrim($lines[$i]);
            if ($line === '' || $line[0] === '#') continue;
            if (str_starts_with($line, 'export ')) $line = ltrim(substr($line, 7));

            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key)) continue;
            $rest = ltrim(substr($line, $eq + 1));

            if ($rest !== '' && $rest[0] === '"') {
                // Comillas dobles, posiblemente multilínea
                $buf = substr($rest, 1);
                $value = '';
                $closed = false;
                while (true) {
                    $len = strlen($buf);
                    for ($k = 0; $k < $len; $k++) {
                        $c = $buf[$k];
                        if ($c === '\\' && $k + 1 < $len) {
                            $nx = $buf[$k + 1];
                            $map = ['n' => "\n", 'r' => "\r", 't' => "\t", '"' => '"', '\\' => '\\'];
                            if (isset($map[$nx])) { $value .= $map[$nx]; $k++; continue; }
                            $value .= $c;
                            continue;
                        }
                        if ($c === '"') { $closed = true; break; }
                        $value .= $c;
                    }
                    if ($closed || $i + 1 >= $n) break;
                    // Continúa en la línea siguiente
                    $value .= "\n";
                    $buf = $lines[++$i];
                }
                $out[$key] = $value;
                continue;
            }

            if ($rest !== '' && $rest[0] === "'") {
                $end = strpos($rest, "'", 1);
                $out[$key] = $end === false ? substr($rest, 1) : substr($rest, 1, $end - 1);
                continue;
            }

            // Sin comillas: cortar comentario en línea (" #"), recortar.
            $hash = strpos($rest, ' #');
            if ($hash === false) $hash = strpos($rest, "\t#");
            if ($hash !== false) $rest = substr($rest, 0, $hash);
            $out[$key] = trim($rest);
        }
        return $out;
    }

    /** @return array<string,string> */
    public static function parseFile(string $path): array
    {
        if (!is_file($path)) return [];
        $content = @file_get_contents($path);
        return $content === false ? [] : self::parse($content);
    }

    /**
     * Añade al `.env` las variables NUEVAS de una versión, SIN tocar las
     * existentes.
     *
     * "Nuevas" = están en el `.env.example` de la versión que se instala y
     * no estaban en el de la versión instalada ($previousExamplePath). Así no
     * se añaden de golpe claves que el admin dejó fuera a propósito y que el
     * código ya resuelve con su valor por defecto (añadirlas con el valor del
     * ejemplo podría cambiar el comportamiento). Sin ejemplo previo, se
     * consideran nuevas todas las que falten.
     *
     * Claves secretas (…_SECRET, …_TOKEN, APP_KEY) que en el ejemplo vienen
     * vacías reciben un valor aleatorio; el resto toma el valor del ejemplo.
     * Antes de escribir deja una copia `.env.bak-FECHA`.
     *
     * @return string[] claves añadidas
     */
    public static function repair(string $envPath, string $examplePath, string $note = '', ?string $previousExamplePath = null): array
    {
        if (!is_file($envPath) || !is_file($examplePath)) return [];
        $current = self::parseFile($envPath);
        $example = self::parseFile($examplePath);
        if ($previousExamplePath !== null && is_file($previousExamplePath)) {
            $example = array_diff_key($example, self::parseFile($previousExamplePath));
        }
        $missing = array_diff_key($example, $current);
        if (!$missing) return [];

        $lines = [];
        foreach ($missing as $key => $value) {
            if ($value === '' && preg_match('/(_SECRET|_TOKEN|^APP_KEY)$/', $key)) {
                $value = bin2hex(random_bytes(24));
            }
            $lines[] = $key . '=' . self::quote($value);
        }

        $original = (string) file_get_contents($envPath);
        $block = ($original !== '' && !str_ends_with($original, "\n") ? "\n" : '')
            . "\n# Añadidas automáticamente por el actualizador" . ($note !== '' ? " ($note)" : '') . ' — ' . gmdate('Y-m-d H:i') . " UTC\n"
            . implode("\n", $lines) . "\n";

        @copy($envPath, dirname($envPath) . '/.env.bak-' . gmdate('Ymd-His'));
        $tmp = $envPath . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $original . $block) === false) {
            throw new RuntimeException('No se pudo escribir el .env');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $envPath)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo reemplazar el .env');
        }
        return array_keys($missing);
    }

    /** Formato seguro para escribir un valor (igual criterio que EnvWriter). */
    public static function quote(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        if ($value === '') return '';
        if (preg_match('/[\s#\'"\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }
}
