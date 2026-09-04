<?php

declare(strict_types=1);

use App\Support\Avatar;
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
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($sites as $site): ?>
            <?php $active = (int) $site['is_active'] === 1; ?>
            <a href="/sites/<?= View::e($site['id']) ?>"
               class="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface
                      transition-colors hover:border-cyan">
                <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-32 w-full', radius: 'rounded-none', textSize: 'text-3xl', fit: 'contain', bg: 'bg-white') ?>

                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-display text-lg font-semibold text-text-primary"><?= View::e($site['name']) ?></span>
                        <?= Labels::activeBadge($active) ?>
                    </div>

                    <?php if (($site['niche'] ?? '') !== ''): ?>
                        <span class="inline-block w-fit max-w-full truncate rounded-full bg-surface-2 px-2.5 py-1 text-xs text-text-secondary">
                            <?= View::e($site['niche']) ?>
                        </span>
                    <?php endif; ?>

                    <dl class="mt-1 space-y-1.5 text-sm">
                        <div class="flex gap-2">
                            <dt class="w-20 shrink-0 text-text-muted">Idioma</dt>
                            <dd class="font-mono text-text-secondary"><?= View::e($site['language']) ?></dd>
                        </div>
                        <?php if (!empty($site['tone'])): ?>
                            <div class="flex gap-2">
                                <dt class="w-20 shrink-0 text-text-muted">Tom</dt>
                                <dd class="truncate text-text-secondary"><?= View::e($site['tone']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($site['target_audience'])): ?>
                            <div class="flex gap-2">
                                <dt class="w-20 shrink-0 text-text-muted">Público</dt>
                                <dd class="truncate text-text-secondary"><?= View::e($site['target_audience']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-2">
                            <dt class="w-20 shrink-0 text-text-muted">WordPress</dt>
                            <dd class="truncate <?= $site['wordpress_url'] ? 'text-text-secondary' : 'text-text-muted' ?>">
                                <?= $site['wordpress_url'] ? View::e(preg_replace('~^https?://~', '', (string) $site['wordpress_url'])) : 'não configurado' ?>
                            </dd>
                        </div>
                    </dl>

                    <span class="mt-auto flex items-center gap-1 pt-2 text-sm font-medium text-cyan">
                        Abrir site
                        <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">→</span>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
