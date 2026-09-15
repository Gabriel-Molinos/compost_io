<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<array<string,mixed>> $articles */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $categories */
/** @var array{all: int, done: int, attention: int, progress: int, discarded: int} $counts */
/** @var string $statusGroup */
/** @var int $page */
/** @var int $totalPages */
/** @var array{id: int}|null $tourReviewArticle */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';

// Agrupamento por status pra stat-bar + filtro por aba — desde a paginação
// (2026-09-15) os dados já chegam prontos do controller (`$counts` é sempre
// o total real do site, `$articles` é só a página atual), então o filtro
// virou navegação de verdade (?status=...&page=...) em vez de JS
// client-side escondendo linhas — com a lista paginada, filtrar em cima só
// da página atual dava contagem errada (achado real, mesma pendência).
$statusUrl = static fn (string $group): string => '/sites/' . $site['id'] . '/production'
    . ($group === 'all' ? '' : '?status=' . $group);
$pageUrl = static fn (int $p): string => '/sites/' . $site['id'] . '/production?page=' . $p
    . ($statusGroup !== 'all' ? '&status=' . $statusGroup : '');

$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
$toneBorder = static fn (string $tone): string => match ($tone) {
    'success' => 'border-l-success', 'warning' => 'border-l-warning', 'danger' => 'border-l-danger',
    'info', 'cyan' => 'border-l-cyan', default => 'border-l-border',
};
?>
<h2 class="font-display text-lg font-semibold text-text-primary">Produção</h2>
<p class="mt-1 text-sm text-text-secondary">
    Todo dia, 1 rascunho novo é gerado automaticamente por este site (se estiver ativo) —
    marcado como <span class="text-cyan">automático</span> na lista abaixo. Cada geração faz
    várias chamadas ao Gemini e <strong>tem custo</strong>. Use o formulário abaixo pra gerar
    um extra quando quiser.
</p>

