<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */

$activeTab = 'categories';
require __DIR__ . '/../_tabs.php';

$withoutGuidelines = array_filter($categories, static fn ($c) => empty($c['guidelines']));
$syncedWithWp = array_filter($categories, static fn ($c) => !empty($c['wordpress_category_id']));
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('categories') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Categorias</h1>
            <p class="mt-1 text-sm text-text-secondary">Organizam a produção e orientam a IA por tema.</p>
        </div>
    </div>
    <a href="/sites/<?= View::e($site['id']) ?>/categories/new" class="btn btn-primary px-4 py-2 text-sm">
        <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
        Nova categoria
    </a>
</div>

<?php if ($categories === []): ?>
    <div class="mt-8 rounded-2xl border border-dashed border-border-strong p-12 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan/10 text-cyan [&>svg]:h-7 [&>svg]:w-7"><?= Icon::nav('categories') ?></span>
        <p class="mt-4 font-display text-lg font-semibold text-text-primary">Nenhuma categoria ainda</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-text-secondary">
            Categorias organizam a produção por tema e podem orientar a IA com diretrizes próprias — o tom de cada uma.
        </p>
        <a href="/sites/<?= View::e($site['id']) ?>/categories/new" class="btn btn-primary mt-5 px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
            Criar a primeira
        </a>
    </div>
<?php else: ?>
    <?php
    $stats = [
        ['Total', count($categories), 'categories', 'cyan'],
        ['Vinculadas ao WordPress', count($syncedWithWp), 'wordpress', 'success'],
        ['Sem diretrizes', count($withoutGuidelines), 'alert', $withoutGuidelines !== [] ? 'warning' : 'muted'],
    ];
    ?>
    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <?php foreach ($stats as $i => [$label, $count, $icon, $tone]): ?>
            <div class="article-card <?= Labels::articleCardTone($tone) ?> rounded-xl" style="--card-enter: <?= $i * 50 ?>ms">
                <div class="flex items-center gap-3 p-4">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?= Labels::toneClasses($tone) ?>"><?= Icon::nav($icon) ?></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted"><?= View::e($label) ?></p>
                        <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $count ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($categories as $i => $category): ?>
            <?php
            $hasGuidelines = !empty($category['guidelines']);
            $synced = !empty($category['wordpress_category_id']);
            $tone = !$hasGuidelines ? 'warning' : ($synced ? 'success' : 'cyan');
            $count = (int) $category['article_count'];
            $cardStyle = sprintf('--card-enter: %dms; --card-phase: -%.2Fs', 150 + min($i, 8) * 40, ($i % 7) * 0.85);
            ?>
            <div class="article-card hover-card <?= Labels::articleCardTone($tone) ?> flex flex-col rounded-2xl" style="<?= $cardStyle ?>">
                <span class="article-card-fx" aria-hidden="true"></span>
                <span class="article-card-tags">
                    <?php if ($synced): ?>
                        <span class="article-card-tag article-card-tag--success"><?= Icon::nav('wordpress') ?>Sincronizada</span>
                    <?php endif; ?>
                    <?php if (!$hasGuidelines): ?>
                        <span class="article-card-tag article-card-tag--warning"><?= Icon::nav('alert') ?>Sem diretrizes</span>
                    <?php endif; ?>
                </span>

                <div class="flex flex-1 flex-col gap-3 p-5 pt-7">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md <?= Labels::toneClasses($tone) ?>"><?= Icon::nav('categories') ?></span>
                        <div class="min-w-0 flex-1">
                            <p class="article-card-title truncate font-display text-base font-semibold text-text-primary"><?= View::e($category['name']) ?></p>
                            <p class="mt-0.5 text-xs text-text-muted"><?= $count ?> artigo<?= $count === 1 ? '' : 's' ?></p>
                        </div>
                    </div>

                    <?php if ($hasGuidelines): ?>
                        <p class="line-clamp-3 text-sm text-text-secondary"><?= View::e($category['guidelines']) ?></p>
                    <?php else: ?>
                        <p class="text-sm italic text-text-muted">A IA vai decidir o tom sozinha nesta categoria.</p>
                    <?php endif; ?>

                    <div class="relative z-10 mt-auto flex items-center gap-2 pt-3">
                        <a href="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/edit"
                           class="btn btn-secondary inline-flex items-center gap-1.5 px-3 py-1.5 text-xs">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                            </svg>
                            Editar
                        </a>
                        <form method="post"
                              action="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/delete"
                              data-confirm="Remover esta categoria?">
                            <?= Csrf::field() ?>
                            <button type="submit"
                                    class="btn btn-secondary btn-hover-danger inline-flex items-center gap-1.5 px-3 py-1.5 text-xs">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 7h16" /><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                                    <path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" /><path d="M10 11v6" /><path d="M14 11v6" />
                                </svg>
                                Remover
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
