<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $users */
?>
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-text-primary">Usuários</h1>
    <a href="/users/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Novo usuário
    </a>
</div>

<div class="mt-6 overflow-x-auto rounded-lg border border-border">
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
                <tr class="border-b border-border/60 last:border-0">
                    <td class="px-4 py-3 text-text-primary">
                        <span class="flex items-center gap-3">
                            <?= Avatar::html($user['avatar_path'] ?? null, $user['name'], size: 'h-7 w-7', radius: 'rounded-full', textSize: 'text-xs') ?>
                            <?= View::e($user['name']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-text-secondary"><?= View::e($user['email']) ?></td>
                    <td class="px-4 py-3 text-text-secondary"><?= View::e(Labels::role($user['role'])) ?></td>
                    <td class="px-4 py-3 text-text-secondary">
                        <?= $user['role'] === 'ADMIN' ? 'todos' : View::e($user['site_count']) ?>
                    </td>
                    <td class="px-4 py-3"><?= Labels::activeBadge((int) $user['is_active'] === 1) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="/users/<?= View::e($user['id']) ?>/edit"
                           class="text-cyan hover:text-cyan-bright">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
