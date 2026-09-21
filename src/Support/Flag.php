<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bandeiras em SVG inline (autorais, simplificadas — mesma filosofia do
 * `Icon`: sem biblioteca nem imagem externa). Não usa emoji de bandeira de
 * propósito: o Windows não desenha emoji de bandeira (aparece só "BR", "CA"),
 * e o COMPOST roda em máquinas Windows.
 *
 * Proporção 3:2 (viewBox 24×16). O tamanho vem de quem chama (classes na View
 * ou o wrapper) — o SVG só tem `width:100%`.
 */
final class Flag
{
    private const PARTS = [
        'br' => '<rect width="24" height="16" fill="#009B3A"/>'
            . '<path d="M12 2.1 21.6 8 12 13.9 2.4 8Z" fill="#FEDF00"/>'
            . '<circle cx="12" cy="8" r="3.5" fill="#002776"/>'
            . '<path d="M8.6 7.1c2.3-.7 5.1-.3 7.2 1.2" fill="none" stroke="#fff" stroke-width=".7"/>',
        'ca' => '<rect width="24" height="16" fill="#fff"/>'
            . '<rect width="6" height="16" fill="#D80621"/><rect x="18" width="6" height="16" fill="#D80621"/>'
            . '<path fill="#D80621" d="M12 2.6l1 2 1.2-.5-.5 3.1 1.7-1.5.4.9 2.2-.4-.8 2.2.7.4-2.9 2.6.3 1-3-.4V14h-.6v-2.4l-3 .4.3-1-2.9-2.6.7-.4-.8-2.2 2.2.4.4-.9 1.7 1.5-.5-3.1 1.2.5Z"/>',
        'es' => '<rect width="24" height="16" fill="#AA151B"/>'
            . '<rect y="4" width="24" height="8" fill="#F1BF00"/>',
        'us' => '<rect width="24" height="16" fill="#B22234"/>'
            . '<rect y="1.23" width="24" height="1.23" fill="#fff"/>'
            . '<rect y="3.69" width="24" height="1.23" fill="#fff"/>'
            . '<rect y="6.15" width="24" height="1.23" fill="#fff"/>'
            . '<rect y="8.61" width="24" height="1.23" fill="#fff"/>'
            . '<rect y="11.08" width="24" height="1.23" fill="#fff"/>'
            . '<rect y="13.54" width="24" height="1.23" fill="#fff"/>'
            . '<rect width="10" height="8.6" fill="#3C3B6E"/>',
    ];

    public static function svg(string $code): string
    {
        $parts = self::PARTS[$code] ?? '<rect width="24" height="16" fill="#8FA6BC"/>';

        return '<svg viewBox="0 0 24 16" width="100%" height="100%" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">'
            . $parts . '</svg>';
    }
}
