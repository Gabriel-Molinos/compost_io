<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var array<string, mixed> $rule */
/** @var string $action */
/** @var array<string, string> $errors */

$activeTab = 'rules';
require __DIR__ . '/../_tabs.php';

$isEdit = !empty($rule['id']);
$type = $rule['type'] ?? 'INTEREST';
$intensity = (int) ($rule['intensity'] ?? 3);
$intensityLabels = ['', 'muito baixa', 'baixa', 'média', 'alta', 'muito alta'];
?>
<a href="/sites/<?= View::e($site['id']) ?>/rules" class="text-sm text-text-secondary hover:text-text-primary">
    ← Interesses
</a>
<h2 class="font-display mt-2 text-lg font-semibold text-text-primary"><?= $isEdit ? 'Editar regra' : 'Nova regra' ?></h2>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-5 max-w-xl" novalidate>
    <?= Csrf::field() ?>

    <section class="rounded-lg border border-border bg-surface p-5">
        <fieldset>
            <legend class="text-sm font-medium text-text-secondary">Tipo</legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary has-[:checked]:border-cyan has-[:checked]:text-cyan has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                    <input type="radio" name="type" value="INTEREST" <?= $type === 'INTEREST' ? 'checked' : '' ?> class="sr-only">
                    Interesse
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary has-[:checked]:border-warning has-[:checked]:text-warning has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                    <input type="radio" name="type" value="NON_INTEREST" <?= $type === 'NON_INTEREST' ? 'checked' : '' ?> class="sr-only">
                    Não-interesse
                </label>
            </div>
            <?php if (isset($errors['type'])): ?>
                <p class="mt-1 text-sm text-danger"><?= View::e($errors['type']) ?></p>
            <?php endif; ?>
        </fieldset>

        <div class="mt-5">
            <?= Form::text('description', 'Descrição', $rule, $errors, required: true) ?>
        </div>

        <fieldset class="mt-5">
            <legend class="text-sm font-medium text-text-secondary">
                Intensidade <span class="text-danger" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2 grid grid-cols-5 gap-2">
                <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-md border border-border bg-surface-2 py-2 text-text-primary hover:border-border-strong has-[:checked]:border-cyan has-[:checked]:bg-cyan/10 has-[:checked]:text-cyan has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                        <input type="radio" name="intensity" value="<?= $n ?>" <?= $intensity === $n ? 'checked' : '' ?> class="sr-only">
                        <span class="font-mono text-base font-semibold"><?= $n ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-xs font-medium text-text-secondary"><?= $intensityLabels[$intensity] ?></p>
            <?php if (isset($errors['intensity'])): ?>
                <p class="mt-1 text-sm text-danger"><?= View::e($errors['intensity']) ?></p>
            <?php endif; ?>
        </fieldset>
    </section>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Adicionar' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/rules"
           class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">Cancelar</a>
    </div>
</form>

<script>
    // Progressive enhancement: a legenda da intensidade ("média", "alta"...)
    // atualiza ao trocar o radio, sem precisar recarregar a página.
    (function () {
        var labels = <?= json_encode($intensityLabels, JSON_UNESCAPED_UNICODE) ?>;
        var radios = document.querySelectorAll('input[name="intensity"]');
        var out = document.querySelector('fieldset:last-of-type p.text-text-muted');
        if (!radios.length || !out) return;
        radios.forEach(function (r) {
            r.addEventListener('change', function () {
                out.textContent = labels[Number(r.value)] || '';
            });
        });
    })();
</script>
