<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gráfico de barras simples, em SVG puro — sem JavaScript, sem biblioteca
 * externa (mesma filosofia "sem dependência pesada" do resto do backend).
 * Pensado pra séries curtas (poucos meses de tendência, Fase 9), não é um
 * componente de gráfico genérico.
 *
 * Acessibilidade (WCAG 2.2 AA, docs/technical/ui-ux-frontend.md): o SVG é
 * decorativo (`aria-hidden`) — quem chama deve renderizar junto uma tabela
 * `sr-only` com os mesmos dados (ver src/Views/sites/reports/index.php).
 */
final class SvgChart
{
    /**
     * @param list<string> $labels rótulos abaixo de cada barra (ex.: meses), mesma ordem de $values
     * @param list<float|int> $values valores das barras
     * @param callable(float|int): string|null $format formata o valor mostrado acima da barra (default: inteiro)
     * @param string|null $colorEnd Segunda cor: cada barra vira um degradê $color (embaixo) → $colorEnd
     *        (em cima), em vez de uma cor chapada só (pedido do responsável, 2026-09-21: "anima ela
     *        com mais cores"). null mantém a cor única de sempre — não muda quem já chama sem isso
     *        (Views/sites/reports/index.php).
     * @param bool $animated Barra "cresce" do zero na entrada (stagger por índice, só `transform`,
     *        compositor) — pedido do responsável: "não deixa essa barra ciano parada". Sempre
     *        `false` por padrão pelo mesmo motivo do $colorEnd.
     */
    public static function bars(
        array $labels,
        array $values,
        string $color = '#00D0F0', // cyan.DEFAULT (docs/product/identidade-visual.md)
        ?callable $format = null,
        int $width = 640,
        int $height = 180,
        ?string $colorEnd = null,
        bool $animated = false,
    ): string {
        $n = count($values);
        if ($n === 0) {
            return '';
        }
        $format ??= static fn (float|int $v): string => (string) $v;

        $max = max([...$values, 0]);
        $max = $max > 0 ? $max : 1;

        $padTop = 28;    // espaço pro valor acima da barra (fonte maior que antes — legibilidade)
        $padBottom = 24; // espaço pro rótulo do mês
        $chartHeight = $height - $padTop - $padBottom;
        $gap = 10;
        $barWidth = ($width - $gap * ($n + 1)) / $n;

        // Um id por chamada (não por processo): duas chamadas na mesma página (Views/sites/reports/
        // index.php desenha vários gráficos na mesma tela) não podem compartilhar <linearGradient>/
        // @keyframes — colidiria e um "roubaria" a animação/degradê do outro.
        $uid = 'sc' . substr(md5(uniqid('', true)), 0, 6);

        $defs = '';
        $fill = htmlspecialchars($color, ENT_QUOTES);
        if ($colorEnd !== null) {
            $defs = sprintf(
                '<defs><linearGradient id="%s" x1="0" y1="1" x2="0" y2="0">'
                    . '<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/>'
                    . '</linearGradient></defs>',
                $uid, htmlspecialchars($color, ENT_QUOTES), htmlspecialchars($colorEnd, ENT_QUOTES)
            );
            $fill = 'url(#' . $uid . ')';
        }

        // Linha de base (eixo) — dá referência visual de "zero" que faltava.
        $bars = sprintf(
            '<line x1="0" y1="%d" x2="%d" y2="%d" stroke="#3D5266" stroke-width="1" />',
            $padTop + $chartHeight, $width, $padTop + $chartHeight
        );
        foreach (array_values($values) as $i => $v) {
            $barHeight = ((float) $v / $max) * $chartHeight;
            $x = $gap + $i * ($barWidth + $gap);
            $y = $padTop + ($chartHeight - $barHeight);
            $label = $labels[$i] ?? '';

            $barAttrs = $animated
                ? sprintf(' class="%s-bar" style="animation-delay:%dms"', $uid, $i * 70)
                : '';
            $bars .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="%s"%s />',
                $x, $y, $barWidth, max($barHeight, 0.0), $fill, $barAttrs
            );
            // Valor acima da barra: texto de destaque (branco, semi-negrito, maior) —
            // é o dado que o usuário veio ler, não pode ter o mesmo peso do rótulo do mês.
            $bars .= sprintf(
                '<text x="%.1f" y="%d" text-anchor="middle" font-size="13" font-weight="600" fill="#F0F8FF">%s</text>',
                $x + $barWidth / 2, $padTop - 10, htmlspecialchars($format($v), ENT_QUOTES)
            );
            $bars .= sprintf(
                '<text x="%.1f" y="%d" text-anchor="middle" font-size="11" fill="#8FA6BC">%s</text>',
                $x + $barWidth / 2, $height - 6, htmlspecialchars((string) $label, ENT_QUOTES)
            );
        }

        // `transform-box: fill-box` + `transform-origin: bottom`: a barra cresce a partir da própria
        // base (não do canto 0,0 do SVG inteiro, que é o padrão). Só `transform` anima — compositor,
        // barato — e `backwards` mantém escala 0 até o delay de cada barra chegar.
        $style = $animated ? sprintf(
            '<style>.%1$s-bar{transform-box:fill-box;transform-origin:bottom;animation:%1$s-grow .7s cubic-bezier(.22,1,.36,1) backwards}'
                . '@keyframes %1$s-grow{from{transform:scaleY(0)}to{transform:scaleY(1)}}'
                . '@media (prefers-reduced-motion: reduce){.%1$s-bar{animation:none}}</style>',
            $uid
        ) : '';

        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="h-auto w-full" aria-hidden="true">'
            . $style . $defs . $bars . '</svg>';
    }
}
