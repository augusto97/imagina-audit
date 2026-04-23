<?php
/**
 * Helper centralizado para la tabla `languages` + los bundles JSON del
 * frontend (backend/locales/{code}/frontend.json). Unifica el flujo de:
 *
 *   - Listar idiomas (públicos o todos) con nombre y flags.
 *   - Resolver un bundle frontend merged (base JSON + DB overrides).
 *   - Crear/actualizar/eliminar idiomas desde el admin.
 *
 * La fuente de verdad del bundle base frontend es el JSON que el build copia
 * en backend/locales/{code}/frontend.json desde frontend/src/i18n/locales/.
 * Si un idioma no tiene ese JSON (porque el admin lo creó después del build),
 * se usa el bundle del idioma default (en) como fallback y las overrides de
 * la DB.
 *
 * Para que el editor de traducciones del panel pueda listar las keys del
 * namespace 'frontend', scaneamos el JSON base y aplanamos las keys con
 * notación dotted (ej. "account.history_view" → "account.history_view").
 */
class Languages
{
    /** Lista todos los idiomas de la tabla. $onlyPublic filtra al subset que se expone en el switcher. */
    public static function all(bool $onlyActive = true, bool $onlyPublic = false): array
    {
        $db = Database::getInstance();
        $where = [];
        if ($onlyActive) $where[] = 'is_active = 1';
        if ($onlyPublic) $where[] = 'is_public = 1';
        $sql = "SELECT code, name, native_name, is_active, is_public, sort_order, created_at FROM languages";
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY sort_order, code';

        $rows = [];
        try {
            $rows = $db->query($sql);
        } catch (Throwable $e) { /* tabla no existe; devolver vacío */ }

        $base = self::baseDir();
        return array_map(function ($row) use ($base) {
            $hasBundle = is_file("$base/{$row['code']}/frontend.json");
            return [
                'code' => $row['code'],
                'name' => $row['name'],
                'nativeName' => $row['native_name'] ?: $row['name'],
                'isActive' => (int) $row['is_active'] === 1,
                'isPublic' => (int) $row['is_public'] === 1,
                'sortOrder' => (int) $row['sort_order'],
                'createdAt' => $row['created_at'],
                'hasFrontendBundle' => $hasBundle,
            ];
        }, $rows);
    }

    /** Busca un idioma por código. Retorna null si no existe. */
    public static function find(string $code): ?array
    {
        $code = self::normalize($code);
        $db = Database::getInstance();
        $row = $db->queryOne("SELECT code, name, native_name, is_active, is_public, sort_order, created_at FROM languages WHERE code = ?", [$code]);
        if (!$row) return null;
        $base = self::baseDir();
        return [
            'code' => $row['code'],
            'name' => $row['name'],
            'nativeName' => $row['native_name'] ?: $row['name'],
            'isActive' => (int) $row['is_active'] === 1,
            'isPublic' => (int) $row['is_public'] === 1,
            'sortOrder' => (int) $row['sort_order'],
            'createdAt' => $row['created_at'],
            'hasFrontendBundle' => is_file("$base/$code/frontend.json"),
        ];
    }

    /**
     * Crea o actualiza un idioma. Si existe, actualiza metadata; si no, inserta.
     * Retorna la fila post-guardado.
     */
    public static function upsert(array $data): array
    {
        $code = self::normalize($data['code'] ?? '');
        if ($code === '') throw new InvalidArgumentException('code_required');
        if (!preg_match('/^[a-z]{2}$/', $code)) throw new InvalidArgumentException('code_invalid');

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') $name = strtoupper($code);
        $native = trim((string) ($data['nativeName'] ?? $name));
        $isActive = !empty($data['isActive']) ? 1 : 0;
        $isPublic = !empty($data['isPublic']) ? 1 : 0;
        $sortOrder = (int) ($data['sortOrder'] ?? 100);

        $db = Database::getInstance();
        $existing = $db->queryOne("SELECT code FROM languages WHERE code = ?", [$code]);
        if ($existing) {
            $db->execute(
                "UPDATE languages SET name = ?, native_name = ?, is_active = ?, is_public = ?, sort_order = ? WHERE code = ?",
                [$name, $native, $isActive, $isPublic, $sortOrder, $code]
            );
        } else {
            $db->execute(
                "INSERT INTO languages (code, name, native_name, is_active, is_public, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [$code, $name, $native, $isActive, $isPublic, $sortOrder]
            );
        }
        Translator::reset();
        return self::find($code) ?? [];
    }

