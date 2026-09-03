<?php

declare(strict_types=1);

use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */
/** @var array{INTEREST: int, NON_INTEREST: int} $ruleCounts */
/** @var int $goalCount */
/** @var bool $canEditSite */
/** @var array{spent: float, limit: float, percent: int, over: bool}|null $costBudget */
/** @var array{blocked: int, error: int} $attention */

$activeTab = 'overview';
require __DIR__ . '/_tabs.php';
?>
<?php if ($attention['blocked'] + $attention['error'] > 0): ?>
    <?php
    $parts = [];
    if ($attention['blocked'] > 0) {
        $parts[] = $attention['blocked'] . ' bloqueado(s)';
    }
    if ($attention['error'] > 0) {
        $parts[] = $attention['error'] . ' com falha técnica';
    }
    ?>
    <p role="alert" class="mb-6 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Precisam de atenção: <?= implode(' · ', $parts) ?>.
        <a href="/sites/<?= View::e($site['id']) ?>/production" class="underline">Ver em Produção</a>.
    </p>
<?php endif; ?>
<?php if ($costBudget !== null): ?>
    <?php
    $toneClasses = match (true) {
        $costBudget['over']              => 'border-danger/40 bg-danger/10 text-danger',
        $costBudget['percent'] >= 80     => 'border-warning/40 bg-warning/10 text-warning',
        default                          => 'border-border bg-surface text-text-secondary',
    };
    ?>
    <p role="<?= $costBudget['over'] ? 'alert' : 'status' ?>"
       class="mb-6 rounded-md border px-3 py-2 text-sm <?= $toneClasses ?>">
        Custo de IA no mês: US$ <?= number_format($costBudget['spent'], 2) ?>
        de US$ <?= number_format($costBudget['limit'], 2) ?> estimados (<?= $costBudget['percent'] ?>%)
        <?= $costBudget['over'] ? ' — limite atingido, sem bloqueio automático.' : '' ?>
    </p>
<?php endif; ?>
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
       class="hover-card rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Categorias</p>
        <p class="mt-1 font-display text-2xl text-text-primary"><?= count($categories) ?></p>
    </a>
    <a href="/sites/<?= View::e($site['id']) ?>/rules"
       class="hover-card rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Interesses</p>
        <p class="mt-1 font-display text-2xl text-text-primary"><?= View::e($ruleCounts['INTEREST']) ?></p>
    </a>
    <a href="/sites/<?= View::e($site['id']) ?>/rules"
       class="hover-card rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Não-interesses</p>
        <p class="mt-1 font-display text-2xl text-text-primary"><?= View::e($ruleCounts['NON_INTEREST']) ?></p>
    </a>
    <a href="/sites/<?= View::e($site['id']) ?>/goals"
       class="hover-card rounded-lg border border-border bg-surface p-4 hover:border-cyan">
        <p class="text-xs uppercase tracking-wide text-text-muted">Metas</p>
        <p class="mt-1 font-display text-2xl text-text-primary"><?= View::e($goalCount) ?></p>
    </a>
</div>
