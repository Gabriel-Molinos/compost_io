<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $lessons */
/** @var list<array<string, mixed>> $recentFeedback */

$activeTab = 'memory';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/memory';
$activeLessons = array_filter($lessons, static fn ($l) => (bool) $l['active']);

$editIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/>'
    . '<path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
$toggleIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V3"/>'
    . '<path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>';
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('memory') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Memória editorial</h1>
            <p class="mt-1 max-w-2xl text-sm text-text-secondary">
                Lições duradouras deste site — escritas por você, nunca pela IA. Entram em toda geração,
                somadas ao feedback recente das últimas rejeições.
            </p>
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-1.5 rounded-full bg-cyan/10 px-3 py-1.5 text-xs font-semibold text-cyan">
        <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>
        <?= count($activeLessons) ?> ativa<?= count($activeLessons) === 1 ? '' : 's' ?>
    </div>
</div>

<?php // ── Escrever uma lição do zero ── ?>
<section class="article-card article-card--cyan mt-6 rounded-2xl" aria-labelledby="h-nova" style="--card-enter: 0ms">
    <span class="article-card-fx" aria-hidden="true"></span>
    <div class="p-5">
        <h2 id="h-nova" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('plus') ?></span>
            Nova lição
        </h2>
        <form method="post" action="<?= $base ?>" class="mt-4">
            <?= Csrf::field() ?>
            <label class="block" data-char-counter>
                <span class="sr-only">Lição</span>
                <textarea name="lesson" rows="4" required maxlength="500"
                          placeholder="Ex.: Este site nunca usa títulos em formato de pergunta."
                          class="w-full resize-y rounded-xl border border-border bg-surface-2 px-3.5 py-3 text-sm text-text-primary
                                 shadow-[inset_0_1px_3px_rgba(0,0,0,.3)] placeholder:text-text-muted focus:border-cyan focus:outline-none"></textarea>
                <p class="mt-1.5 text-right text-xs text-text-muted"><span data-char-count>0</span>/500</p>
            </label>
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
                Adicionar à memória
            </button>
        </form>
    </div>
</section>

<?php if ($recentFeedback !== []): ?>
    <?php // ── Promover uma rejeição recente a lição duradoura ── ?>
    <details class="article-card article-card--warning mt-6 rounded-2xl" style="--card-enter: 60ms">
        <span class="article-card-fx" aria-hidden="true"></span>
        <span class="article-card-tags">
            <span class="article-card-tag article-card-tag--warning"><?= count($recentFeedback) ?> pendente<?= count($recentFeedback) === 1 ? '' : 's' ?></span>
        </span>
        <?php // Marcador "▸" que gira ao abrir já é padrão global (summary::before, src/styles/input.css)
               // — mesmo usado no editor de corpo e em "reagendar"; não precisa de um segundo indicador aqui. ?>
        <summary class="flex items-center gap-2 p-5 pt-7 font-display text-base font-semibold text-text-primary">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('memory') ?></span>
            Promover de rejeições recentes
        </summary>
        <div class="relative border-t border-border/60 p-5">
            <p class="text-sm text-text-secondary">
                Vira uma lição duradoura com o texto pronto — você pode editar depois.
            </p>
            <ul class="mt-3 space-y-2.5">
                <?php foreach ($recentFeedback as $f): ?>
                    <li class="hover-card flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface-2/40 px-4 py-3">
                        <p class="min-w-0 flex-1 text-sm text-text-primary">
                            <span class="mr-1.5 inline-block rounded-full bg-warning/10 px-2 py-0.5 text-xs font-semibold text-warning">
                                <?= View::e(ArticleReviewService::REJECT_REASONS[$f['reason']] ?? $f['reason']) ?>
                            </span>
                            <?= View::e($f['justification']) ?>
                        </p>
                        <form method="post" action="<?= $base ?>/promote" class="relative z-10 shrink-0">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="feedback_id" value="<?= View::e($f['id']) ?>">
                            <button type="submit" class="btn btn-primary px-3 py-1.5 text-sm">
                                <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                                Promover
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>
<?php endif; ?>

