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
?>
<a href="/sites/<?= View::e($site['id']) ?>/rules" class="text-sm text-text-secondary hover:text-text-primary">
    ← Interesses
</a>
<h2 class="mt-2 text-lg font-semibold text-text-primary"><?= $isEdit ? 'Editar regra' : 'Nova regra' ?></h2>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>

    <fieldset>
        <legend class="text-sm font-medium text-text-secondary">Tipo</legend>
        <div class="mt-2 flex gap-4">
            <label class="flex items-center gap-2 text-sm text-text-primary">
                <input type="radio" name="type" value="INTEREST" <?= $type === 'INTEREST' ? 'checked' : '' ?>
                       class="text-cyan focus:ring-cyan"> Interesse
            </label>
            <label class="flex items-center gap-2 text-sm text-text-primary">
                <input type="radio" name="type" value="NON_INTEREST" <?= $type === 'NON_INTEREST' ? 'checked' : '' ?>
                       class="text-cyan focus:ring-cyan"> Não-interesse
            </label>
        </div>
        <?php if (isset($errors['type'])): ?>
            <p class="mt-1 text-sm text-danger"><?= View::e($errors['type']) ?></p>
        <?php endif; ?>
    </fieldset>

    <?= Form::text('description', 'Descrição', $rule, $errors, required: true) ?>

    <div>
        <label for="f_intensity" class="block text-sm font-medium text-text-secondary">
            Intensidade <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <select id="f_intensity" name="intensity"
                class="mt-1 w-full rounded-md border <?= isset($errors['intensity']) ? 'border-danger' : 'border-border focus:border-cyan' ?> bg-surface-2 px-3 py-2 text-text-primary focus:outline-none">
            <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                <option value="<?= $n ?>" <?= $intensity === $n ? 'selected' : '' ?>>
                    <?= $n ?> — <?= ['', 'muito baixa', 'baixa', 'média', 'alta', 'muito alta'][$n] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['intensity'])): ?>
            <p class="mt-1 text-sm text-danger"><?= View::e($errors['intensity']) ?></p>
        <?php endif; ?>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 font-semibold text-[#050B0F] hover:bg-cyan-light">
            <?= $isEdit ? 'Salvar' : 'Adicionar' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/rules"
           class="rounded-md border border-border px-4 py-2 text-text-secondary hover:text-text-primary">Cancelar</a>
    </div>
</form>
