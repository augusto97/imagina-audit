<?php
/**
 * Validación y normalización de URLs + protección anti-SSRF
 */

class UrlValidator {
    /** Rangos de IP privadas que deben bloquearse */
    private const BLOCKED_IP_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '0.0.0.0/8',
    ];

    /** Hosts bloqueados */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        '0.0.0.0',
        '::1',
        '::',
    ];

    /**
     * Valida y normaliza una URL
     * @throws InvalidArgumentException si la URL no es válida
     */
    public static function validate(string $url): string {
        $url = trim($url);

        // Agregar scheme si no tiene
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        // Validar formato de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('La URL proporcionada no tiene un formato válido.');
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            throw new InvalidArgumentException('No se pudo analizar la URL proporcionada.');
        }

        $host = strtolower($parsed['host']);

        // Verificar hosts bloqueados
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('No se permite escanear direcciones locales.');
        }

        // Si el host es una IP directa, verificar que no sea privada
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            self::validateIp($host);
        } else {
            // Resolver DNS y verificar la IP resultante
            $ip = gethostbyname($host);
            if ($ip === $host) {
                throw new InvalidArgumentException('No se pudo resolver el dominio. Verifica que la URL sea correcta.');
            }
            self::validateIp($ip);
        }

        // Normalizar: solo usar scheme + host + path
        $scheme = $parsed['scheme'] ?? 'https';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';

        // Remover trailing slash excepto si es solo /
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return "$scheme://$host$port$path";
    }

    /**
     * Extrae el dominio limpio de una URL
     */
    public static function extractDomain(string $url): string {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;

        // Remover www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return strtolower($host);
    }

    /**
     * Valida que una IP no sea privada ni reservada
     * @throws InvalidArgumentException si la IP es privada
     */
    private static function validateIp(string $ip): void {
        // IPv6 localhost
        if ($ip === '::1' || $ip === '::') {
            throw new InvalidArgumentException('No se permite escanear direcciones locales.');
        }

        // Verificar IP privada/reservada con filtros nativos de PHP
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('No se permite escanear direcciones IP privadas o reservadas.');
        }
    }

    /**
     * Construye una URL relativa a partir de la URL base
     */
    public static function resolveUrl(string $base, string $relative): string {
        // Si ya es absoluta, retornarla
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        $parsed = parse_url($base);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        if (str_starts_with($relative, '//')) {
            return "$scheme:$relative";
        }

        if (str_starts_with($relative, '/')) {
            return "$scheme://$host$port$relative";
        }

        $basePath = $parsed['path'] ?? '/';
        $baseDir = dirname($basePath);
        return "$scheme://$host$port$baseDir/$relative";
    }
}
