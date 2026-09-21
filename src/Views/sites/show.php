<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Flag;
use App\Support\Icon;
use App\Support\Labels;
use App\Support\Languages;
use App\Support\SvgChart;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $categories */
/** @var array{INTEREST: int, NON_INTEREST: int} $ruleCounts */
/** @var int $goalCount */
/** @var bool $canEditSite */
/** @var array{spent: float, limit: float, percent: int, over: bool}|null $costBudget */
/** @var array{blocked: int, error: int} $attention */
/** @var int $staleCount */
/** @var array{periods:list<string>,produced:list<int>,published:list<int>,ai_cost:list<float>} $trend */

$activeTab = 'overview';
require __DIR__ . '/_tabs.php';

$active = (int) $site['is_active'] === 1;
$hasAttention = $attention['blocked'] + $attention['error'] > 0;
$costOver = $costBudget !== null && $costBudget['over'];
$costWarn = $costBudget !== null && !$costOver && $costBudget['percent'] >= 80;

// Tom do card-herói: o pior sinal manda (pedido do responsável, 2026-09-21: "mais profissional,
// com tags" — dá pra ver o estado do site batendo o olho no topo, sem ler os avisos um por um).
$heroTone = match (true) {
    !$active     => 'muted',
    $hasAttention => 'danger',
    $staleCount > 0 || $costOver => 'warning',
    default      => 'success',
};
?>
<?php // ── Herói: logo, nome, tom pelo estado do site, tags do que precisa de atenção ── ?>
<section class="article-card <?= Labels::articleCardTone($heroTone) ?> hover-card rounded-3xl" style="--card-phase: -1.2s">
    <span class="article-card-fx" aria-hidden="true"></span>
    <span class="article-card-tags">
        <span class="article-card-tag <?= $active ? 'article-card-tag--success' : 'article-card-tag--muted' ?>">
            <?php if ($active): ?><span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span><?php endif; ?>
            <?= $active ? 'Ativo' : 'Inativo' ?>
        </span>
        <?php if ($hasAttention): ?>
            <span class="article-card-tag article-card-tag--danger"><?= Icon::nav('alert') ?>Precisa de atenção</span>
        <?php endif; ?>
        <?php if ($staleCount > 0): ?>
            <span class="article-card-tag article-card-tag--warning"><?= Icon::nav('clock') ?>Rascunhos parados</span>
        <?php endif; ?>
        <?php if ($costOver): ?>
            <span class="article-card-tag article-card-tag--danger">Custo de IA no limite</span>
        <?php elseif ($costWarn): ?>
            <span class="article-card-tag article-card-tag--warning">Custo de IA alto</span>
        <?php endif; ?>
    </span>

    <?php // Logo grande e centralizada, como um header (pedido do responsável, 2026-09-21) — mesma
           // linguagem da Início (imagem grande no centro, texto embaixo). ?>
    <div class="relative flex flex-col items-center gap-5 p-6 pt-9 text-center sm:p-8 sm:pt-10">
        <div class="flex h-32 w-full max-w-sm items-center justify-center rounded-2xl bg-white p-5 shadow-[0_10px_30px_-14px_rgba(0,0,0,.8)]">
            <span class="block h-full w-full transition-transform duration-500 ease-out [.hover-card:hover_&]:scale-105">
                <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-full w-full', radius: 'rounded-lg', textSize: 'text-5xl', fit: 'contain', bg: 'bg-white') ?>
            </span>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Visão geral</p>
            <h1 class="mt-1 font-display text-3xl font-bold text-text-primary sm:text-4xl"><?= View::e($site['name']) ?></h1>
        </div>

        <a href="/sites/<?= View::e($site['id']) ?>/production"
           class="btn btn-primary group/open relative z-10 px-6 py-3 text-sm">
            Ir pra Produção
            <span class="transition-transform duration-200 group-hover/open:translate-x-1 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
        </a>
    </div>
</section>

