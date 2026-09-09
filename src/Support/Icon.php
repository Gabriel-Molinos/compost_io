<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ícones de linha, autorais (sem biblioteca/font externa — mesma filosofia
 * "sem dependência pesada" do resto do projeto), usados na navegação da
 * sidebar (layout/_nav.php). SVG inline, 24×24, `currentColor` — herda a cor
 * do texto do link ao redor, incluindo o estado ativo (ciano).
 */
final class Icon
{
    /**
     * Tamanho via atributos SVG (`width`/`height`) + `style` inline, não classes
     * Tailwind — este arquivo fica fora de `src/Views/`, então o scanner de
     * conteúdo do Tailwind (`tailwind.config.js`) nunca o lê; uma classe daqui
     * some silenciosamente do CSS compilado (foi exatamente o bug: os ícones
     * saíam do tamanho padrão do navegador, gigantes, espremendo o rótulo).
     */
    private const VIEWBOX_OPEN = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" '
        . 'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0">';

    private const PATHS = [
        // --- navegação global ---
        'home'  => '<path d="M4 11 12 4l8 7"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1v-9"/>',
        'sites' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/>'
            . '<rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        'users' => '<circle cx="8.5" cy="9" r="3"/><circle cx="16.5" cy="9.5" r="2.4"/>'
            . '<path d="M2.5 19.8c.6-3.5 3-5.6 6-5.6s5.4 2.1 6 5.6"/><path d="M15 19.8c.4-2.4 1.7-4.1 3.6-4.8"/>',
        'bell'  => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 2 6.5H4c.5-1 2-2.5 2-6.5Z"/><path d="M9.5 19.5a2.5 2.5 0 0 0 5 0"/>',
        // --- tela de login ---
        'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 7 8.5-7"/>',
        'lock'  => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'help'   => '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.5a2.8 2.8 0 0 1 5.4.9c0 1.8-2.6 2.1-2.6 3.9"/><path d="M12 17.5h.01"/>',
        // --- abas do site ---
        'overview'     => '<rect x="3" y="12" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="17" rx="1"/>',
        'categories'   => '<path d="M3 3h7l11 11-7 7L3 10V3Z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'rules'        => '<path d="M12 3.5l2.5 5.3 5.8.6-4.3 3.9 1.2 5.7L12 15.9l-5.2 3.1 1.2-5.7-4.3-3.9 5.8-.6L12 3.5Z"/>',
        'memory'       => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 13l9 5 9-5"/>',
        'goals'        => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4"/>',
        'production'   => '<path d="M4 20l1-4.5L15.5 5l3.5 3.5L8.5 19 4 20Z"/><path d="M13 7l3.5 3.5"/>',
        'calendar'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9.5h18"/><path d="M8 3v4M16 3v4"/>',
        'reports'      => '<path d="M3 17l5-6 4 3 7-9"/><path d="M14 5h5v5"/>',
        'intelligence' => '<path d="M12 3l1.4 4.6L18 9l-4.6 1.4L12 15l-1.4-4.6L6 9l4.6-1.4L12 3Z"/>'
            . '<path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/>',
        'config'       => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3'
            . 'M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4 5.6 5.6"/>',
        'wordpress'    => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5v17"/><path d="M6 6.5c2 2 10 2 12 0M6 17.5c2-2 10-2 12 0"/>',
        'ai'           => '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/>'
            . '<path d="M9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/>',
    ];

    /** SVG inline (18×18) do ícone `$key`, ou um círculo vazio genérico se a chave não existir. */
    public static function nav(string $key): string
    {
        $path = self::PATHS[$key] ?? '<circle cx="12" cy="12" r="7"/>';

        return self::VIEWBOX_OPEN . $path . '</svg>';
    }
}
