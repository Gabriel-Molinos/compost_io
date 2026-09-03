<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $goals */

$activeTab = 'goals';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/goals';
?>
<div class="flex items-center justify-between">
    <h2 class="font-display text-lg font-semibold text-text-primary">Metas editoriais</h2>
    <a href="<?= $base ?>/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Nova meta
    </a>
</div>

<p class="mt-2 text-sm text-text-secondary">
    Cada meta cobre um período (mês) e define quantos artigos produzir no total e por categoria.
</p>

<?php if ($goals === []): ?>
    <p class="mt-6 text-text-secondary">Nenhuma meta cadastrada para este site.</p>
<?php else: ?>
    <ul class="mt-6 divide-y divide-border rounded-lg border border-border">
        <?php foreach ($goals as $goal): ?>
            <li class="flex items-start justify-between gap-4 px-4 py-3">
                <div>
                    <p class="font-mono text-text-primary"><?= View::e($goal['period']) ?></p>
                    <p class="mt-0.5 text-xs text-text-muted">
                        <?= View::e($goal['total_articles']) ?> artigos ·
                        <?= View::e($goal['allocated']) ?> distribuídos por categoria
                    </p>
                    <?php if (!empty($goal['general_guidelines'])): ?>
                        <p class="mt-1 text-sm text-text-muted"><?= View::e($goal['general_guidelines']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-sm">
                    <a href="<?= $base ?>/<?= View::e($goal['id']) ?>/edit"
                       class="text-cyan hover:text-cyan-bright">Editar</a>
                    <form method="post" action="<?= $base ?>/<?= View::e($goal['id']) ?>/delete"
                          onsubmit="return confirm('Remover a meta de <?= View::e($goal['period']) ?>?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="text-text-muted hover:text-danger">Remover</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
