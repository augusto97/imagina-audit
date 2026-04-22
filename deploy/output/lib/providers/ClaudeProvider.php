<?php
/**
 * Provider que traduce strings usando Anthropic Claude (API Messages).
 *
 * Modelo por defecto: claude-sonnet-4-5 (última Sonnet estable; se puede
 * override con el setting anthropic_model).
 */
class ClaudeProvider implements TranslationProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'claude-sonnet-4-5')
    {
        if ($apiKey === '') {
            throw new InvalidArgumentException('Anthropic API key is empty');
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getId(): string { return 'claude'; }
    public function getName(): string { return 'Claude (Anthropic)'; }

    public function translate(string $text, string $sourceLang, string $targetLang, string $context = ''): string
    {
        $system = 'You are a professional translator specialized in software UI strings. Translate the text exactly, preserving the tone, formatting and any placeholders (like {{name}} or <b>...</b>). Return ONLY the translated text, with no quotes, commentary or explanation.';
        $contextNote = $context !== '' ? "\nContext: $context" : '';
        $user = "Source language: $sourceLang\nTarget language: $targetLang$contextNote\n\nText:\n$text";

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1000,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Claude request failed: $err");
        }
        if ($status >= 400) {
            throw new RuntimeException("Claude API error ($status): " . substr((string) $response, 0, 300));
        }
        $data = json_decode((string) $response, true);
        $translated = $data['content'][0]['text'] ?? null;
        if (!is_string($translated) || $translated === '') {
            throw new RuntimeException('Claude returned empty translation');
        }
        return trim($translated);
    }
}
