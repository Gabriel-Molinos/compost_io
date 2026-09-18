<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Form;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed>|null $user */
/** @var string|null $error */
/** @var array<string, string> $nameErrors */
?>
<h1 class="font-display text-2xl font-bold text-text-primary">Meu perfil</h1>
<p class="mt-2 text-sm text-text-secondary">
    <?= View::e($user['name'] ?? '') ?> ·
    <span class="text-text-primary"><?= View::e(Labels::role($user['role'] ?? '')) ?></span>
</p>

<?php if ($error !== null): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        <?= View::e($error) ?>
    </p>
<?php endif; ?>

<section class="mt-6 max-w-sm rounded-lg border border-border bg-surface p-5">
    <h2 class="font-display text-base font-semibold text-text-primary">Nome</h2>
    <p class="mt-1 text-sm text-text-secondary">
        Como você aparece na sidebar e pra outros usuários da plataforma.
    </p>

    <form method="post" action="/profile/name" class="mt-4">
        <?= Csrf::field() ?>
        <?= Form::text('name', 'Nome', $user, $nameErrors, required: true) ?>
        <button type="submit" class="btn btn-primary mt-3 px-4 py-2 text-sm">
            Salvar nome
        </button>
    </form>
</section>

<section class="mt-6 max-w-sm rounded-lg border border-border bg-surface p-5">
    <h2 class="font-display text-base font-semibold text-text-primary">Foto de perfil</h2>
    <p class="mt-1 text-sm text-text-secondary">
        Aparece na sidebar, na tela de início e nas listas de usuários — visível pra quem
        gerencia a plataforma com você.
    </p>

    <div class="mt-4 flex items-center gap-4" data-avatar-scope>
        <span data-avatar-preview data-avatar-img-class="<?= View::e(Avatar::imgClass('h-16 w-16', 'rounded-full')) ?>">
            <?= Avatar::html($user['avatar_path'] ?? null, $user['name'] ?? '?', size: 'h-16 w-16', radius: 'rounded-full', textSize: 'text-xl') ?>
        </span>

        <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="flex-1">
            <?= Csrf::field() ?>
            <?= Form::file('avatar', 'Trocar foto') ?>
            <button type="submit" class="btn btn-primary mt-3 px-4 py-2 text-sm">
                Salvar foto
            </button>
        </form>
    </div>

    <?php if (!empty($user['avatar_path'])): ?>
        <form method="post" action="/profile/avatar" class="mt-4 border-t border-border pt-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="remove_avatar" value="1">
            <button type="submit" class="text-sm text-text-muted hover:text-danger">Remover foto atual</button>
        </form>
    <?php endif; ?>
</section>

<p class="mt-6 text-sm text-text-muted">
    E-mail e senha ficam com o administrador —
    <?php if (($user['role'] ?? null) === 'ADMIN'): ?>
        <a href="/users" class="text-cyan hover:text-cyan-bright">gerencie em Usuários</a>.
    <?php else: ?>
        peça pra ele ajustar se precisar mudar algo.
    <?php endif; ?>
</p>
