<?php
/**
 * Serialización comprimida (gzip) de payloads JSON para almacenamiento en BD.
 *
 * El `result_json` y `waterfall_json` de cada auditoría pueden pesar 500KB-2MB
 * en texto plano. Con compresión gzip nivel 6 se reducen típicamente a 15-25%
 * del tamaño original — clave para controlar el crecimiento de SQLite en
 * hosting compartido.
 *
 * Backwards-compatible: decode() detecta automáticamente si el valor ya viene
 * comprimido (magic bytes gzip 0x1f 0x8b) o si es JSON plano de filas antiguas.
 */

class JsonStore {
    private const GZIP_LEVEL = 6;

    /**
     * Codifica un array como JSON gzip-comprimido listo para guardar en BD.
     * Las columnas TEXT de SQLite aceptan bytes binarios sin problemas vía PDO.
     */
    public static function encode(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '';
        }
        $compressed = gzencode($json, self::GZIP_LEVEL);
        return $compressed === false ? $json : $compressed;
    }

    /**
     * Decodifica un valor almacenado. Detecta automáticamente si viene
     * comprimido (gzip) o como JSON plano (filas antiguas).
     */
    public static function decode(?string $raw): ?array {
        if ($raw === null || $raw === '') {
            return null;
        }

        // Magic bytes de gzip: 0x1f 0x8b
        if (strlen($raw) >= 2 && $raw[0] === "\x1f" && $raw[1] === "\x8b") {
            $json = @gzdecode($raw);
            if ($json === false) {
                return null;
            }
        } else {
            // Legacy: valor almacenado como JSON plano
            $json = $raw;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
