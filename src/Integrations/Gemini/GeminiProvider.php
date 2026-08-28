<?php

declare(strict_types=1);

namespace App\Integrations\Gemini;

use App\Integrations\AIProvider;
use App\Integrations\AIResult;

/**
 * Implementação de `AIProvider` sobre a API do Gemini (integracoes.md §35).
 * Traduz prompt + schema no corpo `generateContent` e a resposta em `AIResult`.
 */
final class GeminiProvider implements AIProvider
{
    private GeminiClient $client;
    private string $model;

    public function __construct(?GeminiConfig $config = null, ?GeminiClient $client = null)
    {
        $config ??= GeminiConfig::fromEnv();
        $this->client = $client ?? new GeminiClient($config);
        $this->model = $config->model;
    }

    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult
    {
        return $this->run($prompt, $systemInstruction, null);
    }

    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult
    {
        return $this->run($prompt, $systemInstruction, $schema);
    }

    /** @param array<string, mixed>|null $schema */
    private function run(string $prompt, ?string $systemInstruction, ?array $schema): AIResult
    {
        $body = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
        ];

        if ($systemInstruction !== null && trim($systemInstruction) !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        if ($schema !== null) {
            $body['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema'   => $schema,
            ];
        }

        $response = $this->client->generateContent($body);

        $text = $this->extractText($response);
        $usage = $response['usageMetadata'] ?? [];

        $json = null;
        if ($schema !== null) {
            $parsed = json_decode($text, true);
            $json = is_array($parsed) ? $parsed : null;
        }

        return new AIResult(
            text: $text,
            json: $json,
            promptTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            totalTokens: (int) ($usage['totalTokenCount'] ?? 0),
            model: (string) ($response['modelVersion'] ?? $this->model),
            thoughtsTokens: (int) ($usage['thoughtsTokenCount'] ?? 0),
        );
    }

    /** @param array<string, mixed> $response */
    private function extractText(array $response): string
    {
        $candidate = $response['candidates'][0] ?? null;
        if ($candidate === null) {
            $reason = $response['promptFeedback']['blockReason'] ?? null;
            throw new GeminiException(
                $reason !== null
                    ? "Gemini bloqueou o prompt (motivo: {$reason})."
                    : 'Resposta do Gemini sem candidates.',
            );
        }

        $finish = $candidate['finishReason'] ?? 'STOP';
        $parts = $candidate['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        if ($text === '') {
            throw new GeminiException("Gemini retornou resposta vazia (finishReason: {$finish}).", retryable: $finish === 'MAX_TOKENS');
        }

        return $text;
    }
}
