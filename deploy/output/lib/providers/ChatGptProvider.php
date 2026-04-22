<?php
/**
 * Provider que traduce strings usando el Chat Completions API de OpenAI.
 *
 * Modelo por defecto: gpt-4o-mini (rápido y económico para traducciones
 * cortas típicas de UI). Se puede override con el setting openai_model.
 */
class ChatGptProvider implements TranslationProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        if ($apiKey === '') {
            throw new InvalidArgumentException('OpenAI API key is empty');
        }
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getId(): string { return 'chatgpt'; }
    public function getName(): string { return 'ChatGPT (OpenAI)'; }

    public function translate(string $text, string $sourceLang, string $targetLang, string $context = ''): string
    {
        $system = 'You are a professional translator specialized in software UI strings. Translate the text exactly, preserving the tone, formatting and any placeholders (like {{name}} or <b>...</b>). Return ONLY the translated text, with no quotes, commentary or explanation.';
        $contextNote = $context !== '' ? "\nContext: $context" : '';
        $user = "Source language: $sourceLang\nTarget language: $targetLang$contextNote\n\nText:\n$text";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1000,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("ChatGPT request failed: $err");
        }
        if ($status >= 400) {
            throw new RuntimeException("ChatGPT API error ($status): " . substr((string) $response, 0, 300));
        }
        $data = json_decode((string) $response, true);
        $translated = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($translated) || $translated === '') {
            throw new RuntimeException('ChatGPT returned empty translation');
        }
        return trim($translated);
    }
}
