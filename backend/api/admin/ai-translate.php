<?php
/**
 * POST /api/admin/ai-translate.php
 *
 * Traduce uno o varios strings con un provider de IA (ChatGPT / Claude /
 * Google Translate) y opcionalmente guarda el resultado en la tabla
 * translations con source='ai'.
 *
 * Body:
 *   {
 *     provider?: 'chatgpt' | 'claude' | 'google',  // si se omite, usa el default de settings
 *     sourceLang: 'en',
 *     targetLang: 'fr',
 *     namespace: 'mobile',
 *     items: [
 *       { key: 'viewport.name', text: 'Meta Viewport', context?: '...' },
 *       ...
 *     ],
 *     persist: true   // si true (default), también escribe los overrides en DB
 *   }
 *
 * Response:
 *   { translations: [ { key, text, translated, ok, error? }, ... ] }
 *
 * El endpoint NO detiene el batch si un item falla — devuelve el error
 * inline para que el admin pueda reintentar solo los fallidos. Cada
 * llamada al provider cuesta dinero o cuota así que no hace retry
 * automático — el admin decide.
 */

require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', 405);
}

set_time_limit(120); // traducir 50 items puede tardar 1-2 min

$body = Response::getJsonBody();
$sourceLang = strtolower(substr(trim($body['sourceLang'] ?? ''), 0, 2));
$targetLang = strtolower(substr(trim($body['targetLang'] ?? ''), 0, 2));
$namespace = trim($body['namespace'] ?? '');
$items = $body['items'] ?? [];
$persist = !array_key_exists('persist', $body) || !empty($body['persist']);
$providerRequested = $body['provider'] ?? null;

if (!in_array($targetLang, Translator::SUPPORTED, true)) {
    Response::error('Idioma destino no soportado', 400);
}
if ($sourceLang === $targetLang) {
    Response::error('El idioma origen y destino no pueden ser iguales', 400);
}
if (!is_array($items) || empty($items)) {
    Response::error('items es obligatorio', 400);
}
if (empty($namespace)) {
    Response::error('namespace es obligatorio', 400);
}

// Instanciar provider (viene del body o del setting default_ai_provider)
$db = Database::getInstance();
$settingsRows = $db->query(
    "SELECT key, value FROM settings WHERE key IN ('openai_api_key', 'anthropic_api_key', 'google_translate_api_key', 'default_ai_provider', 'openai_model', 'anthropic_model')"
);
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['key']] = $row['value'];
}

$providerId = $providerRequested ?: ($settings['default_ai_provider'] ?? 'claude');
if (!in_array($providerId, ['chatgpt', 'claude', 'google'], true)) {
    Response::error('Provider inválido', 400);
}

try {
    $provider = null;
    if ($providerId === 'chatgpt') {
        $key = $settings['openai_api_key'] ?? '';
        if ($key === '') throw new RuntimeException('OpenAI API key not configured in settings');
        $provider = new ChatGptProvider($key, $settings['openai_model'] ?? 'gpt-4o-mini');
    } elseif ($providerId === 'claude') {
        $key = $settings['anthropic_api_key'] ?? '';
        if ($key === '') throw new RuntimeException('Anthropic API key not configured in settings');
        $provider = new ClaudeProvider($key, $settings['anthropic_model'] ?? 'claude-sonnet-4-5');
    } else {
        $key = $settings['google_translate_api_key'] ?? '';
        if ($key === '') throw new RuntimeException('Google Translate API key not configured in settings');
        $provider = new GoogleTranslateProvider($key);
    }
} catch (Throwable $e) {
    Response::error($e->getMessage(), 400);
}

$results = [];
foreach ($items as $i => $item) {
    $key = trim($item['key'] ?? '');
    $text = (string) ($item['text'] ?? '');
    $context = (string) ($item['context'] ?? '');

    if ($key === '' || $text === '') {
        $results[] = [
            'key' => $key,
            'text' => $text,
            'translated' => null,
            'ok' => false,
            'error' => 'Empty key or text',
        ];
        continue;
    }

    try {
        $translated = $provider->translate($text, $sourceLang, $targetLang, $context);

        if ($persist) {
            $existing = $db->queryOne(
                "SELECT id FROM translations WHERE lang = ? AND namespace = ? AND key = ?",
                [$targetLang, $namespace, $key]
            );
            if ($existing) {
                $db->execute(
                    "UPDATE translations SET value = ?, source = 'ai', ai_provider = ?, reviewed = 0, updated_at = datetime('now') WHERE id = ?",
                    [$translated, $providerId, $existing['id']]
                );
            } else {
                $db->execute(
                    "INSERT INTO translations (lang, namespace, key, value, source, ai_provider, reviewed) VALUES (?, ?, ?, ?, 'ai', ?, 0)",
                    [$targetLang, $namespace, $key, $translated, $providerId]
                );
            }
        }

        $results[] = [
            'key' => $key,
            'text' => $text,
            'translated' => $translated,
            'ok' => true,
        ];
    } catch (Throwable $e) {
        $results[] = [
            'key' => $key,
            'text' => $text,
            'translated' => null,
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

if ($persist) {
    Translator::reset();
}

$okCount = count(array_filter($results, fn($r) => $r['ok']));
$errorCount = count($results) - $okCount;

Response::success([
    'provider' => $providerId,
    'providerName' => $provider->getName(),
    'translations' => $results,
    'totalCount' => count($results),
    'okCount' => $okCount,
    'errorCount' => $errorCount,
]);
