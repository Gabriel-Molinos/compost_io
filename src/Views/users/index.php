<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $users */

$admins = array_filter($users, static fn ($u) => $u['role'] === 'ADMIN');
$active = array_filter($users, static fn ($u) => (int) $u['is_active'] === 1);
?>
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-text-primary">Usuários</h1>
    <a href="/users/new"
       class="btn btn-primary px-4 py-2 text-sm">
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
<dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-4">
    <?php foreach ($stats as [$label, $value, $sub]): ?>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted"><?= View::e($label) ?></dt>
            <dd class="mt-1 font-mono text-2xl font-semibold text-text-primary"><?= View::e($value) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>

<?php if ($users === []): ?>
    <p class="mt-8 text-sm text-text-secondary">Nenhum usuário cadastrado ainda.</p>
<?php else: ?>
    <div class="mt-5 overflow-x-auto rounded-lg border border-border">
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
                    <tr class="group border-b border-border/60 last:border-0">
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
                            <a href="/users/<?= View::e($user['id']) ?>/edit" class="text-cyan hover:text-cyan-bright">
                                Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
