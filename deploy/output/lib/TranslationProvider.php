<?php
/**
 * Interfaz base para providers de traducción con IA.
 *
 * Todos los providers reciben el mismo contrato:
 *   - $text: el string fuente a traducir (puede incluir placeholders {{x}}
 *            que deben preservarse textualmente).
 *   - $sourceLang: código ISO 639-1 (ej. 'en').
 *   - $targetLang: código ISO 639-1 (ej. 'es').
 *   - $context: string opcional con contexto semántico (ej. "UI button for
 *            saving settings"). Los providers LLM lo incluyen en el prompt;
 *            Google Translate lo ignora.
 *
 * Retornan el texto traducido o lanzan RuntimeException con un mensaje
 * legible en inglés (para logs) si algo falla.
 */
interface TranslationProvider
{
    public function translate(string $text, string $sourceLang, string $targetLang, string $context = ''): string;

    /** Identificador corto del provider: 'chatgpt' | 'claude' | 'google'. */
    public function getId(): string;

    /** Nombre legible. */
    public function getName(): string;
}
