<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Form;
use App\Support\Icon;
use App\Support\Labels;
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
<a href="/users" class="text-sm text-text-secondary hover:text-text-primary">← Usuários</a>

<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary"><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>
            <p class="mt-1 text-sm text-text-secondary">
                <?= $isEdit ? 'Dados de acesso, permissões e sites vinculados.' : 'Cadastre um administrador ou redator-chefe e vincule aos sites certos.' ?>
            </p>
        </div>
    </div>
    <?php if ($isEdit): ?>
        <div class="flex items-center gap-2">
            <?= Labels::roleBadge((string) $role) ?>
            <?= Labels::activeBadge(!empty($user['is_active'])) ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-6 max-w-xl" novalidate>
    <?= Csrf::field() ?>

    <section class="rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Identificação</h2>

        <div class="mt-4 flex items-center gap-4">
            <?= Avatar::html($user['avatar_path'] ?? null, $user['name'] ?? '?', size: 'h-16 w-16', radius: 'rounded-full', textSize: 'text-xl') ?>
            <div class="flex-1">
                <?= Form::file('avatar', 'Foto de perfil', $errors['avatar'] ?? null) ?>
                <?php if ($isEdit && !empty($user['avatar_path'])): ?>
                    <label class="mt-2 flex items-center gap-2 text-sm text-text-secondary">
                        <input type="checkbox" name="remove_avatar" value="1"
                               class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">
                        Remover foto atual
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-5 space-y-5">
            <?= Form::text('name', 'Nome', $user, $errors, required: true) ?>
            <?= Form::text('email', 'E-mail', $user, $errors, type: 'email', required: true) ?>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Acesso</h2>

        <div class="mt-4 space-y-5">
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

            <?= Form::checkbox('is_active', 'Usuário ativo', $user, default: true) ?>
        </div>
    </section>

    <fieldset data-sites-fieldset class="mt-6 rounded-lg border border-border bg-surface p-5 <?= $role === 'ADMIN' ? 'hidden' : '' ?>">
        <legend class="font-display text-base font-semibold text-text-primary">Sites vinculados</legend>
        <p class="mt-1 text-sm text-text-secondary">Redator-Chefe só vê e produz nos sites marcados abaixo.</p>

        <?php if ($sites === []): ?>
            <p class="mt-3 text-sm text-text-muted">Nenhum site cadastrado. Crie um site antes de vincular.</p>
        <?php else: ?>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach ($sites as $site): ?>
                    <label class="flex items-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary">
                        <input type="checkbox" name="site_ids[]" value="<?= View::e($site['id']) ?>"
                               <?= in_array((int) $site['id'], $assignedIds, true) ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-border bg-surface text-cyan focus:ring-cyan">
                        <?= View::e($site['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar usuário' ?>
        </button>
        <a href="/users" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Cancelar
        </a>
    </div>
</form>

<script>
    // Progressive enhancement: esconde a seção de sites quando o perfil é ADMIN.
    (function () {
        var select = document.querySelector('[data-role-select]');
        var fieldset = document.querySelector('[data-sites-fieldset]');
        if (!select || !fieldset) return;
        select.addEventListener('change', function () {
            fieldset.classList.toggle('hidden', select.value === 'ADMIN');
        });
    })();
</script>
