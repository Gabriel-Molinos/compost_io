<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $sources */
/** @var list<array{article_id:int, title:string, updated_at:string, gaps:list<string>, hints:array<string,array{queries:list<string>,keywords:list<string>,source_types:list<string>}>}> $researchGaps */

$activeTab = 'sources';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/sources';

$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
$copyIcon = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/>'
    . '<path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>';

$totalGaps = 0;
foreach ($researchGaps as $g) {
    $totalGaps += count($g['gaps']);
}
?>
<div class="flex items-start gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('sources') ?></span>
    <div class="min-w-0">
        <h1 class="font-display text-2xl font-bold text-text-primary">Fontes e lacunas de pesquisa</h1>
        <p class="mt-1 text-sm text-text-secondary">
            Fontes confiáveis cadastradas por você — a IA <strong class="text-text-primary">não tem busca na web
            de verdade</strong>, então o passo de Pesquisa prioriza o que está aqui antes de recorrer à própria
            memória. Toda URL, daqui ou não, ainda passa por checagem real antes de publicar.
        </p>
    </div>
</div>

<div class="mt-6 grid gap-3 sm:grid-cols-2">
    <div class="article-card article-card--cyan rounded-xl" style="--card-enter: 0ms">
        <div class="flex items-center gap-3 p-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan/15 text-cyan"><?= Icon::nav('sources') ?></span>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Fontes cadastradas</p>
                <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= count($sources) ?></p>
            </div>
        </div>
    </div>
    <div class="article-card article-card--warning rounded-xl" style="--card-enter: 50ms">
        <div class="flex items-center gap-3 p-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"><?= Icon::nav('search') ?></span>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Lacunas de pesquisa</p>
                <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalGaps ?></p>
            </div>
        </div>
    </div>
</div>

<section class="mt-6 overflow-hidden rounded-xl border border-border bg-surface" aria-labelledby="h-nova">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h2 id="h-nova" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('plus') ?></span>
            Nova fonte
        </h2>
    </div>
    <form method="post" action="<?= $base ?>" class="flex flex-wrap items-end gap-3 p-5">
        <?= Csrf::field() ?>
        <label class="min-w-[16rem] flex-1 text-sm">
            <span class="block font-medium text-text-secondary">URL</span>
            <input type="url" name="url" required maxlength="2048" placeholder="https://..."
                   class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-primary
                          placeholder:text-text-muted focus:border-cyan focus:outline-none">
        </label>
        <label class="min-w-[16rem] flex-1 text-sm">
            <span class="block font-medium text-text-secondary">Nota (opcional)</span>
            <input type="text" name="note" maxlength="500" placeholder="Ex.: dados oficiais de inflação"
                   class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-primary
                          placeholder:text-text-muted focus:border-cyan focus:outline-none">
        </label>
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
            Adicionar
        </button>
    </form>
</section>

