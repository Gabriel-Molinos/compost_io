<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $users */

$admins = array_filter($users, static fn ($u) => $u['role'] === 'ADMIN');
$active = array_filter($users, static fn ($u) => (int) $u['is_active'] === 1);
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('users') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Usuários</h1>
            <p class="mt-1 text-sm text-text-secondary">Quem tem acesso ao COMPOST e a quais sites.</p>
        </div>
    </div>
    <a href="/users/new"
       class="btn btn-primary px-4 py-2 text-sm">
        <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
        Novo usuário
    </a>
</div>

<?php
// Barra de estatística única — mesmo padrão de sites/reports/index.php
// (grid + gap-px + bg-border), em vez de repetir o texto em cada linha.
$stats = [
    ['Total', (string) count($users), null],
    ['Administradores', (string) count($admins), null],
    ['Redatores-Chefe', (string) (count($users) - count($admins)), null],
    ['Ativos', (string) count($active), null],
];
?>
<dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-4">
    <?php foreach ($stats as [$label, $value, $sub]): ?>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted"><?= View::e($label) ?></dt>
            <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary"><?= View::e($value) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>

<?php if ($users === []): ?>
    <div class="mt-6 rounded-xl border border-dashed border-border-strong p-8 text-center">
        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-border/40 text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('users') ?></span>
        <p class="mt-3 text-sm text-text-secondary">Nenhum usuário cadastrado ainda.</p>
    </div>
<?php else: ?>
    <div class="mt-5 overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-border text-text-muted">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">Nome</th>
                    <th scope="col" class="px-4 py-3 font-medium">E-mail</th>
                    <th scope="col" class="px-4 py-3 font-medium">Perfil</th>
                    <th scope="col" class="px-4 py-3 font-medium">Sites</th>
                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr class="group border-b border-border/60 transition-colors last:border-0 hover:bg-surface-2/40">
                        <td class="p-0">
                            <a href="/users/<?= View::e($user['id']) ?>/edit" class="flex items-center gap-3 px-4 py-3 text-text-primary">
                                <?= Avatar::html($user['avatar_path'] ?? null, $user['name'], size: 'h-9 w-9', radius: 'rounded-full', textSize: 'text-sm') ?>
                                <span class="font-medium group-hover:text-cyan"><?= View::e($user['name']) ?></span>
                            </a>
                        </td>
                        <td class="px-4 py-3 font-mono text-text-secondary"><?= View::e($user['email']) ?></td>
                        <td class="px-4 py-3"><?= Labels::roleBadge($user['role']) ?></td>
                        <td class="px-4 py-3 text-text-secondary">
                            <?= $user['role'] === 'ADMIN' ? 'todos' : View::e($user['site_count']) ?>
                        </td>
                        <td class="px-4 py-3"><?= Labels::activeBadge((int) $user['is_active'] === 1) ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="/users/<?= View::e($user['id']) ?>/edit" class="font-medium text-cyan hover:text-cyan-bright">
                                Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
