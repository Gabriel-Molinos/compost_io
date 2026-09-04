<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Icon;
use App\Support\Labels;
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
    <div class="flex items-center gap-4">
        <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-24 w-24', radius: 'rounded-lg', textSize: 'text-3xl', bg: 'bg-white') ?>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Visão geral</p>
            <div class="flex items-center gap-2">
                <h1 class="font-display text-2xl font-bold text-text-primary"><?= View::e($site['name']) ?></h1>
                <?= Labels::activeBadge((int) $site['is_active'] === 1) ?>
            </div>
        </div>
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

<section class="mt-8">
    <h2 class="font-display text-lg font-semibold text-text-primary">Identidade editorial</h2>
    <p class="mt-1 text-sm text-text-secondary">O que orienta a IA em toda geração de conteúdo pra este site.</p>

    <dl class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border lg:grid-cols-4">
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Nicho</dt>
            <dd class="mt-1 truncate text-sm text-text-primary"><?= View::e($site['niche'] ?? '—') ?></dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Idioma</dt>
            <dd class="mt-1 font-mono text-sm text-text-primary"><?= View::e($site['language']) ?></dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Tom</dt>
            <dd class="mt-1 truncate text-sm text-text-primary"><?= View::e($site['tone'] ?? '—') ?></dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Público</dt>
            <dd class="mt-1 truncate text-sm text-text-primary"><?= View::e($site['target_audience'] ?? '—') ?></dd>
        </div>
    </dl>
</section>

<section class="mt-8">
    <h2 class="font-display text-lg font-semibold text-text-primary">Configuração editorial</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="/sites/<?= View::e($site['id']) ?>/categories"
           class="hover-card group flex items-start gap-3 rounded-lg border border-border bg-surface p-4 hover:border-cyan">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('categories') ?></span>
            <span>
                <span class="block text-xs font-semibold uppercase tracking-wide text-text-muted">Categorias</span>
                <span class="mt-0.5 block font-mono text-xl font-semibold text-text-primary"><?= count($categories) ?></span>
            </span>
        </a>
        <a href="/sites/<?= View::e($site['id']) ?>/rules"
           class="hover-card group flex items-start gap-3 rounded-lg border border-border bg-surface p-4 hover:border-cyan">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('rules') ?></span>
            <span>
                <span class="block text-xs font-semibold uppercase tracking-wide text-text-muted">Interesses</span>
                <span class="mt-0.5 block font-mono text-xl font-semibold text-text-primary"><?= View::e($ruleCounts['INTEREST']) ?></span>
            </span>
        </a>
        <a href="/sites/<?= View::e($site['id']) ?>/rules"
           class="hover-card group flex items-start gap-3 rounded-lg border border-border bg-surface p-4 hover:border-cyan">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('rules') ?></span>
            <span>
                <span class="block text-xs font-semibold uppercase tracking-wide text-text-muted">Não-interesses</span>
                <span class="mt-0.5 block font-mono text-xl font-semibold text-text-primary"><?= View::e($ruleCounts['NON_INTEREST']) ?></span>
            </span>
        </a>
        <a href="/sites/<?= View::e($site['id']) ?>/goals"
           class="hover-card group flex items-start gap-3 rounded-lg border border-border bg-surface p-4 hover:border-cyan">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('goals') ?></span>
            <span>
                <span class="block text-xs font-semibold uppercase tracking-wide text-text-muted">Metas</span>
                <span class="mt-0.5 block font-mono text-xl font-semibold text-text-primary"><?= View::e($goalCount) ?></span>
            </span>
        </a>
    </div>
</section>

<?php if (array_sum($trend['produced']) > 0): ?>
    <?php
    $mesesAbrev = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun',
        '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
    $labels = array_map(static function (string $p) use ($mesesAbrev): string {
        [$y, $m] = explode('-', $p);
        return $mesesAbrev[$m] . '/' . substr($y, 2);
    }, $trend['periods']);

    // Mês atual × anterior — dá ao gráfico um número de destaque e uma
    // variação, em vez de só as barras soltas sem nenhum dado em texto.
    $n = count($trend['produced']);
    $current = $trend['produced'][$n - 1] ?? 0;
    $previous = $n >= 2 ? $trend['produced'][$n - 2] : null;
    $delta = $previous !== null ? $current - $previous : null;
    ?>
    <section class="mt-8">
        <div class="flex items-center justify-between">
            <h3 class="font-display text-lg font-semibold text-text-primary">Produção</h3>
            <a href="/sites/<?= View::e($site['id']) ?>/reports" class="text-sm text-cyan hover:text-cyan-bright">Ver relatório completo →</a>
        </div>

        <div class="relative mt-3 overflow-hidden rounded-lg border border-border bg-surface p-5">
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-cyan/10 blur-3xl"></div>

            <div class="relative flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Artigos este mês</p>
                    <p class="mt-1 font-mono text-3xl font-semibold text-text-primary"><?= $current ?></p>
                    <?php if ($delta !== null && $delta !== 0): ?>
                        <p class="mt-0.5 text-xs <?= $delta > 0 ? 'text-success' : 'text-warning' ?>">
                            <?= $delta > 0 ? '+' : '' ?><?= $delta ?> vs. mês passado
                        </p>
                    <?php elseif ($delta === 0): ?>
                        <p class="mt-0.5 text-xs text-text-muted">sem mudança vs. mês passado</p>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-text-muted">últimos <?= count($trend['periods']) ?> meses</p>
            </div>

            <div class="relative mt-4">
                <?= SvgChart::bars($labels, $trend['produced'], '#00D0F0', static fn ($v) => (string) (int) $v) ?>
            </div>
        </div>
    </section>
<?php endif; ?>
