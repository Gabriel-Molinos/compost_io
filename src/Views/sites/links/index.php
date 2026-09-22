<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<array{article_id:int, title:string, status:string, ambiguous_links:list<string>, link_rot_dead_urls:list<string>, backlink_suggestions:list<array<string,mixed>>}> $flagged */

$activeTab = 'links';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . View::e($site['id']);

$totalAmbiguous = 0;
$totalDead = 0;
$totalBacklinks = 0;
foreach ($flagged as $f) {
    $totalAmbiguous += count($f['ambiguous_links']);
    $totalDead += count($f['link_rot_dead_urls']);
    $totalBacklinks += count($f['backlink_suggestions']);
}
$totalLinks = $totalAmbiguous + $totalDead + $totalBacklinks;
?>
<div class="flex items-start gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('links') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Central de Links</h1>
        <p class="mt-1 text-sm text-text-secondary">
            Links que a verificação automática não conseguiu confirmar sozinha — sem precisar editar HTML.
            Cada um vira link de verdade no artigo só depois que você agir aqui.
        </p>
    </div>
</div>

<?php if ($flagged !== []): ?>
    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <div class="article-card article-card--warning rounded-xl" style="--card-enter: 0ms">
            <div class="flex items-center gap-3 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"><?= Icon::nav('alert') ?></span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Não confirmados</p>
                    <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalAmbiguous ?></p>
                </div>
            </div>
        </div>
        <div class="article-card article-card--danger rounded-xl" style="--card-enter: 50ms">
            <div class="flex items-center gap-3 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-danger/15 text-danger"><?= Icon::nav('close') ?></span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Quebraram depois</p>
                    <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalDead ?></p>
                </div>
            </div>
        </div>
        <div class="article-card article-card--cyan rounded-xl" style="--card-enter: 100ms">
            <div class="flex items-center gap-3 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan/15 text-cyan"><?= Icon::nav('links') ?></span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Oportunidades</p>
                    <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $totalBacklinks ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($flagged === []): ?>
    <div class="mt-6 rounded-xl border border-success/40 bg-success/5 p-8 text-center">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-success/15 text-success [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('check') ?></span>
        <p class="mt-3 text-sm font-medium text-text-primary">Nenhum link pendente agora.</p>
        <p class="mt-1 text-xs text-text-muted">Assim que a geração ou a revarredura periódica encontrar algo, aparece aqui.</p>
    </div>
<?php else: ?>
    <p class="mt-5 text-xs text-text-muted"><?= $totalLinks ?> link(s) pendente(s) em <?= count($flagged) ?> artigo(s).</p>

    <div class="mt-3 space-y-4">
        <?php foreach ($flagged as $f): ?>
            <?php
            $articleBase = $base . '/production/' . $f['article_id'];
            $isPublished = $f['status'] === 'PUBLISHED';
            ?>
            <section class="overflow-hidden rounded-xl border border-border bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface-2/40 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <a href="<?= $articleBase ?>" class="font-display text-sm font-semibold text-text-primary hover:text-cyan">
                            <?= View::e($f['title'] ?: 'Rascunho #' . $f['article_id']) ?>
                        </a>
                        <?= Labels::articleStatusBadge($f['status']) ?>
                    </div>
                    <a href="<?= $articleBase ?>" class="text-xs font-medium text-cyan hover:text-cyan-bright">Ver artigo →</a>
                </div>

                <ul class="space-y-3 p-4">
                    <?php foreach ($f['ambiguous_links'] as $url): ?>
                        <li class="rounded-lg border border-warning/30 bg-warning/5 p-3">
                            <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warning">
                                <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('alert') ?></span>
                                Não confirmado automaticamente
                            </div>
                            <a href="<?= View::e($url) ?>" target="_blank" rel="noopener" class="mt-1.5 block break-all text-sm text-cyan hover:text-cyan-bright"><?= View::e($url) ?></a>
                            <p class="mt-1 text-xs text-text-muted">Bloqueio comum de bot em domínio grande — pode ser um link real. Abra e confira.</p>

                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/confirm">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="url" value="<?= View::e($url) ?>">
                                    <button type="submit" class="btn btn-success px-3 py-1.5 text-xs">
                                        <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                                        Confirmar que está ok
                                    </button>
                                </form>
                                <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/remove" data-confirm="Remover este link? O texto continua, só deixa de ser um link.">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="url" value="<?= View::e($url) ?>">
                                    <button type="submit" class="btn btn-danger px-3 py-1.5 text-xs">
                                        Remover link
                                    </button>
                                </form>
                                <details class="text-xs">
                                    <summary class="cursor-pointer rounded-md border border-border px-3 py-1.5 font-medium text-text-secondary hover:border-cyan hover:text-text-primary">
                                        Trocar URL
                                    </summary>
                                    <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/replace" class="mt-2 flex flex-wrap items-center gap-2">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="old_url" value="<?= View::e($url) ?>">
                                        <input type="url" name="new_url" required placeholder="https://..."
                                               class="min-w-[16rem] flex-1 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-text-primary focus:border-cyan focus:outline-none">
                                        <button type="submit" class="btn btn-primary px-3 py-1.5">Salvar</button>
                                    </form>
                                </details>
                            </div>
                        </li>
                    <?php endforeach; ?>

                    <?php foreach ($f['link_rot_dead_urls'] as $url): ?>
                        <li class="rounded-lg border border-danger/30 bg-danger/5 p-3">
                            <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-danger">
                                <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('close') ?></span>
                                Quebrou depois de publicar<?= $isPublished ? ' — post ao vivo' : '' ?>
                            </div>
                            <p class="mt-1.5 break-all text-sm text-text-secondary line-through"><?= View::e($url) ?></p>
                            <p class="mt-1 text-xs text-text-muted">Estava vivo quando publicou; a revarredura periódica achou fora do ar agora.</p>

                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/remove" data-confirm="Remover este link? O texto continua, só deixa de ser um link.">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="url" value="<?= View::e($url) ?>">
                                    <button type="submit" class="btn btn-danger px-3 py-1.5 text-xs">
                                        Remover link
                                    </button>
                                </form>
                                <details class="text-xs">
                                    <summary class="cursor-pointer rounded-md border border-border px-3 py-1.5 font-medium text-text-secondary hover:border-cyan hover:text-text-primary">
                                        Trocar URL
                                    </summary>
                                    <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/replace" class="mt-2 flex flex-wrap items-center gap-2">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="old_url" value="<?= View::e($url) ?>">
                                        <input type="url" name="new_url" required placeholder="https://..."
                                               class="min-w-[16rem] flex-1 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-text-primary focus:border-cyan focus:outline-none">
                                        <button type="submit" class="btn btn-primary px-3 py-1.5">Salvar</button>
                                    </form>
                                </details>
                                <?php if ($isPublished): ?>
                                    <span class="text-xs text-text-muted">Corrigir aqui já reenvia pro WordPress.</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>

                    <?php foreach ($f['backlink_suggestions'] as $s): $anchor = (string) ($s['anchor_text'] ?? ''); $url = (string) ($s['url'] ?? ''); ?>
                        <li class="rounded-lg border border-cyan/30 bg-cyan/5 p-3">
                            <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-cyan">
                                <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('links') ?></span>
                                Oportunidade de link interno
                            </div>
                            <p class="mt-1.5 text-sm text-text-primary">
                                Linkar "<span class="font-medium"><?= View::e($anchor) ?></span>" pro artigo
                                <span class="font-medium"><?= View::e((string) ($s['from_title'] ?? '')) ?></span>
                            </p>
                            <p class="mt-1 text-xs text-text-muted">Sugestão da IA a partir do artigo mais recente — nunca aplicada sozinha.</p>

                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/backlink/apply">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="anchor_text" value="<?= View::e($anchor) ?>">
                                    <input type="hidden" name="url" value="<?= View::e($url) ?>">
                                    <button type="submit" class="btn btn-primary px-3 py-1.5 text-xs">
                                        Aplicar
                                    </button>
                                </form>
                                <form method="post" action="<?= $base ?>/links/<?= $f['article_id'] ?>/backlink/dismiss">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="anchor_text" value="<?= View::e($anchor) ?>">
                                    <input type="hidden" name="url" value="<?= View::e($url) ?>">
                                    <button type="submit" class="btn btn-secondary btn-hover-danger px-3 py-1.5 text-xs">
                                        Dispensar
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
