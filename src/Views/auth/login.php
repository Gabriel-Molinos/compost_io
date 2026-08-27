<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var string $email */
/** @var string|null $error */
/** @var string|null $flash */

$describedBy = $error !== null ? 'login-error' : null;
?>
<h1 class="text-xl font-bold text-text-primary">Entrar</h1>
<p class="mt-1 text-sm text-text-secondary">Acesse com seu e-mail e senha.</p>

<?php if ($flash !== null): ?>
    <p role="status" class="mt-4 rounded-md border border-border bg-surface px-3 py-2 text-sm text-text-secondary">
        <?= View::e($flash) ?>
    </p>
<?php endif; ?>

<?php if ($error !== null): ?>
    <p id="login-error" role="alert"
       class="mt-4 flex items-start gap-2 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        <span aria-hidden="true">!</span>
        <span><?= View::e($error) ?></span>
    </p>
<?php endif; ?>

<form method="post" action="/login" class="mt-6 space-y-4" novalidate>
    <?= Csrf::field() ?>

    <div>
        <label for="email" class="block text-sm font-medium text-text-secondary">E-mail</label>
        <input type="email" id="email" name="email" required autocomplete="username" autofocus
               value="<?= View::e($email) ?>"
               <?php if ($describedBy): ?>aria-describedby="<?= $describedBy ?>" aria-invalid="true"<?php endif; ?>
               class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary
                      placeholder:text-text-muted focus:border-cyan focus:outline-none">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-text-secondary">Senha</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"
               <?php if ($describedBy): ?>aria-describedby="<?= $describedBy ?>" aria-invalid="true"<?php endif; ?>
               class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary
                      focus:border-cyan focus:outline-none">
    </div>

    <button type="submit"
            class="w-full rounded-md bg-cyan px-4 py-2 font-semibold text-[#050B0F] transition-colors
                   hover:bg-cyan-light focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
        Entrar
    </button>
</form>
