<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $sites */
/** @var int $sitesTotal */

$isAdmin = $user !== null && $user['role'] === 'ADMIN';

// Saudação por horário — mais humana que um "Olá" genérico em qualquer hora do dia.
$hour = (int) date('G');
$greeting = match (true) {
    $hour < 6   => 'Boa madrugada',
    $hour < 12  => 'Bom dia',
    $hour < 18  => 'Boa tarde',
    default     => 'Boa noite',
};
$firstName = $user !== null ? explode(' ', trim((string) $user['name']))[0] : null;
?>
<h1 class="font-display text-2xl font-bold text-text-primary">
    <?= View::e($greeting) ?><?= $firstName !== null ? ', ' . View::e($firstName) : '' ?>
</h1>
<p class="mt-2 text-sm text-text-secondary">
    <?php if ($user !== null): ?>
        Tudo certo por aqui. Você está conectado(a) como
        <span class="text-text-primary"><?= View::e(Labels::role($user['role'])) ?></span>.
    <?php endif; ?>
</p>

<?php if ($isAdmin): ?>
    <p class="mt-8 text-sm text-text-secondary">
        Por aqui você gerencia toda a operação: pode <span class="text-text-primary">adicionar usuários</span>
        e definir quem acessa cada site, ou <span class="text-text-primary">cadastrar um novo site</span>
        para começar a produzir conteúdo com IA.
    </p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <a href="/users" class="hover-card group flex items-start gap-4 rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span class="block text-base font-semibold text-text-primary">Usuários</span>
                    <span aria-hidden="true" class="text-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-cyan">→</span>
                </span>
                <span class="mt-1 block text-sm text-text-secondary">Administradores e Redatores-Chefe, com vínculo por site.</span>
            </span>
        </a>
        <a href="/sites" class="hover-card group flex items-start gap-4 rounded-lg border border-border bg-surface p-5 hover:border-cyan">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('sites') ?></span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                    <span class="block text-base font-semibold text-text-primary">Sites</span>
                    <span aria-hidden="true" class="text-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-cyan">→</span>
                </span>
                <span class="mt-1 block text-sm text-text-secondary">Cadastro dos sites WordPress geridos pela plataforma.</span>
            </span>
        </a>
    </div>
<?php endif; ?>

<?php if ($user !== null): ?>
    <section aria-labelledby="meus-sites"
             class="relative mt-8 overflow-hidden rounded-xl border border-border bg-surface-2/30 p-6">
        <!-- Painel com identidade própria (não é mais um card genérico igual aos atalhos
             acima): brilho radial fixo no canto, avatar em degradê de marca por site,
             barra de acento embaixo do card que acende no hover — em vez de borda inteira. -->
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-cyan/10 blur-3xl"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <h2 id="meus-sites" class="font-display text-lg font-semibold text-text-primary">
                <?= $isAdmin ? 'Sites' : 'Meus sites' ?>
            </h2>
            <?php if ($sitesTotal > count($sites)): ?>
                <a href="/sites" class="text-sm text-cyan hover:text-cyan-bright">Ver todos (<?= $sitesTotal ?>) →</a>
            <?php endif; ?>
        </div>

        <?php if ($sites === []): ?>
            <p class="relative mt-3 text-sm text-text-secondary">
                <?= $isAdmin
                    ? 'Nenhum site cadastrado ainda.'
                    : 'Nenhum site vinculado ao seu usuário ainda. Peça ao administrador para te vincular a um site.' ?>
            </p>
        <?php else: ?>
            <div class="relative mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($sites as $site): ?>
                    <?php $active = (int) $site['is_active'] === 1; ?>
                    <a href="/sites/<?= View::e($site['id']) ?>"
                       class="group flex flex-col gap-3 rounded-lg border border-border bg-surface p-4 pb-3
                              transition-colors hover:border-border-strong">
                        <span class="flex items-start justify-between gap-2">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-grad
                                         font-display text-base font-bold text-text-primary shadow-[0_0_0_1px_rgba(0,208,240,.25)]">
                                <?= View::e(mb_strtoupper(mb_substr((string) $site['name'], 0, 1))) ?>
                            </span>
                            <span class="flex items-center gap-1.5 text-xs text-text-muted" title="<?= $active ? 'Ativo' : 'Inativo' ?>">
                                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full <?= $active ? 'bg-success status-dot' : 'bg-text-muted' ?>"></span>
                                <?= $active ? 'ativo' : 'inativo' ?>
                            </span>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate font-display text-base font-semibold text-text-primary">
                                <?= View::e($site['name']) ?>
                            </span>
                            <span class="mt-1 inline-block max-w-full truncate rounded-full bg-surface-2 px-2 py-0.5 text-xs text-text-secondary">
                                <?= View::e($site['niche'] ?? 'Sem nicho definido') ?>
                            </span>
                        </span>
                        <span class="-mx-4 -mb-3 mt-1 h-0.5 rounded-b-lg bg-border transition-colors group-hover:bg-cyan"></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!$isAdmin): ?>
                <p class="relative mt-4 text-sm text-text-muted">
                    Abra um site para configurar categorias, interesses e metas editoriais.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
