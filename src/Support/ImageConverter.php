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

    /**
     * Prepara a imagem para envio ao WordPress: reduz para no máximo `$maxWidth`
     * de largura e re-codifica em **WebP** (bem menor que JPEG — seo.md#imagens).
     * Cai para JPEG se `imagewebp` não existir, e devolve os bytes originais se
     * `gd` não existir.
     *
     * @return array{bytes:string, ext:string, mime:string}
     */
    public static function forWeb(string $bytes, int $maxWidth = 1600, int $quality = 82): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return ['bytes' => $bytes, 'ext' => '', 'mime' => ''];
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return ['bytes' => $bytes, 'ext' => '', 'mime' => ''];
        }

        $w = imagesx($src);
        $h = imagesy($src);

        $scale = $w > $maxWidth ? $maxWidth / $w : 1.0;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $q = max(40, min(95, $quality));
        $webp = function_exists('imagewebp');

        ob_start();
        $ok = $webp ? imagewebp($dst, null, $q) : imagejpeg($dst, null, $q);
        $out = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (!$ok || $out === '') {
            return ['bytes' => $bytes, 'ext' => '', 'mime' => ''];
        }

        return $webp
            ? ['bytes' => $out, 'ext' => 'webp', 'mime' => 'image/webp']
            : ['bytes' => $out, 'ext' => 'jpg', 'mime' => 'image/jpeg'];
    }
}