<section class="mt-6" aria-labelledby="h-fontes">
    <h2 id="h-fontes" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('sources') ?></span>
        Fontes cadastradas (<?= count($sources) ?>)
    </h2>
    <?php if ($sources === []): ?>
        <div class="mt-3 rounded-xl border border-dashed border-border-strong p-6 text-center">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-border/40 text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('sources') ?></span>
            <p class="mt-3 text-sm text-text-secondary">Nenhuma fonte cadastrada ainda — adicione uma acima.</p>
        </div>
    <?php else: ?>
        <ul class="mt-3 space-y-2.5">
            <?php foreach ($sources as $i => $s): ?>
                <li class="hover-card flex items-start justify-between gap-3 rounded-xl border border-border bg-surface-2/40 px-4 py-3"
                    style="animation: fade-in-up 240ms ease backwards; animation-delay: <?= min($i, 10) * 40 ?>ms">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan/10 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('globe') ?></span>
                        <div class="min-w-0">
                            <a href="<?= View::e($s['url']) ?>" target="_blank" rel="noopener noreferrer"
                               class="break-all text-sm font-medium text-text-primary hover:text-cyan"><?= View::e($s['url']) ?></a>
                            <?php if (!empty($s['note'])): ?>
                                <p class="mt-1 text-xs text-text-secondary"><?= View::e($s['note']) ?></p>
                            <?php endif; ?>
                            <p class="mt-1 text-[11px] text-text-muted">Adicionada em <?= View::e(date('d/m/Y', strtotime((string) $s['created_at']))) ?></p>
                        </div>
                    </div>
                    <form method="post" action="<?= $base ?>/<?= View::e($s['id']) ?>/delete"
                          data-confirm="Remover esta fonte?">
                        <?= Csrf::field() ?>
                        <button type="submit" title="Remover" aria-label="Remover"
                                class="shrink-0 rounded-full p-2 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">
                            <?= $deleteIcon ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="mt-8" aria-labelledby="h-lacunas">
    <h2 id="h-lacunas" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('search') ?></span>
        Lacunas de pesquisa (<?= $totalGaps ?>)
    </h2>
    <p class="mt-1 text-sm text-text-secondary">
        O que a IA não conseguiu confirmar em fonte confiável ao pesquisar. Clique em
        <strong class="text-text-primary">"Sugerir buscas"</strong> pra receber buscas prontas, palavras-chave e o
        tipo de fonte mais provável de ter esse dado — depois cadastre a URL que achar na lista acima.
    </p>

    <?php if ($researchGaps === []): ?>
        <div class="mt-3 rounded-xl border border-dashed border-border-strong p-6 text-center">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-border/40 text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('check') ?></span>
            <p class="mt-3 text-sm text-text-secondary">Nenhuma lacuna registrada — a pesquisa não sentiu falta de nada até agora.</p>
        </div>
    <?php else: ?>
        <div class="mt-4 space-y-4">
            <?php foreach ($researchGaps as $g): ?>
                <article class="overflow-hidden rounded-xl border border-warning/30 bg-surface">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface-2/40 px-4 py-2.5">
                        <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($g['article_id']) ?>"
                           class="font-medium text-text-primary hover:text-cyan"><?= View::e($g['title'] ?: 'Rascunho #' . $g['article_id']) ?></a>
                        <span class="text-xs text-text-muted"><?= View::e(date('d/m/Y', strtotime($g['updated_at']))) ?></span>
                    </div>
                    <ul class="divide-y divide-border">
                        <?php foreach ($g['gaps'] as $gapIndex => $gap): ?>
                            <?php $hint = $g['hints'][(string) $gapIndex] ?? null; ?>
                            <li class="p-4">
                                <p class="text-sm text-text-secondary"><?= View::e($gap) ?></p>

                                <?php if ($hint === null): ?>
                                    <form method="post"
                                          action="/sites/<?= View::e($site['id']) ?>/sources/gaps/<?= View::e($g['article_id']) ?>/<?= View::e($gapIndex) ?>/suggest"
                                          class="mt-3"
                                          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Pensando…';">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="gap_text" value="<?= View::e($gap) ?>">
                                        <button type="submit" class="btn btn-secondary px-3 py-1.5 text-xs">
                                            <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('search') ?></span>
                                            Sugerir buscas
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                        <?php if ($hint['queries'] !== []): ?>
                                            <div class="rounded-lg border border-border bg-surface-2/40 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Buscas prontas</p>
                                                <ul class="mt-2 space-y-1.5">
                                                    <?php foreach ($hint['queries'] as $q): ?>
                                                        <li class="flex items-center gap-1.5">
                                                            <code class="min-w-0 flex-1 truncate rounded bg-surface px-2 py-1.5 text-[11px] text-text-secondary"><?= View::e($q) ?></code>
                                                            <button type="button" data-copy="<?= View::e($q) ?>" title="Copiar" aria-label="Copiar busca"
                                                                    class="shrink-0 rounded-md p-1.5 text-text-muted transition-colors hover:bg-surface hover:text-cyan">
                                                                <?= $copyIcon ?>
                                                            </button>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <div class="space-y-3">
                                            <?php if ($hint['keywords'] !== []): ?>
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Palavras-chave</p>
                                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                        <?php foreach ($hint['keywords'] as $kw): ?>
                                                            <span class="rounded-full border border-border bg-surface-2 px-2.5 py-1 text-[11px] text-text-secondary"><?= View::e($kw) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($hint['source_types'] !== []): ?>
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Tipo de fonte esperado</p>
                                                    <ul class="mt-1.5 space-y-1 text-xs text-text-secondary">
                                                        <?php foreach ($hint['source_types'] as $st): ?>
                                                            <li class="flex items-start gap-1.5">
                                                                <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-warning"></span><?= View::e($st) ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="post"
                                          action="/sites/<?= View::e($site['id']) ?>/sources/gaps/<?= View::e($g['article_id']) ?>/<?= View::e($gapIndex) ?>/suggest"
                                          class="mt-3"
                                          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Pensando…';">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="gap_text" value="<?= View::e($gap) ?>">
                                        <button type="submit" class="text-xs font-medium text-text-muted hover:text-cyan">
                                            Sugerir de novo
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
    // Progressive enhancement: copiar uma busca sugerida sem sair da página.
    (function () {
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy') || '';
                var done = function () {
                    var original = btn.innerHTML;
                    btn.textContent = 'Copiado!';
                    btn.classList.add('text-cyan');
                    setTimeout(function () {
                        btn.innerHTML = original;
                        btn.classList.remove('text-cyan');
                    }, 1200);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                }
            });
        });
    })();
</script>
