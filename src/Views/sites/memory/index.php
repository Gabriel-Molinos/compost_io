<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\Support\Csrf;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $lessons */
/** @var list<array<string, mixed>> $recentFeedback */

$activeTab = 'memory';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/memory';
?>
<p class="text-sm text-text-secondary">
    Lições duradouras deste site — escritas por você, nunca pela IA. Entram em toda geração,
    somadas ao feedback recente das últimas rejeições (que já entrava antes).
</p>

<section class="mt-6" aria-labelledby="h-nova">
    <h2 id="h-nova" class="font-display text-lg font-semibold text-text-primary">Nova lição</h2>
    <form method="post" action="<?= $base ?>" class="mt-3 grid gap-3 sm:max-w-xl">
        <?= Csrf::field() ?>
        <label class="text-sm">
            <span class="sr-only">Lição</span>
            <textarea name="lesson" rows="3" required placeholder="Ex.: Este site nunca usa títulos em formato de pergunta."
                      class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none"></textarea>
        </label>
        <button type="submit" class="justify-self-start rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Adicionar à memória
        </button>
    </form>
</section>

<?php if ($recentFeedback !== []): ?>
    <section class="mt-8" aria-labelledby="h-promover">
        <h2 id="h-promover" class="font-display text-lg font-semibold text-text-primary">Promover de rejeições recentes</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Vira uma lição duradoura com o texto pronto — você pode editar depois.
        </p>
        <ul class="mt-3 space-y-2">
            <?php foreach ($recentFeedback as $f): ?>
                <li class="flex items-center justify-between gap-4 rounded-lg border border-border bg-surface px-4 py-3">
                    <div>
                        <p class="text-sm text-text-primary">
                            <span class="text-text-muted">[<?= View::e(ArticleReviewService::REJECT_REASONS[$f['reason']] ?? $f['reason']) ?>]</span>
                            <?= View::e($f['justification']) ?>
                        </p>
                    </div>
                    <form method="post" action="<?= $base ?>/promote" class="shrink-0">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="feedback_id" value="<?= View::e($f['id']) ?>">
                        <button type="submit" class="rounded-md border border-cyan px-3 py-1.5 text-sm font-semibold text-cyan hover:bg-cyan/10">
                            Promover
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="mt-8" aria-labelledby="h-licoes">
    <h2 id="h-licoes" class="font-display text-lg font-semibold text-text-primary">Lições (<?= count($lessons) ?>)</h2>
    <?php if ($lessons === []): ?>
        <p class="mt-2 text-sm text-text-secondary">Nenhuma lição ainda.</p>
    <?php else: ?>
        <ul class="mt-3 space-y-2">
            <?php foreach ($lessons as $l): ?>
                <li class="rounded-lg border <?= $l['active'] ? 'border-border' : 'border-border opacity-60' ?> bg-surface p-4">
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm text-text-primary"><?= nl2br(View::e($l['lesson'])) ?></p>
                        <span class="shrink-0 text-xs <?= $l['active'] ? 'text-success' : 'text-text-muted' ?>">
                            <?= $l['active'] ? 'ativa' : 'desativada' ?>
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-text-muted">
                        <?= View::e(date('d/m/Y', strtotime((string) $l['created_at']))) ?>
                        <?php if (!empty($l['author'])): ?> · <?= View::e($l['author']) ?><?php endif; ?>
                        <?php if (!empty($l['source_feedback_id'])): ?> · promovida de uma rejeição<?php endif; ?>
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                        <details>
                            <summary class="cursor-pointer text-cyan hover:text-cyan-bright">Editar</summary>
                            <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>" class="mt-2 grid gap-2 sm:max-w-xl">
                                <?= Csrf::field() ?>
                                <textarea name="lesson" rows="3" required
                                          class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none"
                                ><?= View::e($l['lesson']) ?></textarea>
                                <button type="submit" class="justify-self-start rounded-md bg-cyan px-3 py-1.5 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
                                    Salvar
                                </button>
                            </form>
                        </details>
                        <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/toggle">
                            <?= Csrf::field() ?>
                            <button type="submit" class="text-text-secondary hover:text-text-primary">
                                <?= $l['active'] ? 'Desativar' : 'Reativar' ?>
                            </button>
                        </form>
                        <form method="post" action="<?= $base ?>/<?= View::e($l['id']) ?>/delete"
                              onsubmit="return confirm('Remover esta lição?');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="text-text-muted hover:text-danger">Remover</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
