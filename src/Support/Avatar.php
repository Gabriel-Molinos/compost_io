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
    /** Existe imagem de verdade pra esse caminho? (a mesma checagem que `html()` faz antes de escolher <img> ou iniciais). */
    public static function hasImage(?string $path): bool
    {
        return $path !== null && $path !== '' && is_file(dirname(__DIR__, 2) . '/public/' . $path);
    }

    public static function html(
        ?string $path,
        string $label,
        string $size = 'h-10 w-10',
        string $radius = 'rounded-lg',
        string $textSize = 'text-base',
        string $fit = 'cover',
        string $bg = 'bg-surface-2',
    ): string {
        if (self::hasImage($path)) {
            $src = View::e(View::asset($path));

            return "<img src=\"{$src}\" alt=\"\" class=\"" . self::imgClass($size, $radius, $fit, $bg) . "\">";
        }

        $label = trim($label);
        $initial = $label !== '' ? View::e(mb_strtoupper(mb_substr($label, 0, 1))) : '?';

        return "<span aria-hidden=\"true\" class=\"flex {$size} {$radius} shrink-0 items-center justify-center bg-brand-grad "
            . "{$textSize} font-display font-bold text-text-primary shadow-[0_0_0_1px_rgba(0,208,240,.25)]\">{$initial}</span>";
    }

    /**
     * Classes do `<img>` (variante "tem foto"), separado de `html()` — a
     * pré-visualização instantânea no cliente (`avatar-preview.js`, achado
     * real 2026-09-15: imagem só aparecia depois de salvar+recarregar)
     * precisa montar um `<img>` novo em JS antes de qualquer upload
     * terminar, e não pode simplesmente copiar a classe do elemento atual —
     * quando ainda não existe foto, o elemento atual é o `<span>` das
     * iniciais (classes de flex/cor completamente diferentes de um `<img>`
     * de verdade). Exposto via `data-avatar-img-class` nas Views.
     */
    public static function imgClass(string $size, string $radius, string $fit = 'cover', string $bg = 'bg-surface-2'): string
    {
        // "object-*" sempre literal (nunca montada por interpolação) — mesma
        // pegadinha do bug dos ícones: o scanner de conteúdo do Tailwind só
        // enxerga texto literal no arquivo, não o resultado em runtime de
        // uma string PHP montada com variável.
        $objectFit = $fit === 'contain' ? 'object-contain' : 'object-cover';

        return "{$size} {$radius} shrink-0 {$bg} {$objectFit}";
    }
}
