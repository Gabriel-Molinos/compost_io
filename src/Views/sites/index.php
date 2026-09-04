<?php

declare(strict_types=1);

use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $sites */
/** @var bool $isAdmin */
?>
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-text-primary">Sites</h1>
    <?php if ($isAdmin): ?>
        <a href="/sites/new"
           class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Novo site
        </a>
    <?php endif; ?>
</div>

<?php if ($sites === []): ?>
    <p class="mt-8 text-sm text-text-secondary">
        <?= $isAdmin ? 'Nenhum site cadastrado ainda.' : 'Você não está vinculado a nenhum site.' ?>
    </p>
<?php else: ?>
    <ul class="mt-6 divide-y divide-border rounded-lg border border-border bg-surface">
        <?php foreach ($sites as $site): ?>
            <li>
                <a href="/sites/<?= View::e($site['id']) ?>"
                   class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-surface">
                    <span class="text-text-primary"><?= View::e($site['name']) ?></span>
                    <span class="flex items-center gap-3 text-sm text-text-muted">
                        <span><?= View::e($site['niche'] ?? '—') ?></span>
                        <span class="font-mono"><?= View::e($site['language']) ?></span>
                        <?= Labels::activeBadge((int) $site['is_active'] === 1) ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
