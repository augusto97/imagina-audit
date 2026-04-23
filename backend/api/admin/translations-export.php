<?php
/**
 * GET /api/admin/translations-export.php?lang=XX
 *
 * Descarga un pack de traducciones de un idioma en formato JSON. Contiene:
 *   - Metadata (idioma, nombre, fecha de export, versión del formato).
 *   - Por namespace, el mapa de { key → { value, source, reviewed, aiProvider } }.
 *
 * Solo exporta **overrides** (filas de la tabla `translations`). Los valores
 * que coinciden con el bundle base no se incluyen — así el pack se mantiene
 * pequeño y solo viaja lo que el admin realmente tradujo o editó.
 *
 * La respuesta se sirve como `application/json` con Content-Disposition
 * attachment para que el browser dispare el "Save As" al hacer click.
 *
 * Uso típico:
 *   - Admin A traduce pt completo con IA + manual.
 *   - Admin A exporta → obtiene `imagina-audit-lang-pt.json`.
 *   - Admin B (otra install) importa ese archivo para hidratar su DB.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(Translator::t('api.common.method_not_allowed'), 405);
}

$code = strtolower(substr(trim($_GET['lang'] ?? ''), 0, 2));
if ($code === '' || !preg_match('/^[a-z]{2}$/', $code)) {
    Response::error(Translator::t('admin_api.translations.lang_unsupported'), 400);
}

$lang = Languages::find($code);
if (!$lang) {
    Response::error(Translator::t('admin_api.languages.not_found'), 404);
}

$db = Database::getInstance();
$rows = $db->query(
    "SELECT namespace, key, value, source, ai_provider, reviewed, updated_at
     FROM translations WHERE lang = ?
     ORDER BY namespace, key",
    [$code]
);

$namespaces = [];
foreach ($rows as $row) {
    $ns = $row['namespace'];
    if (!isset($namespaces[$ns])) $namespaces[$ns] = [];
    $namespaces[$ns][$row['key']] = [
        'value' => $row['value'],
        'source' => $row['source'],
        'reviewed' => (int) $row['reviewed'] === 1,
        'aiProvider' => $row['ai_provider'],
        'updatedAt' => $row['updated_at'],
    ];
}

$payload = [
    'imaginaAudit' => true,   // marcador de sanidad para rechazar archivos raros
    'formatVersion' => 1,
    'exportedAt' => date('c'),
    'lang' => $code,
    'name' => $lang['name'],
    'nativeName' => $lang['nativeName'],
    'namespaces' => $namespaces,
    'totalOverrides' => count($rows),
];

// Descargar como archivo, no como respuesta API normal.
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="imagina-audit-lang-' . $code . '.json"');
header('Cache-Control: no-store');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
