<?php

declare(strict_types=1);

namespace App\Integrations\Image\NanoBanana;

use App\Integrations\Image\ImageException;
use App\Integrations\Image\ImageProvider;
use App\Integrations\Image\ImageRequest;
use App\Integrations\Image\ImageResult;

/**
 * Implementação de `ImageProvider` sobre a API de imagem do Gemini / Nano Banana
 * (integracoes.md §36). Traduz `ImageRequest` no corpo `CreateInteraction` e a
 * resposta em `ImageResult` (bytes já decodificados do base64).
 */
final class NanoBananaProvider implements ImageProvider
{
    private NanoBananaClient $client;
    private NanoBananaConfig $config;

    public function __construct(?NanoBananaConfig $config = null, ?NanoBananaClient $client = null)
    {
        $this->config = $config ?? NanoBananaConfig::fromEnv();
        $this->client = $client ?? new NanoBananaClient($this->config);
    }

    public function generate(ImageRequest $request): ImageResult
    {
        $body = [
            'model' => $this->config->model,
            'input' => $request->prompt,
            'response_format' => [
                'type'         => 'image',
                'aspect_ratio' => $request->aspectRatio,
                'image_size'   => $request->size,
            ],
        ];

        $response = $this->client->createInteraction($body);

        $status = (string) ($response['status'] ?? '');
        if ($status === 'failed') {
            $msg = (string) ($response['error']['message'] ?? 'geração de imagem falhou');
            throw new ImageException("Nano Banana: {$msg}");
        }

        [$data, $mime] = $this->extractImage($response);

        $bytes = base64_decode($data, true);
        if ($bytes === false || $bytes === '') {
            throw new ImageException('Nano Banana: dados da imagem não são base64 válido.');
        }

        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        return new ImageResult(
            bytes: $bytes,
            mimeType: $mime,
            model: (string) ($response['model'] ?? $this->config->model),
            inputTokens: (int) ($usage['total_input_tokens'] ?? 0),
            outputTokens: (int) ($usage['total_output_tokens'] ?? 0),
            totalTokens: (int) ($usage['total_tokens'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $response
     * @return array{0:string,1:string} [base64, mimeType]
     */
    private function extractImage(array $response): array
    {
        // Atalho quando a API devolve o resumo: interaction.output_image.{data,mime_type}
        $img = $response['output_image'] ?? null;
        if (is_array($img) && trim((string) ($img['data'] ?? '')) !== '') {
            return [(string) $img['data'], (string) ($img['mime_type'] ?? 'image/jpeg')];
        }

        // Forma normal: um passo `model_output` com uma parte `type: image`
        // em steps[].content[] — { type: "image", data: "<base64>", mime_type }.
        foreach ((array) ($response['steps'] ?? []) as $step) {
            if (!is_array($step)) {
                continue;
            }
            foreach ((array) ($step['content'] ?? []) as $part) {
                if (!is_array($part) || ($part['type'] ?? '') !== 'image') {
                    continue;
                }
                // Alguns formatos aninham em `image`/`inline_data`; a maioria traz `data` direto.
                $inline = $part['image'] ?? $part['inline_data'] ?? $part['inlineData'] ?? $part;
                if (is_array($inline) && trim((string) ($inline['data'] ?? '')) !== '') {
                    $mime = (string) ($inline['mime_type'] ?? $inline['mimeType'] ?? 'image/jpeg');
                    return [(string) $inline['data'], $mime];
                }
            }
        }

        $reason = (string) ($response['status'] ?? 'sem output_image');
        throw new ImageException("Nano Banana: resposta sem imagem ({$reason}).");
    }
}