    /**
     * Elimina un idioma + todos sus overrides de traducciones. El default
     * ('en') nunca se puede eliminar (es la fuente de verdad).
     */
    public static function delete(string $code): void
    {
        $code = self::normalize($code);
        if ($code === Translator::DEFAULT_LANG) {
            throw new RuntimeException('cannot_delete_default');
        }
        $db = Database::getInstance();
        $db->execute("DELETE FROM translations WHERE lang = ?", [$code]);
        $db->execute("DELETE FROM languages WHERE code = ?", [$code]);
        Translator::reset();
    }

    /**
     * Devuelve el bundle frontend merged para un idioma. El cliente lo
     * consume vía /api/frontend-locales.php y lo inyecta en i18next con
     * addResourceBundle(). El merge sigue esta cascada:
     *
     *   1. Bundle base del idioma default (en/frontend.json) — garantiza
     *      que todas las keys existan aunque el idioma target sea nuevo.
     *   2. Bundle base del idioma target (si existe) — sobreescribe el default.
     *   3. Overrides de DB (tabla translations, namespace='frontend') —
     *      ganan por encima de todo.
     *
     * Las keys se almacenan con notación dotted ("account.history_view")
     * pero se reconstruye la estructura anidada al devolver (i18next
     * funciona mejor con objetos anidados).
     */
    public static function frontendBundle(string $code): array
    {
        $code = self::normalize($code);
        $base = self::baseDir();

        $defaultFile = "$base/" . Translator::DEFAULT_LANG . "/frontend.json";
        $langFile = "$base/$code/frontend.json";

        $merged = [];
        if (is_file($defaultFile)) {
            $defaultJson = json_decode(file_get_contents($defaultFile), true);
            if (is_array($defaultJson)) $merged = $defaultJson;
        }
        if ($code !== Translator::DEFAULT_LANG && is_file($langFile)) {
            $langJson = json_decode(file_get_contents($langFile), true);
            if (is_array($langJson)) $merged = self::deepMerge($merged, $langJson);
        }

        // Overrides de la DB (dotted keys)
        try {
            $db = Database::getInstance();
            $rows = $db->query("SELECT key, value FROM translations WHERE lang = ? AND namespace = 'frontend'", [$code]);
            foreach ($rows as $row) {
                self::setDotted($merged, $row['key'], $row['value']);
            }
        } catch (Throwable $e) { /* ignorar */ }

        return $merged;
    }

    /**
     * Aplanar un bundle anidado a dotted-keys. Útil para el editor del admin
     * — muestra "account.history_view" como una key editable en vez de un árbol.
     */
    public static function flattenBundle(array $bundle, string $prefix = ''): array
    {
        $out = [];
        foreach ($bundle as $key => $value) {
            $full = $prefix === '' ? $key : "$prefix.$key";
            if (is_array($value)) {
                $out = array_merge($out, self::flattenBundle($value, $full));
            } else {
                $out[$full] = (string) $value;
            }
        }
        return $out;
    }

    /** Ruta absoluta al directorio backend/locales/. */
    private static function baseDir(): string
    {
        return dirname(__DIR__) . '/locales';
    }

    private static function normalize(string $code): string
    {
        return strtolower(substr(trim($code), 0, 2));
    }

    /** Merge recursivo donde el segundo arg gana. */
    private static function deepMerge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (isset($a[$k]) && is_array($a[$k]) && is_array($v)) {
                $a[$k] = self::deepMerge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    /** Setea una dotted-key en un array anidado, creando niveles al vuelo. */
    private static function setDotted(array &$target, string $key, string $value): void
    {
        $parts = explode('.', $key);
        $cursor = &$target;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $cursor[$part] = $value;
            } else {
                if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                    $cursor[$part] = [];
                }
                $cursor = &$cursor[$part];
            }
        }
    }
}
