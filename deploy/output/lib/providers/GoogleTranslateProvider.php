<?php
/**
 * Provider que traduce con Google Cloud Translation API (v2).
 *
 * Ventaja sobre los LLMs: más barato y rápido para strings cortos.
 * Desventaja: no respeta tan bien el tono ni el contexto de marca, y no
 * entiende "este es un mensaje de venta amigable". Para UI literal
 * (botones, labels) es perfecto.
 */
class GoogleTranslateProvider implements TranslationProvider
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        if ($apiKey === '') {
            throw new InvalidArgumentException('Google Translate API key is empty');
        }
        $this->apiKey = $apiKey;
    }

    public function getId(): string { return 'google'; }
    public function getName(): string { return 'Google Translate'; }

    public function translate(string $text, string $sourceLang, string $targetLang, string $context = ''): string
    {
        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($this->apiKey);
        $payload = [
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'format' => 'text',  // 'html' respeta tags, 'text' los escapa — usamos text porque nuestros placeholders son {{x}}
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Google Translate request failed: $err");
        }
        if ($status >= 400) {
            throw new RuntimeException("Google Translate API error ($status): " . substr((string) $response, 0, 300));
        }
        $data = json_decode((string) $response, true);
        $translated = $data['data']['translations'][0]['translatedText'] ?? null;
        if (!is_string($translated) || $translated === '') {
            throw new RuntimeException('Google Translate returned empty translation');
        }
        return trim($translated);
    }
}
