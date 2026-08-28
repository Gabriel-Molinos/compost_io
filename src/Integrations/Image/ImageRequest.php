<?php

declare(strict_types=1);

namespace App\Integrations\Image;

/**
 * Pedido de geração de uma imagem — o que o passo `image` (brief visual, docs/ai/image.md)
 * produz e o `ImageProvider` consome. Imutável e sem dependência de fornecedor.
 */
final class ImageRequest
{
    /** Proporções aceitas pela API de imagem do Gemini (docs/integrations/images.md). */
    public const ASPECT_RATIOS = ['1:1', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16'];

    /** Resoluções aceitas (`image_size`). */
    public const SIZES = ['1K', '2K', '4K'];

    public function __construct(
        public readonly string $prompt,
        public readonly string $aspectRatio = '16:9',
        public readonly string $size = '1K',
    ) {
        if (trim($prompt) === '') {
            throw new ImageException('ImageRequest: prompt vazio.');
        }
        if (!in_array($aspectRatio, self::ASPECT_RATIOS, true)) {
            throw new ImageException("ImageRequest: aspect ratio inválido: {$aspectRatio}.");
        }
        if (!in_array($size, self::SIZES, true)) {
            throw new ImageException("ImageRequest: image_size inválido: {$size}.");
        }
    }
}
