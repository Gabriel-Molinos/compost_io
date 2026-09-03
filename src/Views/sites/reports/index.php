<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\Support\SvgChart;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $report */
/** @var array<string,mixed> $comparison */
/** @var array{periods:list<string>,produced:list<int>,published:list<int>,ai_cost:list<float>} $trend */
/** @var string $monthName */
/** @var string $prevMonthName */
/** @var string $prevMonth */
/** @var string $nextMonth */

$activeTab = 'reports';
require __DIR__ . '/../_tabs.php';

$n = static fn ($v): string => $v === null ? '—' : (string) $v;
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-lg font-semibold text-text-primary">Relatório mensal</h2>
    <div class="flex items-center gap-2 text-sm">
        <a href="?month=<?= View::e($prevMonth) ?>" class="rounded-md border border-border px-2 py-1 text-text-secondary hover:text-text-primary">←</a>
        <span class="min-w-[9rem] text-center font-medium text-text-primary"><?= View::e($monthName) ?></span>
        <a href="?month=<?= View::e($nextMonth) ?>" class="rounded-md border border-border px-2 py-1 text-text-secondary hover:text-text-primary">→</a>
    </div>
</div>

<dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php
    $card = static function (string $label, string $value, ?string $sub = null): void {
        echo '<div class="rounded-lg border border-border bg-surface p-4">';
        echo '<dt class="text-xs uppercase tracking-wide text-text-muted">' . View::e($label) . '</dt>';
        echo '<dd class="mt-1 font-mono text-2xl text-text-primary">' . View::e($value) . '</dd>';
        if ($sub !== null) {
            echo '<p class="mt-0.5 text-xs text-text-muted">' . View::e($sub) . '</p>';
        }
        echo '</div>';
    };

    $card('Meta do mês', $n($report['goal_total']), $report['goal_total'] === null ? 'sem meta cadastrada' : null);
    $card('Produzidos', (string) $report['produced']);
    $card('Aprovados', (string) $report['approved']);
    $card('Rejeitados', (string) $report['rejected']);
    $card('Publicados', (string) $report['published']);
    $card('Em revisão agora', (string) $report['pending_now']);
    $card('Taxa de aprovação', $report['approval_rate'] === null ? '—' : $report['approval_rate'] . '%',
        'aprovados / (aprovados + rejeitados)');
    $card('Tempo médio de revisão', $report['avg_review_hours'] === null ? '—' : $report['avg_review_hours'] . ' h',
        'do artigo entrar em revisão até aprovar/rejeitar');
    $card('Custo de IA', 'US$ ' . number_format((float) $report['ai_cost'], 2));
    ?>
</dl>

