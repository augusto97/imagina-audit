<?php
/**
 * Time-based One-Time Password (TOTP) según RFC 6238.
 *
 * Implementación standalone sin dependencias — útil en shared hosting
 * donde no podemos meter Composer.
 *
 * Compatible con Google Authenticator, Authy, 1Password, Bitwarden, etc.
 *
 * Uso típico:
 *   1. generateSecret() → guarda el base32 secret
 *   2. otpauthUri($secret, 'admin', 'Imagina Audit') → URI para QR
 *   3. verify($secret, $userInputCode) → true/false
 */

class Totp {
    private const PERIOD = 30;            // Segundos por ventana (estándar)
    private const DIGITS = 6;             // Longitud del código
    private const ALGORITHM = 'sha1';     // Soportado por todas las apps
    private const DRIFT_STEPS = 1;        // ±30s de tolerancia de reloj

    /**
     * Genera un secret de 20 bytes en base32 (estándar: 32 caracteres).
     */
    public static function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * URI otpauth:// lista para meter en un QR.
     *
     *   otpauth://totp/LABEL?secret=SECRET&issuer=ISSUER&digits=6&period=30
     */
    public static function otpauthUri(string $secret, string $label, string $issuer): string {
        $encodedLabel = rawurlencode($issuer . ':' . $label);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/$encodedLabel?$params";
    }

    /**
     * Verifica un código contra el secret tolerando ±1 time step (30s)
     * por clock drift entre cliente y servidor.
     */
    public static function verify(string $secret, string $code, int $time = 0): bool {
        if ($time === 0) $time = time();
        $code = preg_replace('/\D+/', '', $code);   // limpiar espacios/guiones
        if (strlen($code) !== self::DIGITS) return false;

        $currentStep = (int) floor($time / self::PERIOD);

        for ($i = -self::DRIFT_STEPS; $i <= self::DRIFT_STEPS; $i++) {
            $expected = self::generateCode($secret, $currentStep + $i);
            if (hash_equals($expected, $code)) return true;
        }
        return false;
    }

    /**
     * Genera el código para un step concreto. Útil para tests y para
     * UI previews. En producción llama a verify(), no a esto directamente.
     */
    public static function generateCode(string $secret, int $counter): string {
        $key = self::base32Decode($secret);
        if ($key === false) return '';

        // Counter as 8-byte big-endian
        $binCounter = pack('N*', 0) . pack('N*', $counter);

        $hash = hash_hmac(self::ALGORITHM, $binCounter, $key, true);

        // Dynamic truncation (RFC 4226 §5.3)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset])     & 0x7F) << 24)
               | ((ord($hash[$offset + 1]) & 0xFF) << 16)
               | ((ord($hash[$offset + 2]) & 0xFF) << 8)
               |  (ord($hash[$offset + 3]) & 0xFF);

        $modulus = 10 ** self::DIGITS;
        return str_pad((string) ($value % $modulus), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * URL de Google Charts para generar un QR del otpauth URI.
     * Úsala si no tienes generador de QR en frontend.
     *
     * Alternativa: en el frontend renderizar el QR con una librería JS
     * (qrcode, react-qr-code) — preferible porque no hace leak del
     * secret a un tercero.
     */
    public static function qrChartUrl(string $otpauthUri, int $size = 200): string {
        return sprintf(
            'https://chart.googleapis.com/chart?chs=%dx%d&chld=M|0&cht=qr&chl=%s',
            $size, $size, urlencode($otpauthUri)
        );
    }

    // ─── Base32 (RFC 4648 sin padding) ─────────────────────────────

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $bytes): string {
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $out .= self::BASE32_ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $out .= self::BASE32_ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }
        return $out;
    }

    public static function base32Decode(string $s): string|false {
        $s = strtoupper(preg_replace('/=+$/', '', $s));
        $buffer = 0;
        $bitsLeft = 0;
        $out = '';
        for ($i = 0; $i < strlen($s); $i++) {
            $c = $s[$i];
            $idx = strpos(self::BASE32_ALPHABET, $c);
            if ($idx === false) return false;
            $buffer = ($buffer << 5) | $idx;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $out;
    }

    /**
     * Genera códigos de recuperación (backup codes) para casos de
     * pérdida del dispositivo 2FA. 8 códigos de 10 hex chars cada uno.
     */
    public static function generateRecoveryCodes(int $count = 8): array {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5))); // 10 chars
        }
        return $codes;
    }

    /**
     * Hash de un recovery code para guardar en DB. Usamos SHA256
     * (no bcrypt — los códigos son alta entropía y se usan poco).
     */
    public static function hashRecoveryCode(string $code): string {
        return hash('sha256', strtoupper(preg_replace('/\s+/', '', $code)));
    }
}
