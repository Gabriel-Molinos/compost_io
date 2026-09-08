<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var array<string, mixed> $site */
/** @var array{INTEREST: list<array<string,mixed>>, NON_INTEREST: list<array<string,mixed>>} $grouped */

$activeTab = 'rules';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/rules';

// 5 pontinhos, preenchidos até a intensidade — mais rápido de ler que "3/5" em texto.
$dots = static function (int $intensity, string $fillClass): string {
    $html = '<span class="flex items-center gap-1" role="img" aria-label="Intensidade ' . $intensity . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="h-1.5 w-1.5 rounded-full ' . ($i <= $intensity ? $fillClass : 'bg-border') . '"></span>';
    }
    return $html . '</span>';
};

$editIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/>'
    . '<path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';

$renderPanel = static function (array $items, string $type, string $emptyText, string $fillClass) use ($base, $dots, $editIcon, $deleteIcon): void {
    if ($items === []) {
        echo '<p class="mt-4 text-sm text-text-secondary">' . View::e($emptyText) . '</p>';
        return;
    }
    echo '<ul class="mt-4 space-y-2">';
    foreach ($items as $rule) {
        echo '<li class="flex items-center justify-between gap-3 rounded-md border border-border bg-surface-2/40 px-3 py-2.5">';
        echo '<div class="min-w-0 flex-1"><p class="truncate text-sm text-text-primary">' . View::e($rule['description']) . '</p>';
        echo '<div class="mt-1.5">' . $dots((int) $rule['intensity'], $fillClass) . '</div></div>';
        echo '<div class="flex shrink-0 items-center gap-1">';
        echo '<a href="' . $base . '/' . View::e($rule['id']) . '/edit" title="Editar" aria-label="Editar"'
           . ' class="rounded-md p-1.5 text-text-muted transition-colors hover:bg-surface hover:text-cyan">' . $editIcon . '</a>';
        echo '<form method="post" action="' . $base . '/' . View::e($rule['id']) . '/delete" '
           . 'data-confirm="Remover esta regra?">' . Csrf::field()
           . '<button type="submit" title="Remover" aria-label="Remover"'
           . ' class="rounded-md p-1.5 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">' . $deleteIcon . '</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
};

$totalInterest = count($grouped['INTEREST']);
$totalNonInterest = count($grouped['NON_INTEREST']);
?>
<div>
    <h2 class="font-display text-lg font-semibold text-text-primary">Interesses e não-interesses</h2>
    <p class="mt-1 text-sm text-text-secondary">
        Guiam a IA na hora de escolher pauta — cada regra tem intensidade de 1 (leve) a 5 (forte).
    </p>
</div>

<dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border">
    <div class="bg-surface p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Interesses</dt>
        <dd class="mt-1 font-mono text-2xl font-semibold text-cyan"><?= $totalInterest ?></dd>
    </div>
    <div class="bg-surface p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Não-interesses</dt>
        <dd class="mt-1 font-mono text-2xl font-semibold text-warning"><?= $totalNonInterest ?></dd>
    </div>
</dl>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <section class="rounded-lg border border-border bg-surface p-5" aria-labelledby="h-interesses">
        <div class="flex items-center justify-between gap-3">
            <h3 id="h-interesses" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('rules') ?></span>
                Interesses
            </h3>
            <a href="<?= $base ?>/new?type=INTEREST"
               class="rounded-md bg-cyan px-3 py-1.5 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
                Adicionar
            </a>
        </div>
        <?php $renderPanel($grouped['INTEREST'], 'INTEREST', 'Nenhum interesse definido.', 'bg-cyan'); ?>
    </section>

    <section class="rounded-lg border border-border bg-surface p-5" aria-labelledby="h-nao">
        <div class="flex items-center justify-between gap-3">
            <h3 id="h-nao" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('rules') ?></span>
                Não-interesses
            </h3>
            <a href="<?= $base ?>/new?type=NON_INTEREST"
               class="rounded-md border border-border px-3 py-1.5 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
                Adicionar
            </a>
        </div>
        <?php $renderPanel($grouped['NON_INTEREST'], 'NON_INTEREST', 'Nenhum não-interesse definido.', 'bg-warning'); ?>
    </section>
</div>
