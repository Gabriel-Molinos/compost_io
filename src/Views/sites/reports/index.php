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
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Relatório mensal</h1>
        <p class="mt-1 text-sm text-text-secondary"><?= View::e($monthName) ?> · desempenho editorial do site</p>
    </div>
    <div class="flex items-center overflow-hidden rounded-md border border-border">
        <a href="?month=<?= View::e($prevMonth) ?>" aria-label="Mês anterior"
           class="flex h-8 w-8 items-center justify-center text-text-secondary hover:bg-surface-2 hover:text-text-primary">‹</a>
        <span class="min-w-[8.5rem] border-x border-border px-3 py-1.5 text-center font-display text-sm font-semibold text-text-primary">
            <?= View::e($monthName) ?>
        </span>
        <a href="?month=<?= View::e($nextMonth) ?>" aria-label="Próximo mês"
           class="flex h-8 w-8 items-center justify-center text-text-secondary hover:bg-surface-2 hover:text-text-primary">›</a>
    </div>
</div>

<?php
// Barra de estatística única (grid + gap-px + bg-border faz as divisórias
// finas entre colunas, em qualquer quebra de linha responsiva — divide-x
// deixa borda sobrando no início de cada linha nova quando o grid quebra,
// esse truque não) em vez de cards separados lado a lado (§ "sopa de
// bordas" do sistema de design). Ordem: KPIs primários (o que o mês
// produziu de fato) antes dos secundários (custo, tempo, meta).
$statBar = static function (array $cols, string $gridCols): void {
    echo '<dl class="grid gap-px overflow-hidden rounded-lg border border-border bg-border ' . $gridCols . '">';
    foreach ($cols as [$label, $value, $sub]) {
        echo '<div class="bg-surface p-4">';
        echo '<dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">' . View::e($label) . '</dt>';
        echo '<dd class="mt-1 font-mono text-2xl font-semibold text-text-primary">' . View::e($value) . '</dd>';
        if ($sub !== null) {
            echo '<p class="mt-0.5 text-xs text-text-muted">' . View::e($sub) . '</p>';
        }
        echo '</div>';
    }
    echo '</dl>';
};
?>
<div class="mt-6 space-y-px">
    <?php
    $statBar([
        ['Produzidos', (string) $report['produced'], null],
        ['Aprovados', (string) $report['approved'], null],
        ['Publicados', (string) $report['published'], null],
        ['Taxa de aprovação', $report['approval_rate'] === null ? '—' : $report['approval_rate'] . '%', null],
    ], 'grid-cols-2 sm:grid-cols-4');
    $statBar([
        ['Meta do mês', $n($report['goal_total']), $report['goal_total'] === null ? 'sem meta cadastrada' : null],
        ['Rejeitados', (string) $report['rejected'], null],
        ['Em revisão agora', (string) $report['pending_now'], null],
        ['Tempo médio de revisão', $report['avg_review_hours'] === null ? '—' : $report['avg_review_hours'] . ' h', null],
        ['Custo de IA', 'US$ ' . number_format((float) $report['ai_cost'], 2), null],
    ], 'grid-cols-1 sm:grid-cols-5');
    ?>
</div>

