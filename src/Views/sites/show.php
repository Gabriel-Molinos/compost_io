<?php

declare(strict_types=1);

use App\Support\SvgChart;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */
/** @var array{INTEREST: int, NON_INTEREST: int} $ruleCounts */
/** @var int $goalCount */
/** @var bool $canEditSite */
/** @var array{spent: float, limit: float, percent: int, over: bool}|null $costBudget */
/** @var array{blocked: int, error: int} $attention */
/** @var array{periods:list<string>,produced:list<int>,published:list<int>,ai_cost:list<float>} $trend */

$activeTab = 'overview';
require __DIR__ . '/_tabs.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="font-display text-lg font-semibold text-text-primary">Visão geral</h2>
        <p class="mt-1 text-sm text-text-secondary">
            <?= View::e($site['niche'] ?? 'Sem nicho definido') ?>
            <?= !empty($site['language']) ? ' · ' . View::e($site['language']) : '' ?>
        </p>
    </div>
    <a href="/sites/<?= View::e($site['id']) ?>/production"
       class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
        Ir pra Produção
    </a>
</div>

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
    <p role="alert" class="mt-5 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
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
       class="mt-3 rounded-md border px-3 py-2 text-sm <?= $toneClasses ?>">
        Custo de IA no mês: US$ <?= number_format($costBudget['spent'], 2) ?>
        de US$ <?= number_format($costBudget['limit'], 2) ?> estimados (<?= $costBudget['percent'] ?>%)
        <?= $costBudget['over'] ? ' — limite atingido, sem bloqueio automático.' : '' ?>
    </p>
<?php endif; ?>

<div class="mt-6 grid gap-4 sm:grid-cols-2">
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
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-3">
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

<?php if (array_sum($trend['produced']) > 0): ?>
    <section class="mt-8">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">
                Produção nos últimos <?= count($trend['periods']) ?> meses
            </h3>
            <a href="/sites/<?= View::e($site['id']) ?>/reports" class="text-xs text-cyan hover:text-cyan-bright">Ver relatório completo →</a>
        </div>
        <div class="mt-3 rounded-lg border border-border bg-surface p-4">
            <?php
            $mesesAbrev = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun',
                '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
            $labels = array_map(static function (string $p) use ($mesesAbrev): string {
                [$y, $m] = explode('-', $p);
                return $mesesAbrev[$m] . '/' . substr($y, 2);
            }, $trend['periods']);
            ?>
            <?= SvgChart::bars($labels, $trend['produced'], '#00D0F0', static fn ($v) => (string) (int) $v) ?>
        </div>
    </section>
<?php endif; ?>
