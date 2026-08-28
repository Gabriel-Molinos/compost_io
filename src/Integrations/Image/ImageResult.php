<?php

declare(strict_types=1);

namespace App\Integrations\Image;

/**
 * Resultado de uma geração de imagem. `bytes` são os dados binários da imagem
 * (já decodificados do base64), no formato indicado por `mimeType`. A conversão
 * para WebP e a gravação em disco são responsabilidade de quem chama (fatia 5.3).
 */
final class ImageResult
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $totalTokens = 0,
    ) {
    }

    /** Extensão de arquivo correspondente ao mime type retornado. */
    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }
}
