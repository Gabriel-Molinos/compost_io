<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Regras da imagem que o próprio redator sobe (destacada ou de corpo) no lugar
 * das geradas pela IA. Um só lugar pras regras: a tela lê as constantes pra
 * mostrar o que é exigido, e `validate()` confere o arquivo de verdade —
 * pelos bytes, nunca pela extensão ou pelo tipo que o navegador declarou.
 *
 * WebP, largura e proporção seguem docs/editorial/seo.md#imagens e o padrão do
 * gerador (16:9); a publicação ainda reduz pra no máximo 1600 px de largura
 * (ImageConverter::forWeb), então subir maior que isso não traz ganho.
 */
final class ImageUploadValidator
{
    public const MIN_WIDTH = 1200;
    public const MAX_WIDTH = 2560;
    /** Proporção exigida (largura ÷ altura) — a mesma que o gerador usa por padrão. */
    public const RATIO_W = 16;
    public const RATIO_H = 9;
    /** Tolerância pra arredondamento de pixels (1201×675 não deve reprovar). */
    private const RATIO_TOLERANCE = 0.02;
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** Altura correspondente à largura mínima (ex.: 1200 → 675). */
    public static function minHeight(): int
    {
        return (int) round(self::MIN_WIDTH * self::RATIO_H / self::RATIO_W);
    }

    /**
     * @return array{width:int, height:int}
     * @throws InvalidArgumentException com mensagem pronta pra mostrar ao redator
     */
    public static function validate(string $bytes): array
    {
        if ($bytes === '') {
            throw new InvalidArgumentException('O arquivo está vazio.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'A imagem tem %.1f MB — o limite é %d MB. Comprima o WebP e tente de novo.',
                strlen($bytes) / 1048576,
                self::MAX_BYTES / 1048576,
            ));
        }

        // Assinatura de um WebP: "RIFF" + tamanho + "WEBP".
        if (strlen($bytes) < 12 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            throw new InvalidArgumentException(
                'O arquivo não é WebP. Converta a imagem para .webp antes de enviar (PNG/JPG não são aceitos).'
            );
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || ($info[2] ?? null) !== IMAGETYPE_WEBP) {
            throw new InvalidArgumentException('Não foi possível ler as dimensões do WebP — o arquivo parece corrompido.');
        }

        [$w, $h] = [(int) $info[0], (int) $info[1]];

        if ($w < self::MIN_WIDTH || $w > self::MAX_WIDTH) {
            throw new InvalidArgumentException(sprintf(
                'A imagem tem %d px de largura — precisa ficar entre %d e %d px (mínimo %d×%d).',
                $w, self::MIN_WIDTH, self::MAX_WIDTH, self::MIN_WIDTH, self::minHeight(),
            ));
        }

        $expected = self::RATIO_W / self::RATIO_H;
        if (abs(($w / max(1, $h)) / $expected - 1) > self::RATIO_TOLERANCE) {
            throw new InvalidArgumentException(sprintf(
                'A imagem é %d×%d px, que não está na proporção %d:%d. Use, por exemplo, %d×%d ou 1600×900 px.',
                $w, $h, self::RATIO_W, self::RATIO_H, self::MIN_WIDTH, self::minHeight(),
            ));
        }

        return ['width' => $w, 'height' => $h];
    }
}
