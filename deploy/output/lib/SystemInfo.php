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
     * RAM total del servidor en MB.
     *
     * Orden de detección (muchos hostings compartidos restringen unos u otros):
     *   1. Override manual en settings (`system_total_ram_mb`)
     *   2. /proc/meminfo
     *   3. /sys/fs/cgroup/memory.max (cgroup v2) o memory.limit_in_bytes (v1)
     *   4. shell_exec('free -m')
     *   5. null → el admin lo configura manualmente
     */
    public static function totalRamMb(): ?int {
        // 1. Override manual desde admin
        $override = self::manualOverride('system_total_ram_mb');
        if ($override !== null) return $override;

        // 2. /proc/meminfo
        $mem = self::readProcMeminfo();
        if ($mem !== null && isset($mem['MemTotal'])) {
            return (int) round($mem['MemTotal'] / 1024);
        }

        // 3. cgroup (algunos VPS / containers)
        $cgroup = self::readCgroupMemory();
        if ($cgroup !== null) {
            return (int) round($cgroup / 1048576);
        }

        // 4. free -m (si shell_exec está habilitado)
        $free = self::readFreeMb();
        if ($free !== null && isset($free['total'])) {
            return $free['total'];
        }

        return null;
    }

    /**
     * RAM disponible (después de descontar lo que usa el SO + otros procesos).
     * Mismo flujo de fallbacks que totalRamMb().
     */
    public static function availableRamMb(): ?int {
        $mem = self::readProcMeminfo();
        if ($mem !== null) {
            if (isset($mem['MemAvailable'])) return (int) round($mem['MemAvailable'] / 1024);
            if (isset($mem['MemFree']))       return (int) round($mem['MemFree'] / 1024);
        }

        $free = self::readFreeMb();
        if ($free !== null) {
            if (isset($free['available'])) return $free['available'];
            if (isset($free['free']))      return $free['free'];
        }

        return null;
    }

    /**
     * Lee /proc/meminfo y retorna todos los campos numéricos (en KB).
     * null si no es legible (hosting restrictivo).
     */
    private static function readProcMeminfo(): ?array {
        $path = '/proc/meminfo';
        if (!@is_readable($path)) return null;
        $content = @file_get_contents($path);
        if (empty($content)) return null;

        $data = [];
        foreach (preg_split('/\r\n|\n/', $content) as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+(\d+)\s+kB/', $line, $m)) {
                $data[$m[1]] = (int) $m[2];
            }
        }
        return !empty($data) ? $data : null;
    }

    /**
     * Lee límite de memoria del cgroup (útil en hostings containerizados).
     * Retorna bytes o null.
     */
    private static function readCgroupMemory(): ?int {
        $candidates = [
            '/sys/fs/cgroup/memory.max',           // cgroup v2
            '/sys/fs/cgroup/memory/memory.limit_in_bytes', // cgroup v1
        ];
        foreach ($candidates as $path) {
            if (!@is_readable($path)) continue;
            $raw = trim((string) @file_get_contents($path));
            if ($raw === '' || $raw === 'max') continue;
            if (!ctype_digit($raw)) continue;
            $bytes = (int) $raw;
            // cgroup v1 usa un valor absurdamente grande cuando no hay límite
            if ($bytes > 0 && $bytes < PHP_INT_MAX / 2) {
                return $bytes;
            }
        }
        return null;
    }

    /**
     * Parsea `free -m` si shell_exec está disponible.
     * Retorna array con total/used/free/available o null.
     */
    private static function readFreeMb(): ?array {
        if (!function_exists('shell_exec')) return null;
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) return null;

        $output = @shell_exec('free -m 2>/dev/null');
        if (!$output) return null;

        foreach (preg_split('/\r\n|\n/', $output) as $line) {
            if (!str_starts_with(trim($line), 'Mem:')) continue;
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) < 4) return null;
            return [
                'total'     => (int) ($cols[1] ?? 0),
                'used'      => (int) ($cols[2] ?? 0),
                'free'      => (int) ($cols[3] ?? 0),
                'available' => isset($cols[6]) ? (int) $cols[6] : null,
            ];
        }
        return null;
    }

    /**
     * Override manual desde la tabla settings.
     * El admin puede configurar la RAM total cuando la auto-detección falla.
     */
    private static function manualOverride(string $key): ?int {
        try {
            if (!class_exists('Database')) return null;
            $db = Database::getInstance();
            $val = $db->scalar('SELECT value FROM settings WHERE key = ?', [$key]);
            if ($val === null || $val === '' || $val === false) return null;
            $num = (int) $val;
            return $num > 0 ? $num : null;
        } catch (Throwable $e) {
            return null;
        }
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
     *
     * `detectionSource` indica de dónde salió el valor (útil para debuggear
     * cuando un hosting no expone /proc/meminfo).
     */
    public static function snapshot(): array {
        $override = self::manualOverride('system_total_ram_mb');
        $proc = self::readProcMeminfo();
        $cgroup = self::readCgroupMemory();
        $free = self::readFreeMb();

        $source = 'none';
        if ($override !== null) $source = 'manual';
        elseif ($proc !== null && isset($proc['MemTotal'])) $source = 'proc';
        elseif ($cgroup !== null) $source = 'cgroup';
        elseif ($free !== null) $source = 'free';

        $total = self::totalRamMb();
        $available = self::availableRamMb();

        return [
            'totalRamMb' => $total,
            'availableRamMb' => $available,
            'detectionSource' => $source,
            'manualOverrideMb' => $override,
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