<section data-tour="generate-form" class="mt-5 overflow-hidden rounded-lg border border-border bg-surface">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h3 class="font-display text-base font-semibold text-text-primary">Gerar novo rascunho</h3>
    </div>
    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/generate"
          class="flex flex-wrap items-end gap-3 p-5"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Gerando… (pode levar 1–2 min)';">
        <?= Csrf::field() ?>
        <label class="min-w-[11rem] text-sm">
            <span class="block font-medium text-text-secondary">Meta</span>
            <select name="goal_id" class="mt-1 w-full">
                <option value="">— (sem meta)</option>
                <?php foreach ($goals as $g): ?>
                    <option value="<?= View::e($g['id']) ?>"><?= View::e($g['period']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="min-w-[11rem] text-sm">
            <span class="block font-medium text-text-secondary">Categoria</span>
            <select name="category_id" class="mt-1 w-full">
                <option value="">— (a IA escolhe)</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= View::e($c['id']) ?>"><?= View::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Gerar rascunho
        </button>
    </form>
</section>

<?php if ($counts['all'] === 0): ?>
    <p class="mt-6 text-text-secondary">Nenhum artigo produzido ainda.</p>
<?php else: ?>
    <?php
    $filterTabs = [
        ['all', 'Todos', $counts['all']],
        ['progress', 'Em andamento', $counts['progress']],
        ['done', 'Aprovados', $counts['done']],
        ['attention', 'Atenção', $counts['attention']],
        ['discarded', 'Descartados', $counts['discarded']],
    ];
    ?>
    <div class="mt-8 flex items-center justify-between gap-3">
        <h3 class="font-display text-lg font-semibold text-text-primary">Rascunhos (<?= $counts[$statusGroup] ?>)</h3>
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar por status">
            <?php foreach ($filterTabs as [$key, $label, $n]): ?>
                <?php if ($key !== 'all' && $n === 0) continue; ?>
                <?php $active = $key === $statusGroup; ?>
                <a href="<?= View::e($statusUrl($key)) ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>"
                   class="rounded-full border px-3 py-1 text-xs font-medium transition-colors <?= $active ? 'border-cyan bg-cyan/10 text-cyan' : 'border-border text-text-secondary hover:border-border-strong' ?>">
                    <?= View::e($label) ?> <span class="font-mono"><?= $n ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Tutorial guiado (assets/js/tour.js): quando existe um rascunho em
    // revisão, o passo "Revisar um rascunho" pode navegar direto pra ele e
    // mostrar Aprovar/Rejeitar de verdade em vez de só descrever em texto.
    // Vem do controller (`ArticleService::firstInReview()`) desde a
    // paginação — o rascunho em revisão pode estar em qualquer página.
    $tourReviewHref = $tourReviewArticle !== null
        ? '/sites/' . $site['id'] . '/production/' . $tourReviewArticle['id']
        : '';
    ?>
    <?php if ($articles === []): ?>
        <p class="mt-3 text-sm text-text-secondary">Nenhum rascunho neste filtro.</p>
    <?php endif; ?>
    <ul data-tour="article-list" data-tour-review-href="<?= View::e($tourReviewHref) ?>" class="mt-3 space-y-2">
        <?php foreach ($articles as $a): ?>
            <?php
            $tone = Labels::articleStatusTone((string) $a['status']);
            $isGenerating = in_array($a['status'], ['PLANNED', 'IN_PROGRESS'], true);
            ?>
            <li class="flex flex-col gap-2.5 rounded-lg border-l-2 <?= $toneBorder($tone) ?> border-y border-r border-border bg-surface px-4 py-3.5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>"
                           class="font-medium text-text-primary hover:text-cyan">
                            <?= View::e($a['title'] ?: 'Rascunho #' . $a['id']) ?>
                        </a>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-text-muted">
                            <?= Labels::articleStatusBadge((string) $a['status']) ?>
                            <span><?= View::e(date('d/m/Y', strtotime((string) $a['created_at']))) ?></span>
                            <?php if (($a['source'] ?? 'MANUAL') === 'AUTO'): ?>
                                <span class="rounded-full bg-cyan/10 px-1.5 py-0.5 text-[11px] font-medium text-cyan">automático</span>
                            <?php endif; ?>
                            <?php if ((int) ($a['attempt_number'] ?? 1) > 1): ?><span>· tentativa <?= View::e($a['attempt_number']) ?></span><?php endif; ?>
                            <?php if (!empty($a['category_name'])): ?><span>· <?= View::e($a['category_name']) ?></span><?php endif; ?>
                            <?php if (!empty($a['word_count'])): ?><span>· <?= View::e($a['word_count']) ?> palavras</span><?php endif; ?>
                            <span class="font-mono">· US$ <?= number_format((float) $a['ai_cost'], 4) ?></span>
                        </div>
                    </div>
                    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>/delete"
                          data-confirm="Descartar este rascunho?">
                        <?= Csrf::field() ?>
                        <button type="submit" title="Descartar" aria-label="Descartar"
                                class="shrink-0 rounded-md p-1.5 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">
                            <?= $deleteIcon ?>
                        </button>
                    </form>
                </div>
                <?php if ($isGenerating): ?>
                    <?php $isStale = strtotime((string) $a['created_at']) <= strtotime('-15 minutes'); ?>
                    <?php if ($isStale): ?>
                        <p class="text-xs text-warning">
                            Isso está demorando mais que o esperado — provavelmente o processo em
                            segundo plano (worker) não está rodando. Avise o time técnico.
                        </p>
                    <?php else: ?>
                        <div class="loading-bar-track" role="progressbar" aria-label="A IA está gerando este rascunho">
                            <div class="loading-bar-fill"></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-5 flex items-center justify-center gap-3 text-sm" aria-label="Paginação">
            <?php if ($page > 1): ?>
                <a href="<?= View::e($pageUrl($page - 1)) ?>" class="rounded-md border border-border px-3 py-1.5 text-text-secondary hover:border-border-strong">← Anterior</a>
            <?php else: ?>
                <span class="rounded-md border border-border px-3 py-1.5 text-text-muted opacity-40">← Anterior</span>
            <?php endif; ?>
            <span class="text-text-secondary">Página <?= $page ?> de <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= View::e($pageUrl($page + 1)) ?>" class="rounded-md border border-border px-3 py-1.5 text-text-secondary hover:border-border-strong">Próxima →</a>
            <?php else: ?>
                <span class="rounded-md border border-border px-3 py-1.5 text-text-muted opacity-40">Próxima →</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
