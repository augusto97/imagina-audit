<?php
/**
 * Información del sistema host para calibrar la concurrencia de audits.
 *
 * El admin ve la RAM total detectada y una recomendación de
 * audit_max_concurrent. Si la detección falla (hosting restrictivo),
 * devolvemos null y el admin lo configura manualmente.
 */

class SystemInfo {
    /**
     * RAM total del servidor en MB. null si no se puede detectar.
     * Lee /proc/meminfo (disponible en la mayoría de hostings Linux).
     */
    public static function totalRamMb(): ?int {
        $path = '/proc/meminfo';
        if (!@is_readable($path)) return null;

        $content = @file_get_contents($path);
        if (empty($content)) return null;

        if (preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $content, $m)) {
            return (int) round((int) $m[1] / 1024);
        }
        return null;
    }

    /**
     * RAM disponible (después de descontar lo que usa el SO + otros procesos).
     * Útil para un snapshot en vivo del hosting. null si no se puede.
     */
    public static function availableRamMb(): ?int {
        $path = '/proc/meminfo';
        if (!@is_readable($path)) return null;

        $content = @file_get_contents($path);
        if (empty($content)) return null;

        // MemAvailable está presente en kernels modernos (>= 3.14). Fallback a MemFree.
        if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/m', $content, $m)) {
            return (int) round((int) $m[1] / 1024);
        }
        if (preg_match('/^MemFree:\s+(\d+)\s+kB/m', $content, $m)) {
            return (int) round((int) $m[1] / 1024);
        }
        return null;
    }

    /**
     * Recomendación de audit_max_concurrent según RAM total.
     *
     * Tabla calibrada para dejar ~700-800 MB de overhead (SO, PHP-FPM
     * master, servicios del hosting, buffers del kernel, tráfico normal
     * del sitio público/admin) y ~200-250 MB por audit concurrente.
     */
    public static function recommendedConcurrency(int $totalRamMb): int {
        if ($totalRamMb <  768) return 1;
        if ($totalRamMb < 1280) return 2;
        if ($totalRamMb < 1792) return 3;   // 1.5 GB → 3
        if ($totalRamMb < 2560) return 4;
        if ($totalRamMb < 3584) return 6;
        if ($totalRamMb < 5120) return 8;
        if ($totalRamMb < 8192) return 12;
        return 15;
    }

    /**
     * Tabla de recomendaciones para mostrar en la UI del admin.
     * Se consume con JSON directamente.
     */
    public static function recommendationTable(): array {
        return [
            ['minMb' => 0,    'maxMb' => 767,   'concurrency' => 1,  'label' => 'Menos de 768 MB'],
            ['minMb' => 768,  'maxMb' => 1279,  'concurrency' => 2,  'label' => '768 MB – 1.25 GB'],
            ['minMb' => 1280, 'maxMb' => 1791,  'concurrency' => 3,  'label' => '1.25 GB – 1.75 GB'],
            ['minMb' => 1792, 'maxMb' => 2559,  'concurrency' => 4,  'label' => '1.75 GB – 2.5 GB'],
            ['minMb' => 2560, 'maxMb' => 3583,  'concurrency' => 6,  'label' => '2.5 GB – 3.5 GB'],
            ['minMb' => 3584, 'maxMb' => 5119,  'concurrency' => 8,  'label' => '3.5 GB – 5 GB'],
            ['minMb' => 5120, 'maxMb' => 8191,  'concurrency' => 12, 'label' => '5 GB – 8 GB'],
            ['minMb' => 8192, 'maxMb' => 999999,'concurrency' => 15, 'label' => '8 GB o más'],
        ];
    }

    /**
     * Snapshot compacto para el endpoint admin.
     */
    public static function snapshot(): array {
        $total = self::totalRamMb();
        $available = self::availableRamMb();
        return [
            'totalRamMb' => $total,
            'availableRamMb' => $available,
            'phpMemoryLimitMb' => (int) round(self::parseIniBytes(ini_get('memory_limit') ?: '128M') / 1048576),
            'phpVersion' => PHP_VERSION,
            'recommendedConcurrency' => $total !== null ? self::recommendedConcurrency($total) : null,
        ];
    }

    /**
     * Convierte valores de memory_limit (e.g. "256M", "1G") a bytes.
     */
    private static function parseIniBytes(string $value): int {
        $value = trim($value);
        if ($value === '-1' || $value === '') return 0;
        $last = strtolower(substr($value, -1));
        $num = (int) $value;
        switch ($last) {
            case 'g': return $num * 1073741824;
            case 'm': return $num * 1048576;
            case 'k': return $num * 1024;
            default:  return $num;
        }
    }
}
