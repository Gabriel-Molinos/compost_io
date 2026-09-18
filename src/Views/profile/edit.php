<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $user */
/** @var string|null $error erro do envio da foto */
/** @var array<string, string> $nameErrors */
/** @var list<array<string, mixed>> $sites Sites que o usuário enxerga (ADMIN: todos; Redator-Chefe: vinculados) */

$name = trim((string) ($user['name'] ?? ''));
$isAdmin = ($user['role'] ?? null) === 'ADMIN';
$hasAvatar = Avatar::hasImage($user['avatar_path'] ?? null);
$roleDesc = $isAdmin
    ? 'Acesso total: todos os sites, usuários e configurações.'
    : 'Vê e produz só nos sites vinculados a você.';
$sitesTotal = count($sites);
$avatarSize = 'h-44 w-44';
?>
<div data-avatar-scope>
    <?php // ── Cabeçalho: foto grande, nome, papel e resumo de acesso ── ?>
    <section class="article-card article-card--cyan hover-card rounded-3xl" style="--card-phase: -1.2s">
        <span class="article-card-fx" aria-hidden="true"></span>
        <span class="article-card-tags">
            <span class="article-card-tag article-card-tag--cyan"><?= View::e(Labels::role((string) ($user['role'] ?? ''))) ?></span>
            <span class="article-card-tag article-card-tag--success">
                <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>Você
            </span>
        </span>

        <div class="flex flex-col items-center gap-7 p-7 pt-9 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
                <span data-avatar-preview data-avatar-transition
                      data-avatar-img-class="<?= View::e(Avatar::imgClass($avatarSize, 'rounded-full')) ?>"
                      class="relative block rounded-full bg-void p-1.5 shadow-[0_0_0_2px_rgba(0,208,240,.55),0_0_44px_-6px_rgba(0,208,240,.6)]">
                    <?= Avatar::html($user['avatar_path'] ?? null, $name !== '' ? $name : '?', size: $avatarSize, radius: 'rounded-full', textSize: 'text-6xl') ?>
                </span>
                <?php // Câmera sobre a foto: abre o seletor de arquivo do cartão "Foto de perfil" logo abaixo. ?>
                <label for="f_avatar" title="Trocar foto"
                       class="btn btn-primary absolute -bottom-1 -right-1 h-11 w-11 cursor-pointer !rounded-full !p-0">
                    <span class="[&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('camera') ?></span>
                    <span class="sr-only">Trocar foto</span>
                </label>
            </div>

            <div class="min-w-0 flex-1 text-center sm:text-left">
                <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Meu perfil</p>
                <h1 class="mt-1 break-words font-display text-3xl font-bold text-text-primary sm:text-4xl"><?= View::e($name) ?></h1>
                <p class="mt-2 break-all font-mono text-sm text-text-secondary"><?= View::e($user['email'] ?? '') ?></p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5 sm:justify-start">
                    <span class="article-card-fact article-card-fact--chip">
                        <span class="article-card-icon"><?= Icon::nav($isAdmin ? 'shield' : 'production') ?></span>
                        <?= View::e(Labels::role((string) ($user['role'] ?? ''))) ?>
                    </span>
                    <span class="article-card-fact">
                        <span class="article-card-icon"><?= Icon::nav('sites') ?></span>
                        <?= $isAdmin ? 'Todos os ' . $sitesTotal . ' sites' : $sitesTotal . ($sitesTotal === 1 ? ' site vinculado' : ' sites vinculados') ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($error !== null): ?>
        <p role="alert" class="mt-5 rounded-xl border border-danger/40 bg-danger/10 px-4 py-2.5 text-sm text-danger">
            <?= View::e($error) ?>
        </p>
    <?php endif; ?>

    <div class="mt-7 grid gap-6 lg:grid-cols-2">
        <?php // ── Foto: escolher, ver ao vivo no cabeçalho, salvar ── ?>
        <section class="rounded-2xl border border-border bg-surface p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('camera') ?></span>
                <div>
                    <h2 class="font-display text-lg font-semibold text-text-primary">Foto de perfil</h2>
                    <p class="mt-0.5 text-sm text-text-secondary">Aparece na sidebar, na tela de início e nas listas de usuários.</p>
                </div>
            </div>

            <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="mt-5" data-photo-form>
                <?= Csrf::field() ?>
                <input type="file" id="f_avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="peer sr-only">
                <label for="f_avatar"
                       class="pick flex flex-col items-center gap-2 rounded-xl border-dashed px-4 py-7 text-center peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-cyan">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-cyan/15 text-cyan [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('camera') ?></span>
                    <span class="text-sm font-semibold text-text-primary" data-avatar-filename>Escolher uma foto</span>
                    <span class="text-xs text-text-muted">JPG, PNG, WebP ou GIF, até 5 MB.</span>
                </label>
                <p class="mt-3 text-xs text-text-muted">A foto nova aparece no cabeçalho ao lado antes de salvar.</p>
                <button type="submit" class="btn btn-primary mt-4 px-5 py-2 text-sm" data-photo-save>
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('check') ?></span>
                    Salvar foto
                </button>
            </form>

            <?php if ($hasAvatar): ?>
                <form method="post" action="/profile/avatar" class="mt-5 border-t border-border pt-4">
                    <?= Csrf::field() ?>
                    <button type="submit" name="remove_avatar" value="1" class="btn btn-secondary btn-hover-danger px-3 py-1.5 text-xs">Remover foto atual</button>
                </form>
            <?php endif; ?>
        </section>

        <?php // ── Nome ── ?>
        <section class="rounded-2xl border border-border bg-surface p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
                <div>
                    <h2 class="font-display text-lg font-semibold text-text-primary">Nome</h2>
                    <p class="mt-0.5 text-sm text-text-secondary">Como você aparece na sidebar e pra outros usuários da plataforma.</p>
                </div>
            </div>

            <form method="post" action="/profile/name" class="mt-5" data-name-form>
                <?= Csrf::field() ?>
                <div class="flex items-center justify-between gap-3">
                    <label for="f_name" class="text-sm font-semibold text-text-primary">Seu nome <span class="text-danger" aria-hidden="true">*</span></label>
                    <span class="font-mono text-xs text-text-muted"><span data-name-count><?= mb_strlen($name) ?></span> / 191</span>
                </div>
                <input type="text" id="f_name" name="name" value="<?= View::e($name) ?>" required maxlength="191"
                       <?= isset($nameErrors['name']) ? 'aria-invalid="true" aria-describedby="f_name_err"' : '' ?>
                       class="mt-3 w-full rounded-xl border bg-surface-2 px-4 py-3 font-display text-2xl font-bold text-text-primary placeholder:text-text-muted focus:outline-none <?= isset($nameErrors['name']) ? 'border-danger' : 'border-border focus:border-cyan' ?>">
                <?php if (isset($nameErrors['name'])): ?>
                    <p id="f_name_err" class="mt-1 text-sm text-danger"><?= View::e($nameErrors['name']) ?></p>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary mt-4 px-5 py-2 text-sm">
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('check') ?></span>
                    Salvar nome
                </button>
            </form>
        </section>
    </div>

    <?php // ── Acesso: papel, e-mail (só o admin altera) e sites ── ?>
    <section class="mt-6 rounded-2xl border border-border bg-surface p-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/10 text-cyan"><?= Icon::nav('shield') ?></span>
            <div>
                <h2 class="font-display text-lg font-semibold text-text-primary">Seu acesso</h2>
                <p class="mt-0.5 text-sm text-text-secondary">
                    <?= View::e($roleDesc) ?> E-mail e senha ficam com o administrador —
                    <?php if ($isAdmin): ?>
                        <a href="/users" class="text-cyan hover:text-cyan-bright">gerencie em Usuários</a>.
                    <?php else: ?>
                        peça pra ele ajustar se precisar mudar algo.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($sites === []): ?>
            <p class="mt-5 text-sm text-text-muted">Você não está vinculado a nenhum site ainda.</p>
        <?php else: ?>
            <p class="mt-5 text-[11px] font-bold uppercase tracking-widest text-text-muted"><?= $isAdmin ? 'Todos os sites' : 'Meus sites' ?></p>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($sites as $s): ?>
                    <?php $siteActive = (int) ($s['is_active'] ?? 1) === 1; ?>
                    <li>
                        <a href="/sites/<?= View::e($s['id']) ?>" class="pick flex items-center gap-3 rounded-xl p-3">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan/15 font-display text-sm font-bold text-cyan"><?= View::e(mb_strtoupper(mb_substr(trim((string) $s['name']), 0, 1))) ?></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-text-primary"><?= View::e($s['name']) ?></span>
                                <span class="mt-0.5 flex items-center gap-1.5 text-xs text-text-muted">
                                    <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full <?= $siteActive ? 'bg-success' : 'bg-text-muted' ?>"></span>
                                    <span class="truncate"><?= View::e(($s['niche'] ?? '') !== '' ? $s['niche'] : ($siteActive ? 'Ativo' : 'Inativo')) ?></span>
                                </span>
                            </span>
                            <span aria-hidden="true" class="text-text-muted [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<script>
    // Melhoria progressiva: mostra o arquivo escolhido, só habilita "Salvar foto" com um arquivo
    // e conta os caracteres do nome. A pré-visualização no cabeçalho é do avatar-preview.js.
    (function () {
        var file = document.getElementById('f_avatar');
        var label = document.querySelector('[data-avatar-filename]');
        var save = document.querySelector('[data-photo-save]');
        if (file && save) {
            save.disabled = true;
            var original = label ? label.textContent : '';
            file.addEventListener('change', function () {
                var chosen = file.files && file.files[0];
                save.disabled = !chosen;
                if (label) { label.textContent = chosen ? chosen.name : original; }
            });
        }
        var name = document.getElementById('f_name');
        var count = document.querySelector('[data-name-count]');
        if (name && count) {
            name.addEventListener('input', function () { count.textContent = String(name.value.length); });
        }
    })();
</script>