<section class="mt-8">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
        Comparado com <?= View::e($prevMonthName) ?>
    </h3>
    <?php if (!$comparison['had_data']): ?>
        <p class="mt-2 text-sm text-text-secondary">Sem dados no mês anterior — nada para comparar.</p>
    <?php else: ?>
        <?php
        // fmt: formata o delta; $goodDown = true quando cair é melhoria (custo, rejeições, tempo).
        $row = static function (string $label, ?array $d, string $unit = '', bool $goodDown = false) {
            echo '<li class="flex items-center justify-between border-b border-border py-1.5 text-sm">';
            echo '<span class="text-text-primary">' . View::e($label) . '</span>';
            if ($d === null) {
                echo '<span class="font-mono text-text-muted">—</span></li>';
                return;
            }
            $v = round($d['value'], 2);
            if ($v == 0.0) {
                echo '<span class="font-mono text-text-muted">sem mudança</span></li>';
                return;
            }
            $improved = $goodDown ? $v < 0 : $v > 0;
            $color = $improved ? 'text-success' : 'text-warning';
            $sign = $v > 0 ? '+' : '';
            $num = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
            echo '<span class="font-mono ' . $color . '">' . $sign . $num . $unit
                . ' <span class="text-text-muted">(' . rtrim(rtrim(number_format($d['from'], 2, '.', ''), '0'), '.')
                . ' → ' . rtrim(rtrim(number_format($d['to'], 2, '.', ''), '0'), '.') . ')</span></span></li>';
        };
        ?>
        <ul class="mt-3 space-y-1">
            <?php
            $row('Produzidos', $comparison['produced']);
            $row('Aprovados', $comparison['approved']);
            $row('Rejeitados', $comparison['rejected'], '', true);
            $row('Publicados', $comparison['published']);
            $row('Taxa de aprovação', $comparison['approval_rate'], ' p.p.');
            $row('Tempo médio de revisão', $comparison['avg_review_hours'], ' h', true);
            $row('Custo de IA', $comparison['ai_cost'], ' US$', true);
            ?>
        </ul>
        <?php
        $movedReasons = array_filter($comparison['reject_reasons'], static fn ($r) => $r['delta'] !== 0);
        ?>
        <?php if ($movedReasons !== []): ?>
            <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-text-muted">Motivos de rejeição que mudaram</h4>
            <ul class="mt-2 space-y-1 text-sm">
                <?php foreach ($movedReasons as $r): ?>
                    <li class="flex items-center justify-between border-b border-border py-1.5">
                        <span class="text-text-primary"><?= View::e(ArticleReviewService::REJECT_REASONS[$r['reason']] ?? $r['reason']) ?></span>
                        <span class="font-mono <?= $r['delta'] < 0 ? 'text-success' : 'text-warning' ?>">
                            <?= $r['delta'] > 0 ? '+' : '' ?><?= (int) $r['delta'] ?>
                            <span class="text-text-muted">(<?= (int) $r['from'] ?> → <?= (int) $r['to'] ?>)</span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="mt-8">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
        Tendência (últimos <?= count($trend['periods']) ?> meses)
    </h3>
    <?php
    $mesesAbrev = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun',
        '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
    $shortLabel = static function (string $p) use ($mesesAbrev): string {
        [$y, $m] = explode('-', $p);
        return $mesesAbrev[$m] . '/' . substr($y, 2);
    };
    $labels = array_map($shortLabel, $trend['periods']);

    $trendCard = static function (string $title, array $values, string $color, callable $format, string $unitForTable) use ($labels): void {
        echo '<div class="rounded-lg border border-border bg-surface p-4">';
        echo '<p class="text-sm font-medium text-text-primary">' . View::e($title) . '</p>';
        if (array_sum(array_map('floatval', $values)) <= 0) {
            echo '<p class="mt-3 text-sm text-text-secondary">Sem dados no período.</p>';
        } else {
            echo '<div class="mt-2">' . SvgChart::bars($labels, $values, $color, $format) . '</div>';
            // Fallback acessível (WCAG 2.2 AA) — o SVG acima é aria-hidden.
            echo '<table class="sr-only"><caption>' . View::e($title) . ' por mês</caption>';
            echo '<thead><tr><th scope="col">Mês</th><th scope="col">' . View::e($unitForTable) . '</th></tr></thead><tbody>';
            foreach ($labels as $i => $l) {
                echo '<tr><td>' . View::e($l) . '</td><td>' . View::e($format($values[$i])) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    };
    ?>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <?php
        $trendCard('Artigos produzidos', $trend['produced'], '#0AFFEF', static fn ($v) => (string) (int) $v, 'Produzidos');
        $trendCard('Artigos publicados', $trend['published'], '#34D399', static fn ($v) => (string) (int) $v, 'Publicados');
        $trendCard('Custo de IA', $trend['ai_cost'], '#FBBF24', static fn ($v) => 'US$ ' . number_format((float) $v, 2), 'Custo (US$)');
        ?>
    </div>
</section>

<section class="mt-8">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Por categoria (meta × realizado)</h3>
    <?php if ($report['categories'] === []): ?>
        <p class="mt-2 text-sm text-text-secondary">Nenhuma categoria cadastrada.</p>
    <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-1">Categoria</th><th class="py-1 text-right">Meta</th>
                    <th class="py-1 text-right">Aprovados</th><th class="py-1 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php foreach ($report['categories'] as $c): ?>
                    <?php $saldo = (int) $c['produced'] - (int) $c['target']; ?>
                    <tr>
                        <td class="py-1.5 text-text-primary"><?= View::e($c['name']) ?></td>
                        <td class="py-1.5 text-right font-mono text-text-secondary"><?= (int) $c['target'] ?></td>
                        <td class="py-1.5 text-right font-mono text-text-secondary"><?= (int) $c['produced'] ?></td>
                        <td class="py-1.5 text-right font-mono <?= $saldo < 0 ? 'text-warning' : 'text-success' ?>">
                            <?= $saldo > 0 ? '+' : '' ?><?= $saldo ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="mt-8">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Motivos de rejeição</h3>
    <?php if ($report['reject_reasons'] === []): ?>
        <p class="mt-2 text-sm text-text-secondary">Nenhuma rejeição neste mês.</p>
    <?php else: ?>
        <ul class="mt-3 space-y-1 text-sm">
            <?php foreach ($report['reject_reasons'] as $r): ?>
                <li class="flex items-center justify-between border-b border-border py-1.5">
                    <span class="text-text-primary"><?= View::e(ArticleReviewService::REJECT_REASONS[$r['reason']] ?? $r['reason']) ?></span>
                    <span class="font-mono text-text-secondary"><?= (int) $r['total'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
