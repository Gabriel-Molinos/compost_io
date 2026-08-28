<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\View;

/** @var array<string, mixed> $site */
/** @var string $activeTab */

$tabs = [
    ['overview',   'Visão geral', '/sites/' . $site['id'],                true],
    ['categories', 'Categorias',  '/sites/' . $site['id'] . '/categories', true],
    ['rules',      'Interesses',  '/sites/' . $site['id'] . '/rules',      true],
    ['goals',      'Metas',       '/sites/' . $site['id'] . '/goals',      true],
    ['config',     'Configuração', '/sites/' . $site['id'] . '/edit',      AuthService::isAdmin()],
    ['ai',         'IA (teste)',  '/sites/' . $site['id'] . '/ai-playground', AuthService::isAdmin()],
];
?>
<div class="mb-6 border-b border-border">
    <a href="/sites" class="text-sm text-text-secondary hover:text-text-primary">← Sites</a>
    <h1 class="mt-1 text-2xl font-bold text-text-primary"><?= View::e($site['name']) ?></h1>
    <nav aria-label="Seções do site" class="mt-3 flex flex-wrap gap-4 text-sm">
        <?php foreach ($tabs as [$key, $label, $href, $visible]): ?>
            <?php if (!$visible) { continue; } ?>
            <a href="<?= View::e($href) ?>"
               class="-mb-px border-b-2 pb-2 <?= ($activeTab ?? '') === $key
                   ? 'border-cyan text-text-primary'
                   : 'border-transparent text-text-secondary hover:text-text-primary' ?>">
                <?= View::e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