<?php // ── Lições já guardadas ── ?>
<section class="mt-8" aria-labelledby="h-licoes">
    <h2 id="h-licoes" class="flex items-center gap-2 font-display text-lg font-semibold text-text-primary">
        Lições <span class="rounded-full bg-white/5 px-2 py-0.5 font-mono text-sm text-text-muted"><?= count($lessons) ?></span>
    </h2>
    <?php if ($lessons === []): ?>
        <div class="mt-4 rounded-2xl border border-dashed border-border-strong p-10 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan/10 text-cyan [&>svg]:h-7 [&>svg]:w-7"><?= Icon::nav('memory') ?></span>
            <p class="mt-4 font-display text-lg font-semibold text-text-primary">Nenhuma lição ainda</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-text-secondary">
                Escreva a primeira acima, ou promova uma rejeição recente quando aparecer uma.
            </p>
        </div>
    <?php else: ?>
        <ul class="mt-4 space-y-3">
            <?php foreach ($lessons as $i => $l): ?>
                <?php $active = (bool) $l['active']; ?>
                <li class="article-card hover-card <?= Labels::articleCardTone($active ? 'cyan' : 'muted') ?> rounded-2xl"
                    style="--card-enter: <?= min($i, 10) * 45 ?>ms">
                    <span class="article-card-fx" aria-hidden="true"></span>
                    <span class="article-card-tags">
                        <span class="article-card-tag <?= $active ? 'article-card-tag--cyan' : 'article-card-tag--muted' ?>">
                            <?php if ($active): ?><span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span><?php endif; ?>
                            <?= $active ? 'Ativa' : 'Inativa' ?>
                        </span>
                        <?php if (!empty($l['source_feedback_id'])): ?>
                            <span class="article-card-tag article-card-tag--warning"><?= Icon::nav('feedback') ?>Promovida de rejeição</span>
                        <?php endif; ?>
                    </span>

                    <div class="p-5 pt-7">
                        <p class="whitespace-pre-line text-sm text-text-primary"><?= View::e($l['lesson']) ?></p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-text-muted">
                            <span class="article-card-fact"><span class="article-card-icon"><?= Icon::nav('clock') ?></span><?= View::e(date('d/m/Y', strtotime((string) $l['created_at']))) ?></span>
                            <?php if (!empty($l['author'])): ?>
                                <span class="flex items-center gap-1.5">
                                    <?= Avatar::html(null, (string) $l['author'], size: 'h-4 w-4', radius: 'rounded-full', textSize: 'text-[8px]') ?>
                                    <?= View::e($l['author']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="relative z-10 mt-3 flex items-center gap-1 border-t border-border/60 pt-3">
                            <button type="button" data-edit-toggle
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-surface-2 hover:text-cyan">
                                <?= $editIcon ?> Editar
                            </button>
                            <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/toggle">
                                <?= Csrf::field() ?>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-surface-2 hover:text-text-primary">
                                    <?= $toggleIcon ?> <?= $active ? 'Desativar' : 'Reativar' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/delete"
                                  data-confirm="Remover esta lição?">
                                <?= Csrf::field() ?>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-danger/10 hover:text-danger">
                                    <?= $deleteIcon ?> Remover
                                </button>
                            </form>
                        </div>

                        <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>" data-edit-form hidden
                              class="relative z-10 mt-4 -mx-5 -mb-5 rounded-b-2xl border-t border-border/60 bg-surface-2/40 p-4">
                            <?= Csrf::field() ?>
                            <label class="block text-xs font-medium text-text-secondary">
                                Editar texto da lição
                                <textarea name="lesson" rows="4" required
                                          class="mt-1.5 w-full resize-y rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-text-primary focus:border-cyan focus:outline-none"
                                ><?= View::e($l['lesson']) ?></textarea>
                            </label>
                            <div class="mt-2 flex gap-2">
                                <button type="submit" class="btn btn-primary px-3 py-1.5 text-sm">
                                    Salvar
                                </button>
                                <button type="button" data-edit-cancel
                                        class="btn btn-secondary px-3 py-1.5 text-sm">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script>
    // Progressive enhancement: alterna a lição entre "linha de ações" e
    // "formulário de edição" — troca [hidden], não precisa de <details>
    // aninhado dentro da linha de botões (era isso que espremia o textarea
    // de edição num espaço minúsculo antes).
    (function () {
        document.querySelectorAll('[data-edit-toggle]').forEach(function (btn) {
            var li = btn.closest('li');
            var form = li && li.querySelector('[data-edit-form]');
            if (!form) return;
            btn.addEventListener('click', function () {
                form.hidden = !form.hidden;
                if (!form.hidden) form.querySelector('textarea').focus();
            });
        });
        document.querySelectorAll('[data-edit-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = btn.closest('[data-edit-form]');
                if (form) form.hidden = true;
            });
        });
    })();

    // Contador de caracteres do textarea de nova lição.
    (function () {
        var wrap = document.querySelector('[data-char-counter]');
        if (!wrap) return;
        var textarea = wrap.querySelector('textarea');
        var count = wrap.querySelector('[data-char-count]');
        var update = function () { count.textContent = String(textarea.value.length); };
        textarea.addEventListener('input', update);
        update();
    })();
</script>
