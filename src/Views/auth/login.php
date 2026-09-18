<?php

declare(strict_types=1);

use App\Config\Env;
use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var string $email */
/** @var string|null $error */
/** @var string|null $flash */

$describedBy = $error !== null ? 'login-error' : null;
?>
<h1 class="font-display text-xl font-bold text-text-primary">Entrar</h1>
<p class="mt-1 text-sm text-text-secondary">Acesse com seu e-mail e senha.</p>

<?php if ($flash !== null): ?>
    <p role="status" class="mt-4 flex items-center gap-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan" aria-hidden="true"></span>
        <?= View::e($flash) ?>
    </p>
<?php endif; ?>

<?php if ($error !== null): ?>
    <p id="login-error" role="alert"
       class="mt-4 flex items-start gap-2 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        <span class="mt-0.5 shrink-0" aria-hidden="true"><?= Icon::nav('alert') ?></span>
        <span><?= View::e($error) ?></span>
    </p>
<?php endif; ?>

<form method="post" action="/login" class="mt-6 space-y-4" novalidate>
    <?= Csrf::field() ?>

    <div>
        <label for="email" class="sr-only">E-mail</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-text-muted"><?= Icon::nav('mail') ?></span>
            <input type="email" id="email" name="email" required autocomplete="username" autofocus
                   placeholder="E-mail" value="<?= View::e($email) ?>"
                   <?php if ($describedBy): ?>aria-describedby="<?= $describedBy ?>" aria-invalid="true"<?php endif; ?>
                   class="w-full rounded-full border <?= $error !== null ? 'border-danger' : 'border-border focus:border-cyan' ?> bg-[#0A3247] py-3 pl-11 pr-4 text-text-primary
                          placeholder:text-text-muted focus:outline-none">
        </div>
    </div>

    <div>
        <label for="password" class="sr-only">Senha</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-text-muted"><?= Icon::nav('lock') ?></span>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   placeholder="Senha"
                   <?php if ($describedBy): ?>aria-describedby="<?= $describedBy ?>" aria-invalid="true"<?php endif; ?>
                   class="w-full rounded-full border <?= $error !== null ? 'border-danger' : 'border-border focus:border-cyan' ?> bg-[#0A3247] py-3 pl-11 pr-11 text-text-primary
                          focus:outline-none">
            <button type="button" id="toggle-password" aria-label="Mostrar senha" aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex items-center px-4 text-text-muted hover:text-text-primary">
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
            class="btn btn-primary w-full rounded-full px-4 py-3 text-sm font-bold">
        Entrar
    </button>
</form>

<div class="mt-6 flex items-center gap-3 text-xs text-text-muted">
    <span class="h-px flex-1 bg-border"></span>
    <span>ou continue com</span>
    <span class="h-px flex-1 bg-border"></span>
</div>

<?php
// data-login_uri PRECISA ser absoluta (com esquema/host) — achado real
// 2026-09-15: com só "/callback.php" (relativo), o Google devolvia
// "Erro 400: redirect_uri_mismatch" mesmo com a URL certa cadastrada em
// "URIs de redirecionamento autorizados" no Cloud Console, porque o GIS
// não consegue casar um caminho relativo contra o que está lá.
$loginUri = rtrim((string) Env::get('APP_URL', ''), '/') . '/oauth/callback';
?>
<div id="g_id_onload"
     data-client_id="<?= View::e(Env::get('GOOGLE_CLIENT_ID', '')) ?>"
     data-login_uri="<?= View::e($loginUri) ?>"
     data-ux_mode="redirect">
</div>

<?php
// Botão com a cara da COMPOST em vez do widget padrão do Google (achado
// real 2026-09-15, pedido explícito de refazer o visual). O Google não
// deixa customizar o botão renderizado por ele nem disparar o clique dele
// via JS — a técnica aqui (documentada informalmente, usada por várias
// apps) é sobrepor o botão de verdade do Google, invisível mas clicável,
// por cima de um botão "de mentirinha" com a nossa cara: quem recebe o
// clique de verdade é sempre o elemento do Google, então o fluxo
// (CSRF/redirect/verificação) continua idêntico, só a aparência muda.
// data-width tem que ser um pixel fixo (o Google não aceita "100%"), por
// isso o wrapper também é de largura fixa, do mesmo tamanho do card.
?>
<div class="group relative mx-auto mt-4 h-11 w-[320px]">
    <div class="g_id_signin absolute inset-0 z-10 overflow-hidden opacity-0"
         data-type="standard" data-size="large" data-width="320"></div>

    <div class="pointer-events-none absolute inset-0 flex items-center justify-center gap-2.5 rounded-full
                border border-border bg-[#0A3247] text-sm font-semibold text-text-primary transition-colors
                group-hover:border-cyan group-active:bg-surface-2">
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
            <path fill="#4285F4" d="M17.64 9.2045c0-.6381-.0573-1.2518-.1636-1.8409H9v3.4814h4.8436c-.2086 1.125-.8427 2.0782-1.7959 2.7164v2.2581h2.9086c1.7018-1.5668 2.6836-3.8741 2.6836-6.615z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.4673-.8064 5.9564-2.1805l-2.9086-2.2581c-.8064.54-1.8368.8586-3.0477.8586-2.3441 0-4.3282-1.5831-5.0359-3.7104H.9573v2.3318C2.4382 15.9832 5.4818 18 9 18z"/>
            <path fill="#FBBC05" d="M3.9641 10.71c-.18-.54-.2823-1.1168-.2823-1.71s.1023-1.17.2823-1.71V4.9582H.9573C.3477 6.1732 0 7.5477 0 9s.3477 2.8268.9573 4.0418L3.9641 10.71z"/>
            <path fill="#EA4335" d="M9 3.5795c1.3214 0 2.5077.4541 3.4405 1.346l2.5814-2.5814C13.4632.8918 11.426 0 9 0 5.4818 0 2.4382 2.0168.9573 4.9582L3.9641 7.29C4.6718 5.1627 6.6559 3.5795 9 3.5795z"/>
        </svg>
        Continuar com Google
    </div>
</div>

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
