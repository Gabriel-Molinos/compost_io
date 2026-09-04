<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<array<string,mixed>> $articles */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $categories */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';

// Agrupamento por status pra stat-bar + filtro por aba (client-side, sem
// recarregar) — os mesmos grupos usados no alerta de atenção da Visão Geral.
$statusGroup = static fn (string $s): string => match ($s) {
    'APPROVED', 'SCHEDULED', 'PUBLISHED' => 'done',
    'BLOCKED', 'ERROR'                   => 'attention',
    'DISCARDED'                          => 'discarded',
    default                              => 'progress', // PLANNED/IN_PROGRESS/IN_REVIEW/REVISION_REQUESTED
};
$counts = ['done' => 0, 'attention' => 0, 'progress' => 0, 'discarded' => 0];
foreach ($articles as $a) {
    $counts[$statusGroup((string) $a['status'])]++;
}

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

<section class="mt-5 overflow-hidden rounded-lg border border-border bg-surface">
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

<?php if ($articles === []): ?>
    <p class="mt-6 text-text-secondary">Nenhum artigo produzido ainda.</p>
<?php else: ?>
    <?php
    $filterTabs = [
        ['all', 'Todos', count($articles)],
        ['progress', 'Em andamento', $counts['progress']],
        ['done', 'Aprovados', $counts['done']],
        ['attention', 'Atenção', $counts['attention']],
        ['discarded', 'Descartados', $counts['discarded']],
    ];
    ?>
    <div class="mt-8 flex items-center justify-between gap-3">
        <h3 class="font-display text-lg font-semibold text-text-primary">Rascunhos (<?= count($articles) ?>)</h3>
        <div class="flex flex-wrap gap-1.5" data-filter-tabs role="tablist" aria-label="Filtrar por status">
            <?php foreach ($filterTabs as [$key, $label, $n]): ?>
                <?php if ($key !== 'all' && $n === 0) continue; ?>
                <button type="button" data-filter="<?= $key ?>" role="tab" aria-selected="<?= $key === 'all' ? 'true' : 'false' ?>"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors <?= $key === 'all' ? 'border-cyan bg-cyan/10 text-cyan' : 'border-border text-text-secondary hover:border-border-strong' ?>">
                    <?= View::e($label) ?> <span class="font-mono"><?= $n ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <ul class="mt-3 space-y-2">
        <?php foreach ($articles as $a): ?>
            <?php
            $tone = Labels::articleStatusTone((string) $a['status']);
            $group = $statusGroup((string) $a['status']);
            ?>
            <li data-status-group="<?= $group ?>"
                class="flex items-start justify-between gap-4 rounded-lg border-l-2 <?= $toneBorder($tone) ?> border-y border-r border-border bg-surface px-4 py-3.5">
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
                      onsubmit="return confirm('Descartar este rascunho?');">
                    <?= Csrf::field() ?>
                    <button type="submit" title="Descartar" aria-label="Descartar"
                            class="shrink-0 rounded-md p-1.5 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">
                        <?= $deleteIcon ?>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <p data-filter-empty class="mt-3 hidden text-sm text-text-secondary">Nenhum rascunho neste filtro.</p>
<?php endif; ?>

<script>
    // Progressive enhancement: filtro por status sem recarregar a página —
    // some/mostra as linhas já renderizadas via data-status-group.
    (function () {
        var tabs = document.querySelectorAll('[data-filter]');
        var rows = document.querySelectorAll('[data-status-group]');
        var empty = document.querySelector('[data-filter-empty]');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var filter = tab.getAttribute('data-filter');
                tabs.forEach(function (t) {
                    var active = t === tab;
                    t.setAttribute('aria-selected', String(active));
                    t.classList.toggle('border-cyan', active);
                    t.classList.toggle('bg-cyan/10', active);
                    t.classList.toggle('text-cyan', active);
                    t.classList.toggle('border-border', !active);
                    t.classList.toggle('text-text-secondary', !active);
                });
                var visible = 0;
                rows.forEach(function (row) {
                    var show = filter === 'all' || row.getAttribute('data-status-group') === filter;
                    // classList.toggle('hidden', ...) em vez do atributo/propriedade
                    // `hidden` nativo — a linha tem `class="flex ..."`, e o `.flex`
                    // do Tailwind tem a mesma especificidade do reset de `[hidden]`
                    // (que usa :where(), especificidade zero); a classe `.hidden`
                    // do próprio Tailwind vem depois no CSS compilado e vence.
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                if (empty) empty.classList.toggle('hidden', visible > 0);
            });
        });
    })();
</script>
