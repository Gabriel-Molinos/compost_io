<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
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
<div class="flex items-center justify-between">
    <div>
        <h2 class="font-display text-lg font-semibold text-text-primary">Metas editoriais</h2>
        <p class="mt-1 text-sm text-text-secondary">Cada meta cobre um mês e define quantos artigos produzir, no total e por categoria.</p>
    </div>
    <a href="<?= $base ?>/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Nova meta
    </a>
</div>

<?php if ($goals === []): ?>
    <p class="mt-8 text-sm text-text-secondary">Nenhuma meta cadastrada para este site.</p>
<?php else: ?>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($goals as $goal): ?>
            <?php
            $total = (int) $goal['total_articles'];
            $approved = (int) $goal['approved'];
            $percent = $total > 0 ? min(100, (int) round($approved / $total * 100)) : 0;
            $isCurrent = $goal['period'] === $currentPeriod;
            $overAllocated = (int) $goal['allocated'] > $total;
            ?>
            <div class="flex flex-col gap-3 rounded-lg border <?= $isCurrent ? 'border-cyan/50' : 'border-border' ?> bg-surface p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('goals') ?></span>
                        <div>
                            <p class="font-display text-base font-semibold text-text-primary"><?= View::e($periodLabel($goal['period'])) ?></p>
                            <?php if ($isCurrent): ?>
                                <span class="text-xs font-medium text-cyan">Mês atual</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-text-secondary"><?= $approved ?> de <?= $total ?> artigos aprovados</span>
                        <span class="font-mono text-text-muted"><?= $percent ?>%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-border">
                        <div class="h-full rounded-full <?= $percent >= 100 ? 'bg-success' : 'bg-cyan' ?>" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>

                <p class="text-xs <?= $overAllocated ? 'text-warning' : 'text-text-muted' ?>">
                    <?= (int) $goal['allocated'] ?> de <?= $total ?> distribuídos por categoria
                    <?= $overAllocated ? ' — passou do total' : '' ?>
                </p>

                <?php if (!empty($goal['general_guidelines'])): ?>
                    <p class="line-clamp-2 text-sm text-text-secondary"><?= View::e($goal['general_guidelines']) ?></p>
                <?php endif; ?>

                <div class="mt-auto flex items-center gap-1 border-t border-border pt-3">
                    <a href="<?= $base ?>/<?= View::e($goal['id']) ?>/edit"
                       class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:border-cyan hover:text-cyan">
                        <?= $editIcon ?> Editar
                    </a>
                    <form method="post" action="<?= $base ?>/<?= View::e($goal['id']) ?>/delete"
                          onsubmit="return confirm('Remover a meta de <?= View::e($periodLabel($goal['period'])) ?>?');">
                        <?= Csrf::field() ?>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-text-muted transition-colors hover:border-danger/50 hover:bg-danger/10 hover:text-danger">
                            <?= $deleteIcon ?> Remover
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
