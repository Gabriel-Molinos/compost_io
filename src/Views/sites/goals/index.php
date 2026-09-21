<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $goals */

$activeTab = 'goals';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/goals';
$currentPeriod = date('Y-m');

$meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$periodLabel = static function (string $period) use ($meses): string {
    [$y, $m] = explode('-', $period);
    return $meses[(int) $m] . ' ' . $y;
};

$editIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/>'
    . '<path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('goals') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Metas editoriais</h1>
            <p class="mt-1 text-sm text-text-secondary">Cada meta cobre um mês e define quantos artigos produzir, no total e por categoria.</p>
        </div>
    </div>
    <a href="<?= $base ?>/new" data-tour="new-goal" class="btn btn-primary px-4 py-2 text-sm">
        <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
        Nova meta
    </a>
</div>

<?php if ($goals === []): ?>
    <div class="mt-8 rounded-2xl border border-dashed border-border-strong p-12 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan/10 text-cyan [&>svg]:h-7 [&>svg]:w-7"><?= Icon::nav('goals') ?></span>
        <p class="mt-4 font-display text-lg font-semibold text-text-primary">Nenhuma meta ainda</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-text-secondary">
            Uma meta define quantos artigos produzir no mês, no total e por categoria — a IA se orienta por ela.
        </p>
        <a href="<?= $base ?>/new" class="btn btn-primary mt-5 px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
            Criar a primeira
        </a>
    </div>
<?php else: ?>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($goals as $i => $goal): ?>
            <?php
            $total = (int) $goal['total_articles'];
            $approved = (int) $goal['approved'];
            $percent = $total > 0 ? min(100, (int) round($approved / $total * 100)) : 0;
            $isCurrent = $goal['period'] === $currentPeriod;
            $allocated = (int) $goal['allocated'];
            $overAllocated = $allocated > $total;
            $done = $percent >= 100;
            $tone = $done ? 'success' : ($isCurrent ? 'cyan' : 'muted');
            $cardStyle = sprintf('--card-enter: %dms; --card-phase: -%.2Fs', min($i, 8) * 50, ($i % 7) * 0.85);
            ?>
            <div class="article-card hover-card <?= Labels::articleCardTone($tone) ?> flex flex-col rounded-2xl" style="<?= $cardStyle ?>">
                <span class="article-card-fx" aria-hidden="true"></span>
                <span class="article-card-tags">
                    <?php if ($isCurrent): ?>
                        <span class="article-card-tag article-card-tag--cyan"><span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>Mês atual</span>
                    <?php endif; ?>
                    <?php if ($done): ?>
                        <span class="article-card-tag article-card-tag--success"><?= Icon::nav('check') ?>Meta batida</span>
                    <?php endif; ?>
                    <?php if ($overAllocated): ?>
                        <span class="article-card-tag article-card-tag--warning"><?= Icon::nav('alert') ?>Passou do total</span>
                    <?php endif; ?>
                </span>

                <div class="flex flex-1 flex-col gap-4 p-5 pt-7">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md <?= Labels::toneClasses($tone) ?>"><?= Icon::nav('goals') ?></span>
                        <p class="article-card-title font-display text-base font-semibold text-text-primary"><?= View::e($periodLabel($goal['period'])) ?></p>
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-text-secondary"><?= $approved ?> de <?= $total ?> artigos aprovados</span>
                            <span class="font-mono font-semibold text-text-primary"><?= $percent ?>%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-border">
                            <div class="goal-bar-fill h-full rounded-full <?= $done ? 'bg-success' : 'bg-cyan' ?> shadow-[0_0_10px_-1px_rgba(0,208,240,.7)]"
                                 style="width: <?= $percent ?>%; --card-enter: <?= 150 + min($i, 8) * 50 ?>ms"></div>
                        </div>
                    </div>

                    <p class="article-card-fact text-xs <?= $overAllocated ? 'text-warning' : 'text-text-muted' ?>">
                        <span class="article-card-icon"><?= Icon::nav('categories') ?></span>
                        <?= $allocated ?> de <?= $total ?> distribuídos por categoria
                    </p>

                    <?php if (!empty($goal['general_guidelines'])): ?>
                        <p class="line-clamp-2 text-sm text-text-secondary"><?= View::e($goal['general_guidelines']) ?></p>
                    <?php endif; ?>

                    <div class="relative z-10 mt-auto flex items-center gap-1 border-t border-border/60 pt-3">
                        <a href="<?= $base ?>/<?= View::e($goal['id']) ?>/edit"
                           class="btn btn-secondary inline-flex items-center gap-1.5 px-3 py-1.5 text-xs">
                            <?= $editIcon ?> Editar
                        </a>
                        <form method="post" action="<?= $base ?>/<?= View::e($goal['id']) ?>/delete"
                              data-confirm="Remover a meta de <?= View::e($periodLabel($goal['period'])) ?>?">
                            <?= Csrf::field() ?>
                            <button type="submit"
                                    class="btn btn-secondary btn-hover-danger inline-flex items-center gap-1.5 px-3 py-1.5 text-xs">
                                <?= $deleteIcon ?> Remover
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
