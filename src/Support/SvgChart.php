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
     */
    public static function bars(
        array $labels,
        array $values,
        string $color = '#00D0F0', // cyan.DEFAULT (docs/product/identidade-visual.md)
        ?callable $format = null,
        int $width = 640,
        int $height = 180,
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

            $bars .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="%s" />',
                $x, $y, $barWidth, max($barHeight, 0.0), $color
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

        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="h-auto w-full" aria-hidden="true">' . $bars . '</svg>';
    }
}