<div class="mt-8 grid gap-5 lg:grid-cols-2">
    <section class="flex flex-col rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">
            Comparado com <?= View::e($prevMonthName) ?>
        </h2>
        <?php if (!$comparison['had_data']): ?>
            <p class="flex flex-1 items-center justify-center py-8 text-center text-sm text-text-secondary">
                Sem dados no mês anterior — nada para comparar.
            </p>
        <?php else: ?>
            <?php
            // fmt: formata o delta com seta ▲/▼; $goodDown = true quando cair é melhoria (custo, rejeições, tempo).
            $row = static function (string $label, ?array $d, string $unit = '', bool $goodDown = false) {
                echo '<div class="flex items-center justify-between border-b border-border py-2 text-sm last:border-0">';
                echo '<span class="text-text-secondary">' . View::e($label) . '</span>';
                if ($d === null) {
                    echo '<span class="font-mono text-text-muted">—</span></div>';
                    return;
                }
                $v = round($d['value'], 2);
                if ($v == 0.0) {
                    echo '<span class="font-mono text-xs text-text-muted">sem mudança</span></div>';
                    return;
                }
                $improved = $goodDown ? $v < 0 : $v > 0;
                $color = $improved ? 'text-success' : 'text-warning';
                $arrow = $v > 0 ? '▲' : '▼';
                $num = rtrim(rtrim(number_format(abs($v), 2, '.', ''), '0'), '.');
                echo '<span class="flex items-center gap-2">';
                echo '<span class="font-mono text-xs text-text-muted">' . rtrim(rtrim(number_format($d['from'], 2, '.', ''), '0'), '.')
                    . ' → ' . rtrim(rtrim(number_format($d['to'], 2, '.', ''), '0'), '.') . '</span>';
                echo '<span class="flex items-center gap-1 font-mono font-semibold ' . $color . '">' . $arrow . ' ' . $num . $unit . '</span>';
                echo '</span></div>';
            };
            ?>
            <div class="mt-2">
                <?php
                $row('Produzidos', $comparison['produced']);
                $row('Aprovados', $comparison['approved']);
                $row('Rejeitados', $comparison['rejected'], '', true);
                $row('Publicados', $comparison['published']);
                $row('Taxa de aprovação', $comparison['approval_rate'], ' p.p.');
                $row('Tempo médio de revisão', $comparison['avg_review_hours'], ' h', true);
                $row('Custo de IA', $comparison['ai_cost'], ' US$', true);
                ?>
            </div>
            <?php
            $movedReasons = array_filter($comparison['reject_reasons'], static fn ($r) => $r['delta'] !== 0);
            ?>
            <?php if ($movedReasons !== []): ?>
                <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-text-muted">Motivos de rejeição que mudaram</h3>
                <div class="mt-2">
                    <?php foreach ($movedReasons as $r): ?>
                        <div class="flex items-center justify-between border-b border-border py-2 text-sm last:border-0">
                            <span class="text-text-secondary"><?= View::e(ArticleReviewService::REJECT_REASONS[$r['reason']] ?? $r['reason']) ?></span>
                            <span class="flex items-center gap-2">
                                <span class="font-mono text-xs text-text-muted"><?= (int) $r['from'] ?> → <?= (int) $r['to'] ?></span>
                                <span class="flex items-center gap-1 font-mono font-semibold <?= $r['delta'] < 0 ? 'text-success' : 'text-warning' ?>">
                                    <?= $r['delta'] > 0 ? '▲' : '▼' ?> <?= abs((int) $r['delta']) ?>
                                </span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">
            Tendência <span class="font-sans text-sm font-normal text-text-muted">· últimos <?= count($trend['periods']) ?> meses</span>
        </h2>
        <?php
        $mesesAbrev = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun',
            '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
        $shortLabel = static function (string $p) use ($mesesAbrev): string {
            [$y, $m] = explode('-', $p);
            return $mesesAbrev[$m] . '/' . substr($y, 2);
        };
        $labels = array_map($shortLabel, $trend['periods']);

        $trendCard = static function (string $title, array $values, string $color, callable $format, string $unitForTable) use ($labels): void {
            echo '<div class="mt-4 first:mt-3">';
            echo '<p class="text-xs font-semibold uppercase tracking-wide text-text-muted">' . View::e($title) . '</p>';
            if (array_sum(array_map('floatval', $values)) <= 0) {
                echo '<p class="mt-2 text-sm text-text-muted">Sem dados no período.</p>';
            } else {
                echo '<div class="mt-1">' . SvgChart::bars($labels, $values, $color, $format, height: 120) . '</div>';
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
        $trendCard('Artigos produzidos', $trend['produced'], '#00D0F0', static fn ($v) => (string) (int) $v, 'Produzidos');
        $trendCard('Artigos publicados', $trend['published'], '#3DF07A', static fn ($v) => (string) (int) $v, 'Publicados');
        $trendCard('Custo de IA', $trend['ai_cost'], '#FFC53D', static fn ($v) => 'US$ ' . number_format((float) $v, 2), 'Custo (US$)');
        ?>
    </section>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <section class="rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Por categoria</h2>
        <p class="mt-1 text-sm text-text-secondary">Meta × realizado no mês.</p>
        <?php if ($report['categories'] === []): ?>
            <p class="mt-3 text-sm text-text-secondary">Nenhuma categoria cadastrada.</p>
        <?php else: ?>
            <div class="mt-3 overflow-x-auto rounded-md border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">Categoria</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Meta</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Aprovados</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['categories'] as $c): ?>
                            <?php $saldo = (int) $c['produced'] - (int) $c['target']; ?>
                            <tr class="border-b border-border/60 last:border-0">
                                <td class="truncate px-3 py-2 text-text-primary"><?= View::e($c['name']) ?></td>
                                <td class="px-3 py-2 text-right font-mono text-text-secondary"><?= (int) $c['target'] ?></td>
                                <td class="px-3 py-2 text-right font-mono text-text-secondary"><?= (int) $c['produced'] ?></td>
                                <td class="px-3 py-2 text-right font-mono <?= $saldo < 0 ? 'text-warning' : 'text-success' ?>">
                                    <?= $saldo > 0 ? '+' : '' ?><?= $saldo ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Motivos de rejeição</h2>
        <p class="mt-1 text-sm text-text-secondary">Por que artigos foram rejeitados este mês.</p>
        <?php if ($report['reject_reasons'] === []): ?>
            <p class="mt-3 text-sm text-text-secondary">Nenhuma rejeição neste mês.</p>
        <?php else: ?>
            <?php $maxReasonTotal = max(array_map(static fn ($r) => (int) $r['total'], $report['reject_reasons'])); ?>
            <ul class="mt-3 space-y-3">
                <?php foreach ($report['reject_reasons'] as $r): ?>
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text-primary"><?= View::e(ArticleReviewService::REJECT_REASONS[$r['reason']] ?? $r['reason']) ?></span>
                            <span class="font-mono text-text-secondary"><?= (int) $r['total'] ?></span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-border">
                            <div class="h-full rounded-full bg-warning" style="width: <?= $maxReasonTotal > 0 ? round((int) $r['total'] / $maxReasonTotal * 100) : 0 ?>%"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
