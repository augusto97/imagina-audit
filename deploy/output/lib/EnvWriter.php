<?php
/**
 * EnvWriter — helper para crear/actualizar `.env` desde código.
 *
 * El setup wizard lo usa para persistir las credenciales DB que el admin
 * mete en la UI, sin que tenga que abrir un editor de texto en el hosting.
 *
 * Comportamiento:
 *   - Si `.env` no existe, lo crea desde `.env.example` como template.
 *   - Si existe, preserva todas las claves que NO se están actualizando
 *     (comentarios y formato incluidos en lo posible).
 *   - Escritura atómica: escribe a un archivo temp y rename al final.
 *   - Permisos 0600 después del write.
 *
 * No usa Composer ni librerías externas — vanilla PHP para el hosting
 * compartido.
 */
class EnvWriter
{
    /** Path absoluto al `.env`. */
    public static function path(): string
    {
        return dirname(__DIR__) . '/.env';
    }

    /** Path absoluto al `.env.example` (template). */
    public static function templatePath(): string
    {
        return dirname(__DIR__) . '/.env.example';
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    /**
     * Actualiza N claves en `.env`. Si el archivo no existe, lo crea
     * partiendo de `.env.example`. Si la clave no existe en el template,
     * la añade al final del archivo.
     *
     * @param array<string,string> $updates Map clave => valor.
     * @return bool true si la escritura fue exitosa.
     */
    public static function update(array $updates): bool
    {
        $target = self::path();
        $contents = '';
        if (is_file($target)) {
            $contents = file_get_contents($target) ?: '';
        } elseif (is_file(self::templatePath())) {
            // Arrancamos desde el .env.example como base.
            $contents = file_get_contents(self::templatePath()) ?: '';
        }

        $lines = $contents === '' ? [] : explode("\n", rtrim($contents, "\n"));
        $written = [];
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $out[] = $line;
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) { $out[] = $line; continue; }
            $key = trim(substr($trimmed, 0, $pos));
            if (array_key_exists($key, $updates)) {
                $out[] = $key . '=' . self::escape($updates[$key]);
                $written[$key] = true;
            } else {
                $out[] = $line;
            }
        }
        // Claves que no estaban en el archivo → al final
        foreach ($updates as $key => $value) {
            if (!isset($written[$key])) {
                $out[] = $key . '=' . self::escape($value);
            }
        }

        $finalContents = implode("\n", $out) . "\n";

        // Escritura atómica: tmp file + rename. Si el hosting no permite
        // rename atómico, caemos a un write directo (igual el archivo no
        // tiene otros lectores concurrentes durante setup).
        $tmp = $target . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $finalContents) === false) {
            // Fallback: write directo
            if (file_put_contents($target, $finalContents) === false) return false;
            @chmod($target, 0600);
            return true;
        }
        if (!@rename($tmp, $target)) {
            @copy($tmp, $target);
            @unlink($tmp);
        }
        @chmod($target, 0600);
        return true;
    }

    /**
     * Escapa el valor para el formato .env. Si contiene espacios, #, o
     * caracteres especiales, lo envuelve en comillas dobles.
     */
    private static function escape(string $value): string
    {
        if ($value === '') return '';
        if (preg_match('/[\s#\'"\\\\]/', $value)) {
            // Comillas dobles: escapa " y \
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }
}
