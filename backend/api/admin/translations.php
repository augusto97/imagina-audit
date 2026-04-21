<?php
/**
 * CRUD de traducciones editables desde el admin.
 *
 *   GET    /api/admin/translations.php?lang=es&namespace=mobile
 *     Devuelve la unión del bundle base + overrides de la DB para ese
 *     (lang, namespace). Cada key incluye:
 *       - value: el texto actual (override si existe, si no el del bundle)
 *       - defaultValue: el texto del bundle base (para comparar/restaurar)
 *       - overridden: true si hay un registro en translations
 *       - source: 'manual' | 'ai' | 'import' | null
 *       - aiProvider: 'chatgpt' | 'claude' | 'google' | null
 *       - reviewed: 0 | 1
 *
 *   PUT    /api/admin/translations.php
 *     Body: { lang, namespace, key, value, source?, aiProvider?, reviewed? }
 *     Upsert del override. Por default source='manual' reviewed=1.
 *
 *   DELETE /api/admin/translations.php?lang=es&namespace=mobile&key=viewport.name
 *     Borra el override → la key vuelve al valor del bundle base.
 *
 *   GET    /api/admin/translations.php?meta=namespaces
 *     Devuelve la lista de namespaces disponibles (escaneando locales/en/*.php)
 *     + SUPPORTED languages.
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET meta=namespaces ────────────────────────────────────────────
if ($method === 'GET' && ($_GET['meta'] ?? '') === 'namespaces') {
    $localesDir = dirname(__DIR__, 2) . '/locales';
    $namespaces = [];
    // Usamos 'en' como fuente de verdad — todos los ns que existen en
    // inglés son los editables (los otros idiomas hacen fallback ahí).
    $enDir = "$localesDir/en";
    if (is_dir($enDir)) {
        foreach (glob("$enDir/*.php") as $file) {
            $namespaces[] = basename($file, '.php');
        }
        sort($namespaces);
    }
    Response::success([
        'namespaces' => $namespaces,
        'languages' => Translator::SUPPORTED,
        'defaultLang' => Translator::DEFAULT_LANG,
    ]);
}

if ($method === 'GET') {
    $lang = strtolower(substr(trim($_GET['lang'] ?? ''), 0, 2));
    $namespace = trim($_GET['namespace'] ?? '');
    if (!in_array($lang, Translator::SUPPORTED, true)) {
        Response::error('Idioma no soportado', 400);
    }
    if (empty($namespace) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $namespace)) {
        Response::error('Namespace inválido', 400);
    }

    // Base bundle (archivo PHP)
    $baseFile = dirname(__DIR__, 2) . "/locales/$lang/$namespace.php";
    $base = file_exists($baseFile) ? (require $baseFile) : [];
    if (!is_array($base)) $base = [];

    // Bundle en el idioma fuente (en) para mostrar siempre el string original
    // como referencia — útil cuando el admin traduce a un idioma que aún no
    // existe como archivo y el bundle base es []
    $sourceFile = dirname(__DIR__, 2) . "/locales/" . Translator::DEFAULT_LANG . "/$namespace.php";
    $source = file_exists($sourceFile) ? (require $sourceFile) : [];
    if (!is_array($source)) $source = [];

    // Overrides de la DB
    $rows = $db->query(
        "SELECT key, value, source, ai_provider, reviewed, updated_at FROM translations WHERE lang = ? AND namespace = ?",
        [$lang, $namespace]
    );
    $overrides = [];
    foreach ($rows as $row) {
        $overrides[$row['key']] = $row;
    }

    // Unión de keys (source + base + overrides) — el source garantiza que
    // todas las keys del idioma de referencia aparezcan aunque el idioma
    // target aún no tenga bundle.
    $allKeys = array_unique(array_merge(array_keys($source), array_keys($base), array_keys($overrides)));
    sort($allKeys);

    $items = [];
    foreach ($allKeys as $key) {
        $override = $overrides[$key] ?? null;
        $bundleValue = $base[$key] ?? null;
        $currentValue = $override['value'] ?? $bundleValue ?? '';
        $items[] = [
            'key' => $key,
            'value' => $currentValue,
            'defaultValue' => $bundleValue,
            'sourceValue' => $source[$key] ?? null,
            'overridden' => $override !== null,
            'source' => $override['source'] ?? null,
            'aiProvider' => $override['ai_provider'] ?? null,
            'reviewed' => (int) ($override['reviewed'] ?? 0) === 1,
            'updatedAt' => $override['updated_at'] ?? null,
        ];
    }

    Response::success([
        'lang' => $lang,
        'namespace' => $namespace,
        'items' => $items,
        'totalKeys' => count($items),
        'overriddenCount' => count($overrides),
    ]);
}

if ($method === 'PUT') {
    $body = Response::getJsonBody();
    $lang = strtolower(substr(trim($body['lang'] ?? ''), 0, 2));
    $namespace = trim($body['namespace'] ?? '');
    $key = trim($body['key'] ?? '');
    $value = (string) ($body['value'] ?? '');
    $source = $body['source'] ?? 'manual';
    $aiProvider = $body['aiProvider'] ?? null;
    $reviewed = !empty($body['reviewed']) ? 1 : 0;

    if (!in_array($lang, Translator::SUPPORTED, true)) {
        Response::error('Idioma no soportado', 400);
    }
    if (empty($namespace) || empty($key)) {
        Response::error('namespace y key son obligatorios', 400);
    }
    if (!in_array($source, ['manual', 'ai', 'import'], true)) {
        Response::error('source inválido', 400);
    }

    $existing = $db->queryOne(
        "SELECT id FROM translations WHERE lang = ? AND namespace = ? AND key = ?",
        [$lang, $namespace, $key]
    );
    if ($existing) {
        $db->execute(
            "UPDATE translations SET value = ?, source = ?, ai_provider = ?, reviewed = ?, updated_at = datetime('now') WHERE id = ?",
            [$value, $source, $aiProvider, $reviewed, $existing['id']]
        );
    } else {
        $db->execute(
            "INSERT INTO translations (lang, namespace, key, value, source, ai_provider, reviewed) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$lang, $namespace, $key, $value, $source, $aiProvider, $reviewed]
        );
    }

    Translator::reset();
    Response::success(['success' => true]);
}

if ($method === 'DELETE') {
    $lang = strtolower(substr(trim($_GET['lang'] ?? ''), 0, 2));
    $namespace = trim($_GET['namespace'] ?? '');
    $key = trim($_GET['key'] ?? '');

    if (!in_array($lang, Translator::SUPPORTED, true)) {
        Response::error('Idioma no soportado', 400);
    }

    if (!empty($key)) {
        $db->execute(
            "DELETE FROM translations WHERE lang = ? AND namespace = ? AND key = ?",
            [$lang, $namespace, $key]
        );
    } elseif (!empty($namespace)) {
        $db->execute(
            "DELETE FROM translations WHERE lang = ? AND namespace = ?",
            [$lang, $namespace]
        );
    } else {
        Response::error('namespace (y opcionalmente key) son obligatorios', 400);
    }

    Translator::reset();
    Response::success(['success' => true]);
}

Response::error('Método no permitido', 405);
