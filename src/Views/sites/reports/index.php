<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $report */
/** @var string $monthName */
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
