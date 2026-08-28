<?php

declare(strict_types=1);

namespace App\Integrations\Gemini;

use App\Support\CaBundle;

/**
 * Cliente HTTP de baixo nível para a API do Gemini. Só sabe fazer um POST
 * autenticado e devolver o JSON decodificado — não conhece prompts, camadas
 * nem regras editoriais (isso é do `GeminiProvider`).
 *
 * Sem dependência externa: usa a extensão `curl` nativa (ADR-002).
 */
final class GeminiClient
{
    public function __construct(private readonly GeminiConfig $config)
    {
        if (!function_exists('curl_init')) {
            throw new GeminiException('Extensão PHP `curl` não está habilitada.');
        }
    }

    /**
     * @param array<string, mixed> $body corpo da requisição generateContent
     * @return array<string, mixed> resposta JSON decodificada
     * @throws GeminiException
     */
    public function generateContent(array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new GeminiException('Não foi possível serializar o corpo da requisição.');
        }

        $ch = curl_init($this->config->generateContentUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconds,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->config->apiKey,
            ],
        ]);
        // O PHP local usa OpenSSL sem curl.cainfo — aponta o bundle de CA versionado.
        $caBundle = CaBundle::path();
        if (is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            // 28 = timeout; 7 = não conectou; 35/60 = TLS — todos valem retry.
            throw new GeminiException("Falha de rede ao chamar o Gemini: {$error} (curl {$errno}).", retryable: true);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new GeminiException("Resposta do Gemini não é JSON (HTTP {$status}).", retryable: $status >= 500, httpStatus: $status);
        }

        if ($status < 200 || $status >= 300) {
            $apiMessage = $decoded['error']['message'] ?? 'erro desconhecido';
            $retryable = $status === 429 || $status >= 500;
            throw new GeminiException("Gemini retornou HTTP {$status}: {$apiMessage}", retryable: $retryable, httpStatus: $status);
        }

        return $decoded;
    }
}