<?php if ($hasAttention || $staleCount > 0 || $costBudget !== null): ?>
    <div class="mt-4 space-y-2">
        <?php if ($hasAttention): ?>
            <?php
            $parts = [];
            if ($attention['blocked'] > 0) {
                $parts[] = $attention['blocked'] . ' bloqueado(s)';
            }
            if ($attention['error'] > 0) {
                $parts[] = $attention['error'] . ' com falha técnica';
            }
            ?>
            <p role="alert" class="flex items-start gap-2.5 rounded-xl border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger">
                <span class="mt-0.5 shrink-0 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('alert') ?></span>
                <span>Precisam de atenção: <?= implode(' · ', $parts) ?>.
                    <a href="/sites/<?= View::e($site['id']) ?>/production" class="font-semibold underline">Ver em Produção</a>.</span>
            </p>
        <?php endif; ?>
        <?php if ($staleCount > 0): ?>
            <p role="alert" class="flex items-start gap-2.5 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
                <span class="mt-0.5 shrink-0 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('clock') ?></span>
                <span><?= $staleCount ?> rascunho<?= $staleCount > 1 ? 's' : '' ?> parado<?= $staleCount > 1 ? 's' : '' ?> "planejado/em produção" há mais de 15 min —
                    provavelmente o processo em segundo plano (worker) não está rodando. Avise o time técnico.
                    <a href="/sites/<?= View::e($site['id']) ?>/production" class="font-semibold underline">Ver em Produção</a>.</span>
            </p>
        <?php endif; ?>
        <?php if ($costBudget !== null): ?>
            <?php
            $costClasses = match (true) {
                $costOver => 'border-danger/40 bg-danger/10 text-danger',
                $costWarn => 'border-warning/40 bg-warning/10 text-warning',
                default   => 'border-border bg-surface text-text-secondary',
            };
            ?>
            <p role="<?= $costOver ? 'alert' : 'status' ?>" class="flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm <?= $costClasses ?>">
                <span class="mt-0.5 shrink-0 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('ai') ?></span>
                <span>Custo de IA no mês: US$ <?= number_format($costBudget['spent'], 2) ?>
                    de US$ <?= number_format($costBudget['limit'], 2) ?> estimados (<?= $costBudget['percent'] ?>%)
                    <?= $costOver ? ' — limite atingido, sem bloqueio automático.' : '' ?></span>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="mt-8">
    <h2 class="font-display text-lg font-semibold text-text-primary">Identidade editorial</h2>
    <p class="mt-1 text-sm text-text-secondary">O que orienta a IA em toda geração de conteúdo pra este site.</p>

    <?php
    // Idioma mostra a bandeira do preset (mesma lógica de /sites) em vez do ícone genérico —
    // só cai pro globo quando o texto salvo não bate com nenhum preset conhecido.
    $presets = Languages::presets();
    $langKey = Languages::presetFor((string) ($site['language'] ?? ''));

    $facts = [
        ['Nicho', $site['niche'] ?? null, 'categories', 'cyan'],
        ['Idioma', $site['language'] ?? null, 'globe', 'info'],
        ['Tom', $site['tone'] ?? null, 'intelligence', 'success'],
        ['Público', $site['target_audience'] ?? null, 'users', 'warning'],
    ];
    ?>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($facts as $i => [$label, $value, $icon, $tone]): ?>
            <div class="article-card <?= Labels::articleCardTone($tone) ?> rounded-xl" style="--card-enter: <?= $i * 50 ?>ms">
                <div class="flex items-center gap-3 p-4">
                    <?php if ($label === 'Idioma' && $langKey !== null): ?>
                        <span class="block h-9 w-9 shrink-0 overflow-hidden rounded-full shadow-[0_0_0_1px_rgba(255,255,255,.25)]"><?= Flag::svg($presets[$langKey]['flag']) ?></span>
                    <?php else: ?>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?= Labels::toneClasses($tone) ?>"><?= Icon::nav($icon) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted"><?= View::e($label) ?></p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-text-primary">
                            <?= View::e($label === 'Idioma' && $langKey !== null ? $presets[$langKey]['label'] : ($value !== null && $value !== '' ? (string) $value : '—')) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mt-8">
    <h2 class="font-display text-lg font-semibold text-text-primary">Configuração editorial</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $shortcuts = [
            ['/categories', 'Categorias', count($categories), 'categories', 'cyan'],
            ['/rules', 'Interesses', $ruleCounts['INTEREST'], 'rules', 'cyan'],
            ['/rules', 'Não-interesses', $ruleCounts['NON_INTEREST'], 'rules', 'warning'],
            ['/goals', 'Metas', $goalCount, 'goals', 'cyan'],
        ];
        ?>
        <?php foreach ($shortcuts as $i => [$path, $label, $count, $icon, $tone]): ?>
            <a href="/sites/<?= View::e($site['id']) ?><?= $path ?>"
               class="hover-card group flex items-start gap-3 rounded-xl border border-border bg-surface p-4 hover:border-cyan"
               style="animation: fade-in-up 260ms ease backwards; animation-delay: <?= 200 + $i * 60 ?>ms">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md <?= Labels::toneClasses($tone) ?>"><?= Icon::nav($icon) ?></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-text-muted"><?= View::e($label) ?></span>
                        <span aria-hidden="true" class="text-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-cyan">→</span>
                    </span>
                    <span class="mt-0.5 block font-mono text-xl font-semibold text-text-primary"><?= View::e($count) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
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

        <div class="article-card article-card--cyan hover-card relative mt-3 rounded-2xl" style="--card-enter: 420ms">
            <span class="article-card-fx" aria-hidden="true"></span>
            <span class="article-card-tags">
                <span class="article-card-tag article-card-tag--cyan"><?= Icon::nav('clock') ?>Últimos <?= count($trend['periods']) ?> meses</span>
            </span>

            <div class="relative flex flex-wrap items-end justify-between gap-4 p-5 pt-8">
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
            </div>

            <div class="relative px-5 pb-5">
                <?php // Degradê ciano → violeta (o mesmo par de cores da navegação dentro do site) em vez de
                       // uma cor chapada, e cada barra cresce ao entrar — pedido do responsável, 2026-09-21:
                       // "não deixa essa barra ciano parada, anima ela com mais cores". ?>
                <?= SvgChart::bars(
                    $labels, $trend['produced'], '#00D0F0', static fn ($v) => (string) (int) $v,
                    colorEnd: '#A78BFA', animated: true,
                ) ?>
            </div>
        </div>
    </section>
<?php endif; ?>
