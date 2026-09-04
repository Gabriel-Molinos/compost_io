<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $user */
/** @var string $action */
/** @var array<string, string> $errors */
/** @var list<array<string, mixed>> $sites */
/** @var list<int> $assignedIds */
/** @var bool $requirePass */

$isEdit = !empty($user['id']);
$role = $user['role'] ?? 'REDATOR_CHEFE';
?>
<div class="flex items-center gap-3">
    <a href="/users" class="text-sm text-text-secondary hover:text-text-primary">← Usuários</a>
</div>
<h1 class="font-display mt-2 text-2xl font-bold text-text-primary"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>

    <?= Form::text('name', 'Nome', $user, $errors, required: true) ?>
    <?= Form::text('email', 'E-mail', $user, $errors, type: 'email', required: true) ?>

    <div>
        <label for="f_role" class="block text-sm font-medium text-text-secondary">
            Perfil <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <select id="f_role" name="role" data-role-select
                class="mt-1 w-full rounded-md border <?= isset($errors['role']) ? 'border-danger' : 'border-border focus:border-cyan' ?> bg-surface-2 px-3 py-2 text-text-primary focus:outline-none">
            <option value="REDATOR_CHEFE" <?= $role === 'REDATOR_CHEFE' ? 'selected' : '' ?>>Redator-Chefe</option>
            <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
        </select>
        <?php if (isset($errors['role'])): ?>
            <p class="mt-1 text-sm text-danger"><?= View::e($errors['role']) ?></p>
        <?php endif; ?>
    </div>

    <?= Form::text('password', $requirePass ? 'Senha' : 'Nova senha (deixe em branco para manter)', [], $errors, type: 'password', required: $requirePass) ?>

    <fieldset data-sites-fieldset class="<?= $role === 'ADMIN' ? 'hidden' : '' ?> rounded-md border border-border p-4">
        <legend class="px-1 text-sm font-medium text-text-secondary">Sites do Redator-Chefe</legend>
        <?php if ($sites === []): ?>
            <p class="text-sm text-text-muted">Nenhum site cadastrado. Crie um site antes de vincular.</p>
        <?php else: ?>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <?php foreach ($sites as $site): ?>
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="checkbox" name="site_ids[]" value="<?= View::e($site['id']) ?>"
                               <?= in_array((int) $site['id'], $assignedIds, true) ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">
                        <?= View::e($site['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="mt-2 text-xs text-text-muted">Administradores enxergam todos os sites — o vínculo não se aplica.</p>
    </fieldset>

    <?= Form::checkbox('is_active', 'Usuário ativo', $user, default: true) ?>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar usuário' ?>
        </button>
        <a href="/users" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Cancelar
        </a>
    </div>
</form>

<script>
    // Progressive enhancement: esconde os sites quando o perfil é ADMIN.
    (function () {
        var select = document.querySelector('[data-role-select]');
        var fieldset = document.querySelector('[data-sites-fieldset]');
        if (!select || !fieldset) return;
        select.addEventListener('change', function () {
            fieldset.classList.toggle('hidden', select.value === 'ADMIN');
        });
    })();
</script>
