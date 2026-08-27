<?php

declare(strict_types=1);

use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */
/** @var array{INTEREST: int, NON_INTEREST: int} $ruleCounts */
/** @var bool $canEditSite */

$activeTab = 'overview';
require __DIR__ . '/_tabs.php';
?>
<dl class="grid gap-4 sm:grid-cols-2">
    <div class="rounded-lg border border-border bg-surface p-4">
        <dt class="text-xs uppercase tracking-wide text-text-muted">Nicho</dt>
        <dd class="mt-1 text-text-primary"><?= View::e($site['niche'] ?? '—') ?></dd>
    </div>
    <div class="rounded-lg border border-border bg-surface p-4">
        <dt class="text-xs uppercase tracking-wide text-text-muted">Idioma · Tom</dt>
        <dd class="mt-1 text-text-primary">
            <?= View::e($site['language']) ?><?= $site['tone'] ? ' · ' . View::e($site['tone']) : '' ?>
        </dd>
    </div>
</dl>

<div class="mt-6 grid gap-4 sm:grid-cols-3">
    <a href="/sites/<?= View::e($site['id']) ?>/categories"
       class="rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Categorias</p>
        <p class="mt-1 font-mono text-2xl text-text-primary"><?= count($categories) ?></p>
    </a>
    <a href="/sites/<?= View::e($site['id']) ?>/rules"
       class="rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Interesses</p>
        <p class="mt-1 font-mono text-2xl text-text-primary"><?= View::e($ruleCounts['INTEREST']) ?></p>
    </a>
    <a href="/sites/<?= View::e($site['id']) ?>/rules"
       class="rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Não-interesses</p>
        <p class="mt-1 font-mono text-2xl text-text-primary"><?= View::e($ruleCounts['NON_INTEREST']) ?></p>
    </a>
</div>

<p class="mt-8 text-sm text-text-muted">Metas editoriais chegam na próxima etapa da Fase 3.</p>
