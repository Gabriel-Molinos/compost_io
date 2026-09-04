<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var string $email */
/** @var string|null $error */
/** @var string|null $flash */

$describedBy = $error !== null ? 'login-error' : null;
?>
<h1 class="font-display text-xl font-bold text-text-primary">Entrar</h1>
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
        <div class="relative mt-1">
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   <?php if ($describedBy): ?>aria-describedby="<?= $describedBy ?>" aria-invalid="true"<?php endif; ?>
                   class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 pr-11 text-text-primary
                          focus:border-cyan focus:outline-none">
            <button type="button" id="toggle-password" aria-label="Mostrar senha" aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-text-muted hover:text-text-primary">
                <svg data-icon="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg data-icon="hide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        </div>
    </div>

    <button type="submit"
            class="w-full rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] transition-colors
                   hover:bg-cyan-bright focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
        Entrar
    </button>
</form>

<script>
    (function () {
        var btn = document.getElementById('toggle-password');
        var input = document.getElementById('password');
        if (!btn || !input) { return; }

        btn.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(reveal));
            btn.setAttribute('aria-label', reveal ? 'Ocultar senha' : 'Mostrar senha');
            btn.querySelector('[data-icon=show]').classList.toggle('hidden', reveal);
            btn.querySelector('[data-icon=hide]').classList.toggle('hidden', !reveal);
            input.focus();
        });
    })();
</script>
