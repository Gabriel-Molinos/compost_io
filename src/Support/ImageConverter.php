<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Conversão de imagem para WebP antes do upload (docs/editorial/seo.md#imagens,
 * integracoes.md §36). Usa a extensão `gd` — sem dependência Composer.
 *
 * Se `gd` não estiver habilitada, `toWebp()` lança — quem chama decide se isso
 * é bloqueante (no pipeline não é: guarda o formato original e registra um aviso).
 */
final class ImageConverter
{
    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagewebp');
    }

    /**
     * @param string $bytes  dados binários da imagem de origem (jpeg/png/…)
     * @return string dados binários em WebP
     * @throws RuntimeException
     */
    public static function toWebp(string $bytes, int $quality = 82): string
    {
        if (!self::available()) {
            throw new RuntimeException('Extensão PHP `gd` (com suporte a WebP) não está habilitada.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('Não foi possível ler os dados da imagem para conversão.');
        }

        // Preserva transparência (PNG de origem).
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, max(0, min(100, $quality)));
        $out = (string) ob_get_clean();
        imagedestroy($image);

        if (!$ok || $out === '') {
            throw new RuntimeException('Falha ao codificar a imagem em WebP.');
        }

        return $out;
    }
}
