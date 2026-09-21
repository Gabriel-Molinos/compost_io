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
$intensityLabels = ['', 'muito baixa', 'baixa', 'média', 'alta', 'muito alta'];

// 5 pontinhos, preenchidos até a intensidade — mais rápido de ler que "3/5" em texto.
$dots = static function (int $intensity, string $fillClass): string {
    $html = '<span class="flex items-center gap-1" role="img" aria-label="Intensidade ' . $intensity . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="h-2 w-2 rounded-full ' . ($i <= $intensity ? $fillClass : 'bg-border-strong') . '"></span>';
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

// Um painel (Interesses ou Não-interesses) por dentro — pra não repetir o markup 2x com tons/textos
// diferentes. Imprime direto (mesma técnica de closure que já existia aqui antes do redesign).
$renderPanel = static function (array $items, string $emptyIcon, string $emptyText, string $fillClass, array $labels)
    use ($base, $dots, $editIcon, $deleteIcon): void {
    if ($items === []) {
        echo '<div class="mt-4 rounded-xl border border-dashed border-border-strong p-6 text-center">'
            . '<span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-border/40 text-text-muted [&>svg]:h-5 [&>svg]:w-5">' . $emptyIcon . '</span>'
            . '<p class="mt-3 text-sm text-text-secondary">' . View::e($emptyText) . '</p></div>';
        return;
    }
    echo '<ul class="mt-4 space-y-2.5">';
    foreach ($items as $i => $rule) {
        $intensity = (int) $rule['intensity'];
        echo '<li class="hover-card flex items-center justify-between gap-3 rounded-xl border border-border bg-surface-2/40 px-4 py-3"'
            . ' style="animation: fade-in-up 240ms ease backwards; animation-delay: ' . min($i, 10) * 40 . 'ms">';
        echo '<div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-text-primary">' . View::e($rule['description']) . '</p>';
        echo '<div class="mt-1.5 flex items-center gap-2">' . $dots($intensity, $fillClass)
            . '<span class="text-[11px] text-text-muted">' . View::e($labels[$intensity] ?? '') . '</span></div></div>';
        echo '<div class="flex shrink-0 items-center gap-1">';
        echo '<a href="' . $base . '/' . View::e($rule['id']) . '/edit" title="Editar" aria-label="Editar"'
           . ' class="rounded-full p-2 text-text-muted transition-colors hover:bg-surface hover:text-cyan">' . $editIcon . '</a>';
        echo '<form method="post" action="' . $base . '/' . View::e($rule['id']) . '/delete" '
           . 'data-confirm="Remover esta regra?">' . Csrf::field()
           . '<button type="submit" title="Remover" aria-label="Remover"'
           . ' class="rounded-full p-2 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">' . $deleteIcon . '</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
};

$totalInterest = count($grouped['INTEREST']);
$totalNonInterest = count($grouped['NON_INTEREST']);
?>
<div class="flex items-center gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('rules') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Interesses e não-interesses</h1>
        <p class="mt-1 text-sm text-text-secondary">
            Guiam a IA na hora de escolher pauta — cada regra tem intensidade de 1 (leve) a 5 (forte).
        </p>
    </div>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-2">
    <div class="article-card article-card--cyan rounded-xl" style="--card-enter: 0ms">
        <div class="flex items-center gap-3 p-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan/15 text-cyan"><?= Icon::nav('rules') ?></span>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Interesses</p>
                <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalInterest ?></p>
            </div>
        </div>
    </div>
    <div class="article-card article-card--warning rounded-xl" style="--card-enter: 50ms">
        <div class="flex items-center gap-3 p-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"><?= Icon::nav('rules') ?></span>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Não-interesses</p>
                <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalNonInterest ?></p>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-2" data-tour="rules-panels">
    <section class="article-card article-card--cyan rounded-2xl" aria-labelledby="h-interesses" style="--card-enter: 120ms">
        <span class="article-card-fx" aria-hidden="true"></span>
        <span class="article-card-tags">
            <span class="article-card-tag article-card-tag--cyan"><?= $totalInterest ?> regra<?= $totalInterest === 1 ? '' : 's' ?></span>
        </span>
        <div class="p-5 pt-7">
            <div class="flex items-center justify-between gap-3">
                <h2 id="h-interesses" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('rules') ?></span>
                    Interesses
                </h2>
                <a href="<?= $base ?>/new?type=INTEREST" class="btn btn-primary relative z-10 px-3 py-1.5 text-sm">
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
                    Adicionar
                </a>
            </div>
            <?php $renderPanel($grouped['INTEREST'], Icon::nav('rules'), 'Nenhum interesse definido — a IA escolhe pauta livremente.', 'bg-cyan', $intensityLabels); ?>
        </div>
    </section>

    <section class="article-card article-card--warning rounded-2xl" aria-labelledby="h-nao" style="--card-enter: 170ms">
        <span class="article-card-fx" aria-hidden="true"></span>
        <span class="article-card-tags">
            <span class="article-card-tag article-card-tag--warning"><?= $totalNonInterest ?> regra<?= $totalNonInterest === 1 ? '' : 's' ?></span>
        </span>
        <div class="p-5 pt-7">
            <div class="flex items-center justify-between gap-3">
                <h2 id="h-nao" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('rules') ?></span>
                    Não-interesses
                </h2>
                <a href="<?= $base ?>/new?type=NON_INTEREST" class="btn btn-secondary relative z-10 px-3 py-1.5 text-sm">
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
                    Adicionar
                </a>
            </div>
            <?php $renderPanel($grouped['NON_INTEREST'], Icon::nav('rules'), 'Nenhum não-interesse definido.', 'bg-warning', $intensityLabels); ?>
        </div>
    </section>
</div>
