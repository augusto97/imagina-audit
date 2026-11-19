<?php
/**
 * Versión de la app. Fuente única: el archivo `VERSION` de la raíz del repo.
 *
 * En el paquete desplegado, deploy/build.sh genera `lib/BuildInfo.php` con
 * la versión compilada dentro del código PHP. Se prefiere esa constante
 * porque viaja con el bytecode: si tras una actualización OPcache siguiera
 * sirviendo código viejo, el health-check lo detecta (versión distinta) en
 * vez de dar un falso OK leyendo el archivo VERSION nuevo.
 */
final class AppVersion {
    public static function current(): string {
        if (class_exists('BuildInfo')) {
            return BuildInfo::VERSION;
        }
        foreach ([dirname(__DIR__) . '/VERSION', dirname(__DIR__, 2) . '/VERSION'] as $file) {
            if (is_file($file)) {
                $v = trim((string) @file_get_contents($file));
                if (self::isValid($v)) return $v;
            }
        }
        return '0.0.0';
    }

    public static function isValid(string $v): bool {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $v);
    }

    /** true si $candidate es estrictamente más nueva que $current. */
    public static function isNewer(string $candidate, string $current): bool {
        return self::isValid($candidate) && version_compare($candidate, $current, '>');
    }
}
