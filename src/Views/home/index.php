<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $mySites */

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
<?php else: ?>
    <section aria-labelledby="meus-sites" class="mt-8">
        <h2 id="meus-sites" class="text-xs font-semibold uppercase tracking-wide text-text-muted">Meus sites</h2>
        <?php if ($mySites === []): ?>
            <p class="mt-3 text-sm text-text-secondary">
                Nenhum site vinculado ao seu usuário ainda. Peça ao administrador para te vincular a um site.
            </p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-border rounded-lg border border-border bg-surface">
                <?php foreach ($mySites as $site): ?>
                    <li>
                        <a href="/sites/<?= View::e($site['id']) ?>"
                           class="flex items-center justify-between px-4 py-3 hover:bg-surface">
                            <span class="text-text-primary"><?= View::e($site['name']) ?></span>
                            <span class="text-sm text-text-muted">
                                <?= View::e($site['niche'] ?? '—') ?>
                                <?php if ((int) $site['is_active'] !== 1): ?>· inativo<?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-sm text-text-muted">
                Abra um site para configurar categorias, interesses e metas editoriais.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>
