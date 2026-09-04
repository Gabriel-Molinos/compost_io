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
<div class="flex items-center justify-between">
    <div>
        <h2 class="font-display text-lg font-semibold text-text-primary">Categorias</h2>
        <p class="mt-1 text-sm text-text-secondary">Organizam a produção e orientam a IA por tema.</p>
    </div>
    <a href="/sites/<?= View::e($site['id']) ?>/categories/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Nova categoria
    </a>
</div>

<?php if ($categories === []): ?>
    <p class="mt-8 text-sm text-text-secondary">Nenhuma categoria cadastrada para este site.</p>
<?php else: ?>
    <dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-3">
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Total</dt>
            <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary"><?= count($categories) ?></dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Vinculadas ao WordPress</dt>
            <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary"><?= count($syncedWithWp) ?></dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Sem diretrizes</dt>
            <dd class="mt-1 font-mono text-2xl font-semibold <?= $withoutGuidelines !== [] ? 'text-warning' : 'text-text-primary' ?>">
                <?= count($withoutGuidelines) ?>
            </dd>
        </div>
    </dl>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($categories as $category): ?>
            <div class="flex flex-col gap-3 rounded-lg border border-border bg-surface p-4">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('categories') ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-base font-semibold text-text-primary"><?= View::e($category['name']) ?></p>
                        <p class="mt-0.5 text-xs text-text-muted">
                            <?= (int) $category['article_count'] ?> artigo<?= (int) $category['article_count'] === 1 ? '' : 's' ?>
                            <?php if (!empty($category['wordpress_category_id'])): ?>
                                · <span class="text-success">sincronizada com o WordPress</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($category['guidelines'])): ?>
                    <p class="line-clamp-3 text-sm text-text-secondary"><?= View::e($category['guidelines']) ?></p>
                <?php else: ?>
                    <p class="text-sm text-text-muted italic">Sem diretrizes — a IA vai decidir o tom sozinha nesta categoria.</p>
                <?php endif; ?>

                <div class="mt-auto flex items-center gap-2 border-t border-border pt-3">
                    <a href="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/edit"
                       class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:border-cyan hover:text-cyan">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                        Editar
                    </a>
                    <form method="post"
                          action="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/delete"
                          onsubmit="return confirm('Remover esta categoria?');">
                        <?= Csrf::field() ?>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-text-muted transition-colors hover:border-danger/50 hover:bg-danger/10 hover:text-danger">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16" /><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                                <path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13" /><path d="M10 11v6" /><path d="M14 11v6" />
                            </svg>
                            Remover
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
