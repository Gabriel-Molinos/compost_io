<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var array<string, mixed> $goal */
/** @var array<int, int> $targets */
/** @var list<array<string, mixed>> $categories */
/** @var string $action */
/** @var array<string, string> $errors */

$activeTab = 'goals';
require __DIR__ . '/../_tabs.php';

$isEdit = !empty($goal['id']);
?>
<a href="/sites/<?= View::e($site['id']) ?>/goals" class="text-sm text-text-secondary hover:text-text-primary">
    ← Metas
</a>
<h2 class="font-display mt-2 text-lg font-semibold text-text-primary"><?= $isEdit ? 'Editar meta' : 'Nova meta' ?></h2>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>

    <?= Form::text('period', 'Período (AAAA-MM)', $goal, $errors, type: 'month', required: true) ?>
    <?= Form::text('total_articles', 'Total de artigos', $goal, $errors, type: 'number', required: true) ?>
    <?= Form::textarea('general_guidelines', 'Diretrizes gerais', $goal, $errors, rows: 4) ?>
    <p class="text-xs text-text-muted">
        As diretrizes gerais valem para todos os artigos do período (ex.: “priorizar pautas de atualidade”).
    </p>

    <fieldset>
        <legend class="text-sm font-medium text-text-secondary">Artigos por categoria</legend>
        <?php if ($categories === []): ?>
            <p class="mt-2 text-sm text-text-muted">
                Este site ainda não tem categorias. Cadastre categorias para distribuir a meta.
            </p>
        <?php else: ?>
            <?php if (isset($errors['targets'])): ?>
                <p class="mt-1 text-sm text-danger"><?= View::e($errors['targets']) ?></p>
            <?php endif; ?>
            <div class="mt-2 divide-y divide-border rounded-lg border border-border bg-surface">
                <?php foreach ($categories as $category): ?>
                    <?php $cid = (int) $category['id']; ?>
                    <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                        <label for="f_target_<?= $cid ?>" class="text-sm text-text-primary">
                            <?= View::e($category['name']) ?>
                        </label>
                        <input type="number" min="0" id="f_target_<?= $cid ?>"
                               name="targets[<?= $cid ?>]"
                               value="<?= View::e((string) ($targets[$cid] ?? 0)) ?>"
                               class="w-20 rounded-md border border-border bg-surface-2 px-2 py-1 text-right text-text-primary focus:border-cyan focus:outline-none">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-1 text-xs text-text-muted">A soma não pode passar do total de artigos.</p>
        <?php endif; ?>
    </fieldset>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar meta' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/goals"
           class="rounded-md border border-border px-4 py-2 text-text-secondary hover:text-text-primary">Cancelar</a>
    </div>
</form>
