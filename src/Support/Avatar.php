<?php

declare(strict_types=1);

namespace App\Support;

use App\View;

/**
 * Chip de avatar/logo reutilizado em toda a interface (sidebar, tela de
 * início, listas de usuários/sites): mostra a imagem enviada quando existe,
 * e cai pro chip com a inicial em degradê de marca quando não existe — a
 * mesma "moldura" nos dois casos, pra nunca ter um <img> quebrado no layout
 * nem dois estilos visuais concorrentes pra "sem foto ainda".
 */
final class Avatar
{
    public static function html(
        ?string $path,
        string $label,
        string $size = 'h-10 w-10',
        string $radius = 'rounded-lg',
        string $textSize = 'text-base',
        string $fit = 'cover',
    ): string {
        if ($path !== null && $path !== '' && is_file(dirname(__DIR__, 2) . '/public/' . $path)) {
            $src = View::e(View::asset($path));
            // Classe "object-*" sempre literal (nunca montada por interpolação)
            // — é a mesma pegadinha do bug dos ícones: o scanner de conteúdo do
            // Tailwind só enxerga texto literal no arquivo, não o resultado em
            // runtime de uma string PHP montada com variável.
            $objectFit = $fit === 'contain' ? 'object-contain' : 'object-cover';
            // bg-surface-2 embaixo: some no "cover" (a imagem preenche tudo),
            // mas no "contain" (logo não-quadrado numa moldura larga) vira o
            // fundo das sobras — a logo inteira aparece, sem esticar/cortar.
            return "<img src=\"{$src}\" alt=\"\" class=\"{$size} {$radius} shrink-0 bg-surface-2 {$objectFit}\">";
        }

        $label = trim($label);
        $initial = $label !== '' ? View::e(mb_strtoupper(mb_substr($label, 0, 1))) : '?';

        return "<span aria-hidden=\"true\" class=\"flex {$size} {$radius} shrink-0 items-center justify-center bg-brand-grad "
            . "{$textSize} font-display font-bold text-text-primary shadow-[0_0_0_1px_rgba(0,208,240,.25)]\">{$initial}</span>";
    }
}
