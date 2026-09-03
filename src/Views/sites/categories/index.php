<?php

declare(strict_types=1);

use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */

$activeTab = 'categories';
require __DIR__ . '/../_tabs.php';
?>
<div class="flex items-center justify-between">
    <h2 class="font-display text-lg font-semibold text-text-primary">Categorias</h2>
    <a href="/sites/<?= View::e($site['id']) ?>/categories/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Nova categoria
    </a>
</div>

<?php if ($categories === []): ?>
    <p class="mt-6 text-text-secondary">Nenhuma categoria cadastrada para este site.</p>
<?php else: ?>
    <ul class="mt-6 divide-y divide-border rounded-lg border border-border">
        <?php foreach ($categories as $category): ?>
            <li class="flex items-start justify-between gap-4 px-4 py-3">
                <div>
                    <p class="text-text-primary"><?= View::e($category['name']) ?></p>
                    <?php if (!empty($category['guidelines'])): ?>
                        <p class="mt-1 text-sm text-text-muted"><?= View::e($category['guidelines']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <a href="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/edit"
                       class="text-cyan hover:text-cyan-bright">Editar</a>
                    <form method="post"
                          action="/sites/<?= View::e($site['id']) ?>/categories/<?= View::e($category['id']) ?>/delete"
                          onsubmit="return confirm('Remover esta categoria?');">
                        <?= \App\Support\Csrf::field() ?>
                        <button type="submit" class="text-text-muted hover:text-danger">Remover</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
