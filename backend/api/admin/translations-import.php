<?php
/**
 * POST /api/admin/translations-import.php
 *
 * Importa un pack de traducciones previamente exportado. Soporta dos pasos:
 *
 *   1. dryRun=true → NO toca la DB, solo devuelve un preview con contadores
 *      y una lista de cambios. El admin revisa y confirma.
 *   2. dryRun=false → aplica el import siguiendo el `mode` elegido.
 *
 * Modos soportados:
 *
 *   - "fill_missing": solo inserta keys sin override en la DB. Si el admin
 *      ya tocó una key a mano (hay fila en `translations`), se respeta.
 *      → El más seguro. Default.
 *
 *   - "replace_all": machaca TODO. Override existe o no, el valor del pack
 *      gana. Se usa cuando el admin quiere empezar limpio con un pack
 *      completo.
 *
 *   - "smart_merge": respeta solo lo que el admin marcó como `reviewed=1`
 *      (traducciones que él validó manualmente). Las demás — IA no revisada
 *      o filas viejas con `reviewed=0` — se sobreescriben.
 *
 * Body JSON esperado:
 *   {
 *     "payload": { ...archivo exportado... },
 *     "mode": "fill_missing" | "replace_all" | "smart_merge",
 *     "dryRun": true | false
 *   }
 *
 * Respuesta:
 *   - En dryRun: {
 *        mode, lang, totalInPack,
 *        willAdd: N,        // keys nuevas que entran
 *        willChange: N,     // keys existentes que se reemplazan
 *        willSkip: N,       // keys protegidas (reviewed o fill_missing)
 *        changes: [         // muestra hasta 50 (para evitar response gigante)
 *          { namespace, key, currentValue, incomingValue, action: 'add'|'change'|'skip', reason? }
 *        ]
 *     }
 *   - En apply real: { applied: true, added, changed, skipped }
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$body = Response::getJsonBody();
$payload = $body['payload'] ?? null;
$mode = $body['mode'] ?? 'fill_missing';
$dryRun = !empty($body['dryRun']);

if (!is_array($payload) || empty($payload['imaginaAudit']) || !isset($payload['lang']) || !isset($payload['namespaces'])) {
    Response::error(Translator::t('admin_api.translations_import.invalid_file'), 400);
}
if (!in_array($mode, ['fill_missing', 'replace_all', 'smart_merge'], true)) {
    Response::error(Translator::t('admin_api.translations_import.invalid_mode'), 400);
}

$code = strtolower(substr(trim((string) $payload['lang']), 0, 2));
if (!preg_match('/^[a-z]{2}$/', $code)) {
    Response::error(Translator::t('admin_api.translations.lang_unsupported'), 400);
}

// Si el idioma no existe aún en la DB, lo creamos sobre la marcha para que
// el admin pueda importar un pack sin haber creado manualmente el idioma antes.
$existingLang = Languages::find($code);
$createdLanguage = false;
if (!$existingLang) {
    try {
        Languages::upsert([
            'code' => $code,
            'name' => $payload['name'] ?? null,
            'nativeName' => $payload['nativeName'] ?? null,
            'isActive' => true,
            'isPublic' => true,
            'sortOrder' => 100,
        ]);
        $createdLanguage = true;
    } catch (Throwable $e) {
        Response::error(Translator::t('admin_api.languages.save_error') . ': ' . $e->getMessage(), 500);
    }
}

$db = Database::getInstance();

// Snapshot de lo que ya hay en la DB, para decidir qué es "nuevo" vs "reemplazo"
$existingRows = $db->query(
    "SELECT namespace, key, value, source, reviewed FROM translations WHERE lang = ?",
    [$code]
);
$existingMap = []; // "namespace|key" → fila
foreach ($existingRows as $row) {
    $existingMap[$row['namespace'] . '|' . $row['key']] = $row;
}

$willAdd = 0;
$willChange = 0;
$willSkip = 0;
$changes = []; // Acumulador para el preview
$maxDetailedChanges = 100;

$toApply = []; // [{namespace, key, value}]

foreach ($payload['namespaces'] as $namespace => $keys) {
    if (!is_string($namespace) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $namespace)) continue;
    if (!is_array($keys)) continue;

    foreach ($keys as $key => $entry) {
        if (!is_string($key) || $key === '') continue;
        $incoming = is_array($entry) ? ($entry['value'] ?? null) : $entry;
        if (!is_string($incoming)) continue;

        $mapKey = "$namespace|$key";
        $existing = $existingMap[$mapKey] ?? null;

        if ($existing === null) {
            // Key nueva — todos los modos la aceptan.
            $willAdd++;
            if (count($changes) < $maxDetailedChanges) {
                $changes[] = [
                    'namespace' => $namespace,
                    'key' => $key,
                    'currentValue' => null,
                    'incomingValue' => $incoming,
                    'action' => 'add',
                ];
            }
            $toApply[] = ['namespace' => $namespace, 'key' => $key, 'value' => $incoming];
            continue;
        }

        // Ya hay override local — decidir según el modo.
        $sameValue = $existing['value'] === $incoming;
        if ($sameValue) {
            // Sin cambio real, ni contar.
            continue;
        }

        $protect = false;
        $reason = null;
        if ($mode === 'fill_missing') {
            // Cualquier override existente es intocable.
            $protect = true;
            $reason = 'override_exists';
        } elseif ($mode === 'smart_merge') {
            // Solo protegemos si el admin marcó la key como revisada.
            if ((int) $existing['reviewed'] === 1) {
                $protect = true;
                $reason = 'reviewed';
            }
        } // replace_all: nunca protege

        if ($protect) {
            $willSkip++;
            if (count($changes) < $maxDetailedChanges) {
                $changes[] = [
                    'namespace' => $namespace,
                    'key' => $key,
                    'currentValue' => $existing['value'],
                    'incomingValue' => $incoming,
                    'action' => 'skip',
                    'reason' => $reason,
                ];
            }
        } else {
            $willChange++;
            if (count($changes) < $maxDetailedChanges) {
                $changes[] = [
                    'namespace' => $namespace,
                    'key' => $key,
                    'currentValue' => $existing['value'],
                    'incomingValue' => $incoming,
                    'action' => 'change',
                ];
            }
            $toApply[] = ['namespace' => $namespace, 'key' => $key, 'value' => $incoming];
        }
    }
}

if ($dryRun) {
    Response::success([
        'dryRun' => true,
        'mode' => $mode,
        'lang' => $code,
        'languageCreated' => $createdLanguage,
        'totalInPack' => array_sum(array_map('count', $payload['namespaces'])),
        'willAdd' => $willAdd,
        'willChange' => $willChange,
        'willSkip' => $willSkip,
        'changes' => $changes,  // hasta 100 items
        'truncated' => count($changes) >= $maxDetailedChanges,
    ]);
}

// Aplicación real — insertamos/actualizamos vía upsert.
// Envuelto en transacción para que un error a mitad no deje la DB mediopatida.
$pdo = $db->getPdo();
$pdo->beginTransaction();
try {
    $upsert = $pdo->prepare(
        "INSERT INTO translations (lang, namespace, key, value, source, ai_provider, reviewed)
         VALUES (?, ?, ?, ?, 'import', NULL, 1)
         ON CONFLICT(lang, namespace, key) DO UPDATE SET
            value = excluded.value,
            source = 'import',
            reviewed = 1,
            updated_at = datetime('now')"
    );
    foreach ($toApply as $row) {
        $upsert->execute([$code, $row['namespace'], $row['key'], $row['value']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Logger::error('translations-import falló: ' . $e->getMessage());
    Response::error(Translator::t('admin_api.translations_import.apply_error'), 500);
}

Translator::reset();

Response::success([
    'applied' => true,
    'mode' => $mode,
    'lang' => $code,
    'languageCreated' => $createdLanguage,
    'added' => $willAdd,
    'changed' => $willChange,
    'skipped' => $willSkip,
]);
