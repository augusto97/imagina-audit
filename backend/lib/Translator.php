<?php
/**
 * Translator — lookup de cadenas localizadas para el backend.
 *
 * Uso:
 *   Translator::setLang('es');                // setear idioma activo
 *   Translator::t('security.ssl.name');       // 'Certificado SSL'
 *   Translator::t('security.ssl.name', ['domain' => 'x.com']);  // interpolación
 *
 * Los archivos de locale viven en backend/locales/{lang}/ como arrays PHP
 * flat con keys dotted:
 *   backend/locales/es/security.php → ['ssl.name' => 'Certificado SSL', ...]
 *   backend/locales/en/security.php → ['ssl.name' => 'SSL certificate', ...]
 *
 * Si la key no existe en el lang activo, fallback a 'en'. Si tampoco está
 * en 'en', retorna la key (debugging-friendly).
 *
 * El idioma se determina en este orden de prioridad:
 *   1. Llamada explícita a setLang() (desde audit.php con el body.lang).
 *   2. Query param ?lang=xx.
 *   3. Header Accept-Language.
 *   4. Default: 'en' (audiencia principal de CodeCanyon).
 */
class Translator
{
    public const DEFAULT_LANG = 'en';
    public const SUPPORTED = ['en', 'es', 'pt', 'fr', 'de', 'it'];

    private static ?string $currentLang = null;
    private static array $cache = [];  // lang → namespace → flat array
    private static ?string $baseDir = null;

    /**
     * Setea el idioma activo. Se normaliza y valida contra SUPPORTED.
     * Si el idioma no está soportado, se queda en DEFAULT_LANG.
     */
    public static function setLang(?string $lang): void
    {
        $lang = self::normalize($lang);
        self::$currentLang = in_array($lang, self::SUPPORTED, true) ? $lang : self::DEFAULT_LANG;
    }

    /** Idioma activo (auto-detecta si no fue seteado). */
    public static function getLang(): string
    {
        if (self::$currentLang === null) {
            self::$currentLang = self::detect();
        }
        return self::$currentLang;
    }

    /**
     * Busca una traducción. La key tiene formato "namespace.dotted.key" — el
     * primer segmento es el archivo de locale (ej. security.ssl.name busca
     * en locales/{lang}/security.php la key 'ssl.name').
     *
     * Si la key no existe, fallback a 'en'. Si tampoco, retorna la key cruda.
     */
    public static function t(string $key, array $params = []): string
    {
        $lang = self::getLang();
        $value = self::lookup($lang, $key);
        if ($value === null && $lang !== self::DEFAULT_LANG) {
            $value = self::lookup(self::DEFAULT_LANG, $key);
        }
        if ($value === null) {
            return $key;
        }
        return self::interpolate($value, $params);
    }

    /**
     * Variante que retorna null si la key no existe (sin fallback a la key
     * cruda). Útil para checks opcionales.
     */
    public static function has(string $key): bool
    {
        $lang = self::getLang();
        if (self::lookup($lang, $key) !== null) return true;
        if ($lang !== self::DEFAULT_LANG && self::lookup(self::DEFAULT_LANG, $key) !== null) return true;
        return false;
    }

    /** Para testing / reset en tests unitarios. */
    public static function reset(): void
    {
        self::$currentLang = null;
        self::$cache = [];
    }

    // ─── Private helpers ────────────────────────────────────────────────

    private static function lookup(string $lang, string $key): ?string
    {
        [$namespace, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if ($namespace === null || $rest === null) return null;
        $bundle = self::loadBundle($lang, $namespace);
        if ($bundle === null) return null;

        // Primero intentar como key dotted literal (ej. "ssl.name")
        if (isset($bundle[$rest]) && is_string($bundle[$rest])) {
            return $bundle[$rest];
        }
        return null;
    }

    /** Lazy-load de un bundle de locales. */
    private static function loadBundle(string $lang, string $namespace): ?array
    {
        $cacheKey = "$lang:$namespace";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        $base = self::$baseDir ??= dirname(__DIR__) . '/locales';
        $file = "$base/$lang/$namespace.php";
        if (!file_exists($file)) {
            self::$cache[$cacheKey] = [];
            return null;
        }
        $data = require $file;
        if (!is_array($data)) {
            self::$cache[$cacheKey] = [];
            return null;
        }
        self::$cache[$cacheKey] = $data;
        return $data;
    }

    /** Reemplaza {{name}} con $params['name']. */
    private static function interpolate(string $str, array $params): string
    {
        if (empty($params)) return $str;
        foreach ($params as $k => $v) {
            $str = str_replace('{{' . $k . '}}', (string) $v, $str);
        }
        return $str;
    }

    private static function normalize(?string $lang): string
    {
        if (!$lang) return self::DEFAULT_LANG;
        return strtolower(substr(trim($lang), 0, 2));
    }

    /** Auto-detección desde query param → Accept-Language → default. */
    private static function detect(): string
    {
        // 1. Query param
        $fromQuery = $_GET['lang'] ?? null;
        if ($fromQuery) {
            $lang = self::normalize($fromQuery);
            if (in_array($lang, self::SUPPORTED, true)) return $lang;
        }
        // 2. Accept-Language header (primer valor)
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header) {
            $first = explode(',', $header)[0];
            $lang = self::normalize($first);
            if (in_array($lang, self::SUPPORTED, true)) return $lang;
        }
        return self::DEFAULT_LANG;
    }
}
