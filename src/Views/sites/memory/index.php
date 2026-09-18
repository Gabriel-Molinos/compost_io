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
<div class="flex items-start justify-between gap-4">
    <p class="text-sm text-text-secondary">
        Lições duradouras deste site — escritas por você, nunca pela IA. Entram em toda geração,
        somadas ao feedback recente das últimas rejeições.
    </p>
    <div class="flex shrink-0 items-center gap-1.5 rounded-full bg-cyan/10 px-3 py-1 text-xs font-medium text-cyan">
        <span class="h-1.5 w-1.5 rounded-full bg-cyan"></span>
        <?= count($activeLessons) ?> ativa<?= count($activeLessons) === 1 ? '' : 's' ?>
    </div>
</div>

<section class="mt-6 overflow-hidden rounded-lg border border-border bg-surface" aria-labelledby="h-nova">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h2 id="h-nova" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('memory') ?></span>
            Nova lição
        </h2>
    </div>
    <form method="post" action="<?= $base ?>" class="p-5">
        <?= Csrf::field() ?>
        <label class="block" data-char-counter>
            <span class="sr-only">Lição</span>
            <textarea name="lesson" rows="4" required maxlength="500"
                      placeholder="Ex.: Este site nunca usa títulos em formato de pergunta."
                      class="w-full resize-y rounded-md border border-border bg-surface-2 px-3 py-2.5 text-sm text-text-primary
                             placeholder:text-text-muted focus:border-cyan focus:outline-none"></textarea>
            <p class="mt-1.5 text-right text-xs text-text-muted"><span data-char-count>0</span>/500</p>
        </label>
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            Adicionar à memória
        </button>
    </form>
</section>

<?php if ($recentFeedback !== []): ?>
    <section class="mt-6 overflow-hidden rounded-lg border border-warning/30 bg-surface" aria-labelledby="h-promover">
        <details>
            <summary class="flex cursor-pointer items-center justify-between gap-3 bg-warning/5 px-5 py-3">
                <span id="h-promover" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-warning/10 text-warning"><?= Icon::nav('memory') ?></span>
                    Promover de rejeições recentes
                </span>
                <span class="rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-semibold text-warning"><?= count($recentFeedback) ?></span>
            </summary>
            <div class="border-t border-border p-5">
                <p class="text-sm text-text-secondary">
                    Vira uma lição duradoura com o texto pronto — você pode editar depois.
                </p>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($recentFeedback as $f): ?>
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-surface-2/40 px-4 py-3">
                            <p class="min-w-0 flex-1 text-sm text-text-primary">
                                <span class="mr-1.5 inline-block rounded bg-warning/10 px-1.5 py-0.5 text-xs font-medium text-warning">
                                    <?= View::e(ArticleReviewService::REJECT_REASONS[$f['reason']] ?? $f['reason']) ?>
                                </span>
                                <?= View::e($f['justification']) ?>
                            </p>
                            <form method="post" action="<?= $base ?>/promote" class="shrink-0">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="feedback_id" value="<?= View::e($f['id']) ?>">
                                <button type="submit" class="btn btn-primary px-3 py-1.5 text-sm">
                                    Promover
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    </section>
<?php endif; ?>

<section class="mt-6" aria-labelledby="h-licoes">
    <h2 id="h-licoes" class="flex items-center gap-2 font-display text-lg font-semibold text-text-primary">
        <span class="text-cyan"><?= Icon::nav('memory') ?></span> Lições (<?= count($lessons) ?>)
    </h2>
    <?php if ($lessons === []): ?>
        <p class="mt-3 text-sm text-text-secondary">Nenhuma lição ainda.</p>
    <?php else: ?>
        <ul class="mt-3 space-y-3">
            <?php foreach ($lessons as $l): ?>
                <li class="overflow-hidden rounded-lg border-l-2 <?= $l['active'] ? 'border-l-cyan' : 'border-l-border' ?> border-y border-r border-border bg-surface <?= $l['active'] ? '' : 'opacity-60' ?>">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <p class="whitespace-pre-line text-sm text-text-primary"><?= View::e($l['lesson']) ?></p>
                            <span class="shrink-0"><?= Labels::activeBadge((bool) $l['active'], feminine: true) ?></span>
                        </div>
                        <p class="mt-2 flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
                            <span><?= View::e(date('d/m/Y', strtotime((string) $l['created_at']))) ?></span>
                            <?php if (!empty($l['author'])): ?>
                                <span class="flex items-center gap-1">
                                    · <?= Avatar::html(null, (string) $l['author'], size: 'h-4 w-4', radius: 'rounded-full', textSize: 'text-[8px]') ?>
                                    <?= View::e($l['author']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($l['source_feedback_id'])): ?><span>· promovida de uma rejeição</span><?php endif; ?>
                        </p>

                        <div class="mt-3 flex items-center gap-1 border-t border-border pt-3" data-lesson-actions>
                            <button type="button" data-edit-toggle
                                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-surface-2 hover:text-cyan">
                                <?= $editIcon ?> Editar
                            </button>
                            <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/toggle">
                                <?= Csrf::field() ?>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-surface-2 hover:text-text-primary">
                                    <?= $toggleIcon ?> <?= $l['active'] ? 'Desativar' : 'Reativar' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/delete"
                                  data-confirm="Remover esta lição?">
                                <?= Csrf::field() ?>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-text-secondary transition-colors hover:bg-danger/10 hover:text-danger">
                                    <?= $deleteIcon ?> Remover
                                </button>
                            </form>
                        </div>
                    </div>

                    <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>" data-edit-form hidden
                          class="border-t border-border bg-surface-2/40 p-4">
                        <?= Csrf::field() ?>
                        <label class="block text-xs font-medium text-text-secondary">
                            Editar texto da lição
                            <textarea name="lesson" rows="4" required
                                      class="mt-1.5 w-full resize-y rounded-md border border-border bg-surface px-3 py-2.5 text-sm text-text-primary focus:border-cyan focus:outline-none"
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
