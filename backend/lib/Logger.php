<?php
/**
 * Logger simple a archivo con saneamiento de PII y rotación automática.
 *
 * - Enmascara emails, URLs del servidor y tokens/hashes en el mensaje y contexto.
 * - Rota logs antiguos (>30 días) con probabilidad baja en cada escritura.
 */

class Logger {
    private const RETENTION_DAYS = 30;
    private const ROTATION_PROBABILITY = 100; // 1 de cada N escrituras dispara rotate()

    private static ?string $logDir = null;

    /**
     * Obtiene el directorio de logs
     */
    private static function getLogDir(): string {
        if (self::$logDir === null) {
            self::$logDir = dirname(__DIR__) . '/logs';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }

    /**
     * Registra un mensaje de información
     */
    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }

    /**
     * Registra un mensaje de error
     */
    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }

    /**
     * Registra un mensaje de advertencia
     */
    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }

    /**
     * Registra un evento de auditoría (solo dominio, no URL completa)
     */
    public static function audit(string $domain, int $score, float $durationSec): void {
        self::log('AUDIT', "Auditoría completada: $domain (score: $score, duración: {$durationSec}s)");
    }

    /**
     * Elimina archivos de log con más de RETENTION_DAYS días de antigüedad.
     * Pública para poder llamarla desde un cron si se prefiere.
     */
    public static function rotate(): void {
        $dir = self::getLogDir();
        $cutoff = time() - (self::RETENTION_DAYS * 86400);
        $files = @glob($dir . '/*.log');
        if (!$files) return;
        foreach ($files as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * Escribe una entrada en el archivo de log
     */
    private static function log(string $level, string $message, array $context = []): void {
        $logDir = self::getLogDir();
        $date = date('Y-m-d');
        $timestamp = date('Y-m-d H:i:s');
        $logFile = "$logDir/$date.log";

        $entry = "[$timestamp] [$level] " . self::sanitizeString($message);
        if (!empty($context)) {
            $entry .= ' ' . json_encode(
                self::sanitizeArray($context),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        $entry .= PHP_EOL;

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        // Rotación perezosa: una de cada N escrituras comprueba
        if (random_int(1, self::ROTATION_PROBABILITY) === 1) {
            self::rotate();
        }
    }

    /**
     * Enmascara PII en strings:
     * - Emails: "foo@bar.com" → "f***@bar.com"
     * - Paths absolutos del servidor: "/home/user/app/..." → "[path]/..."
     * - Tokens/hashes largos (>=32 hex): enmascarados
     */
    private static function sanitizeString(string $s): string {
        // Emails
        $s = preg_replace_callback(
            '/\b([A-Z0-9._%+-])[A-Z0-9._%+-]*(@[A-Z0-9.-]+\.[A-Z]{2,})/i',
            function ($m) { return $m[1] . '***' . $m[2]; },
            $s
        ) ?? $s;

        // Rutas absolutas del servidor (Linux). No tocamos URLs (empiezan con http).
        $s = preg_replace('#(?<!https?:)/(?:home|var|srv|opt|usr)/[^\s"\']*#', '[path]', $s) ?? $s;

        // Tokens/hashes hexadecimales largos
        $s = preg_replace('/\b[a-f0-9]{32,}\b/i', '[redacted]', $s) ?? $s;

        return $s;
    }

    /**
     * Aplica sanitizeString recursivamente a arrays, enmascarando también
     * claves sensibles comunes (password, token, authorization…).
     */
    private static function sanitizeArray(array $data): array {
        $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'authorization', 'api_key', 'apikey'];
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $sensitiveKeys, true)) {
                $out[$k] = '[redacted]';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::sanitizeArray($v);
            } elseif (is_string($v)) {
                $out[$k] = self::sanitizeString($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
