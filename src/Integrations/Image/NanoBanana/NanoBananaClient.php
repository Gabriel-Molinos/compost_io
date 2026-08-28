<?php

declare(strict_types=1);

namespace App\Integrations\Image\NanoBanana;

use App\Integrations\Image\ImageException;
use App\Support\CaBundle;

/**
 * Cliente HTTP de baixo nível para a API de imagem do Gemini (endpoint
 * `POST /v1beta/interactions`). Só sabe fazer um POST autenticado e devolver o
 * JSON decodificado — não conhece briefs nem regras editoriais (isso é do
 * `NanoBananaProvider`). Sem dependência externa: `curl` nativo (ADR-002).
 *
 * Espelha `Gemini\GeminiClient` de propósito — mesma autenticação, mesmo
 * tratamento de erro e mesma classificação `retryable`.
 */
final class NanoBananaClient
{
    public function __construct(private readonly NanoBananaConfig $config)
    {
        if (!function_exists('curl_init')) {
            throw new ImageException('Extensão PHP `curl` não está habilitada.');
        }
    }

    /**
     * @param array<string, mixed> $body corpo da requisição CreateInteraction
     * @return array<string, mixed> resposta JSON decodificada
     * @throws ImageException
     */
    public function createInteraction(array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new ImageException('Não foi possível serializar o corpo da requisição.');
        }

        $ch = curl_init($this->config->interactionsUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->config->timeoutSeconds,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->config->apiKey,
                'Api-Revision: ' . $this->config->apiRevision,
            ],
        ]);
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
            throw new ImageException("Falha de rede ao chamar o Nano Banana: {$error} (curl {$errno}).", retryable: true);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new ImageException("Resposta do Nano Banana não é JSON (HTTP {$status}).", retryable: $status >= 500, httpStatus: $status);
        }

        if ($status < 200 || $status >= 300) {
            $apiMessage = $decoded['error']['message'] ?? 'erro desconhecido';
            $retryable = $status === 429 || $status >= 500;
            throw new ImageException("Nano Banana retornou HTTP {$status}: {$apiMessage}", retryable: $retryable, httpStatus: $status);
        }

        return $decoded;
    }
}
