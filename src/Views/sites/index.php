<?php

declare(strict_types=1);

use App\View;

/** @var list<array<string, mixed>> $sites */
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-text-primary">Sites</h1>
    <a href="/sites/new"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
        Novo site
    </a>
</div>

<?php if ($sites === []): ?>
    <p class="mt-8 text-text-secondary">Nenhum site cadastrado ainda.</p>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded-lg border border-border">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-border text-text-muted">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">Nome</th>
                    <th scope="col" class="px-4 py-3 font-medium">Nicho</th>
                    <th scope="col" class="px-4 py-3 font-medium">Idioma</th>
                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site): ?>
                    <tr class="border-b border-border/60 last:border-0">
                        <td class="px-4 py-3 text-text-primary"><?= View::e($site['name']) ?></td>
                        <td class="px-4 py-3 text-text-secondary"><?= View::e($site['niche'] ?? '—') ?></td>
                        <td class="px-4 py-3 font-mono text-text-secondary"><?= View::e($site['language']) ?></td>
                        <td class="px-4 py-3">
                            <?php if ((int) $site['is_active'] === 1): ?>
                                <span class="text-success">● Ativo</span>
                            <?php else: ?>
                                <span class="text-text-muted">○ Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="/sites/<?= View::e($site['id']) ?>/edit"
                               class="text-cyan hover:text-cyan-light">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
