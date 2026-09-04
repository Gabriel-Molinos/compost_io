<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var array<string, mixed> $site */
/** @var array{INTEREST: list<array<string,mixed>>, NON_INTEREST: list<array<string,mixed>>} $grouped */

$activeTab = 'rules';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/rules';

$renderList = static function (array $items, string $emptyText) use ($base): void {
    if ($items === []) {
        echo '<p class="mt-4 text-sm text-text-secondary">' . View::e($emptyText) . '</p>';
        return;
    }
    echo '<ul class="mt-4 divide-y divide-border rounded-lg border border-border bg-surface">';
    foreach ($items as $rule) {
        echo '<li class="flex items-center justify-between gap-4 px-4 py-3">';
        echo '<div><p class="text-text-primary">' . View::e($rule['description']) . '</p>';
        echo '<p class="mt-0.5 text-xs text-text-muted">Intensidade ' . View::e($rule['intensity']) . '/5</p></div>';
        echo '<div class="flex shrink-0 items-center gap-3 text-sm">';
        echo '<a href="' . $base . '/' . View::e($rule['id']) . '/edit" class="text-cyan hover:text-cyan-bright">Editar</a>';
        echo '<form method="post" action="' . $base . '/' . View::e($rule['id']) . '/delete" '
           . 'onsubmit="return confirm(\'Remover esta regra?\');">' . Csrf::field()
           . '<button type="submit" class="text-text-muted hover:text-danger">Remover</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
};
?>
<p class="text-sm text-text-secondary">
    Interesses e não-interesses guiam a IA. Cada regra tem intensidade de 1 a 5.
</p>

<section class="mt-8" aria-labelledby="h-interesses">
    <div class="flex items-center justify-between">
        <h2 id="h-interesses" class="font-display text-lg font-semibold text-text-primary">Interesses</h2>
        <a href="<?= $base ?>/new?type=INTEREST"
           class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Adicionar
        </a>
    </div>
    <?php $renderList($grouped['INTEREST'], 'Nenhum interesse definido.'); ?>
</section>

<section class="mt-8" aria-labelledby="h-nao">
    <div class="flex items-center justify-between">
        <h2 id="h-nao" class="font-display text-lg font-semibold text-text-primary">Não-interesses</h2>
        <a href="<?= $base ?>/new?type=NON_INTEREST"
           class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Adicionar
        </a>
    </div>
    <?php $renderList($grouped['NON_INTEREST'], 'Nenhum não-interesse definido.'); ?>
</section>
