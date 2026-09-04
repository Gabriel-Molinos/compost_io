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

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-5 max-w-xl" novalidate>
    <?= Csrf::field() ?>

    <section class="rounded-lg border border-border bg-surface p-5">
        <h3 class="font-display text-base font-semibold text-text-primary">Período e volume</h3>
        <div class="mt-4 grid grid-cols-2 gap-4">
            <?= Form::text('period', 'Período', $goal, $errors, type: 'month', required: true) ?>
            <?= Form::text('total_articles', 'Total de artigos', $goal, $errors, type: 'number', required: true) ?>
        </div>

        <div class="mt-5">
            <?= Form::textarea('general_guidelines', 'Diretrizes gerais', $goal, $errors, rows: 4) ?>
            <p class="mt-1 text-xs text-text-muted">
                Valem para todos os artigos do período (ex.: "priorizar pautas de atualidade").
            </p>
        </div>
    </section>

    <fieldset class="mt-6 rounded-lg border border-border bg-surface p-5">
        <legend class="font-display text-base font-semibold text-text-primary">Distribuição por categoria</legend>
        <?php if ($categories === []): ?>
            <p class="mt-2 text-sm text-text-muted">
                Este site ainda não tem categorias. Cadastre categorias para distribuir a meta.
            </p>
        <?php else: ?>
            <?php if (isset($errors['targets'])): ?>
                <p class="mt-1 text-sm text-danger"><?= View::e($errors['targets']) ?></p>
            <?php endif; ?>
            <div class="mt-3 divide-y divide-border overflow-hidden rounded-md border border-border">
                <?php foreach ($categories as $category): ?>
                    <?php $cid = (int) $category['id']; ?>
                    <div class="flex items-center justify-between gap-4 bg-surface-2/40 px-4 py-2.5">
                        <label for="f_target_<?= $cid ?>" class="text-sm text-text-primary"><?= View::e($category['name']) ?></label>
                        <input type="number" min="0" id="f_target_<?= $cid ?>" name="targets[<?= $cid ?>]"
                               data-target-input
                               value="<?= View::e((string) ($targets[$cid] ?? 0)) ?>"
                               class="w-20 rounded-md border border-border bg-surface px-2 py-1 text-right text-text-primary focus:border-cyan focus:outline-none">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 flex items-center justify-between text-xs text-text-muted">
                <span>A soma não pode passar do total de artigos.</span>
                <span><span data-target-sum>0</span> distribuído(s) de <span data-total-articles>0</span></span>
            </p>
        <?php endif; ?>
    </fieldset>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar meta' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/goals"
           class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">Cancelar</a>
    </div>
</form>

<script>
    // Progressive enhancement: soma os alvos por categoria em tempo real,
    // pra ver na hora se passou do total (a validação de verdade continua
    // no servidor — isso é só feedback visual imediato).
    (function () {
        var inputs = document.querySelectorAll('[data-target-input]');
        var sumOut = document.querySelector('[data-target-sum]');
        var totalOut = document.querySelector('[data-total-articles]');
        var totalField = document.getElementById('f_total_articles');
        if (!inputs.length || !sumOut) return;

        var update = function () {
            var sum = 0;
            inputs.forEach(function (i) { sum += Number(i.value) || 0; });
            sumOut.textContent = String(sum);
            if (totalOut && totalField) totalOut.textContent = totalField.value || '0';
            sumOut.parentElement.classList.toggle('text-warning', totalField && sum > (Number(totalField.value) || 0));
        };
        inputs.forEach(function (i) { i.addEventListener('input', update); });
        if (totalField) totalField.addEventListener('input', update);
        update();
    })();
</script>
