<?php
/**
 * Logger simple a archivo
 * Registra errores y eventos de auditoría
 */

class Logger {
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
     * Registra un evento de auditoría
     */
    public static function audit(string $domain, int $score, float $durationSec): void {
        self::log('AUDIT', "Auditoría completada: $domain (score: $score, duración: {$durationSec}s)");
    }

    /**
     * Escribe una entrada en el archivo de log
     */
    private static function log(string $level, string $message, array $context = []): void {
        $logDir = self::getLogDir();
        $date = date('Y-m-d');
        $timestamp = date('Y-m-d H:i:s');
        $logFile = "$logDir/$date.log";

        $entry = "[$timestamp] [$level] $message";
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $entry .= PHP_EOL;

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
