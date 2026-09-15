<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var array<string, mixed> $site */
/** @var list<array<string, mixed>> $sources */

$activeTab = 'sources';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . $site['id'] . '/sources';

$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
?>
<div class="flex items-start justify-between gap-4">
    <p class="text-sm text-text-secondary">
        Fontes confiáveis deste site — cadastradas por você, nunca pela IA. A IA hoje
        <strong>não tem busca na web de verdade</strong>: o passo de Pesquisa levanta fatos e
        URLs da própria memória de treinamento, por isso as fontes aqui entram como
        <strong>prioridade</strong> antes disso. Ainda pode citar outras fontes conhecidas, mas
        toda URL — daqui ou não — passa por checagem real antes de publicar.
    </p>
    <div class="flex shrink-0 items-center gap-1.5 rounded-full bg-cyan/10 px-3 py-1 text-xs font-medium text-cyan">
        <span class="h-1.5 w-1.5 rounded-full bg-cyan"></span>
        <?= count($sources) ?> fonte<?= count($sources) === 1 ? '' : 's' ?>
    </div>
</div>

<section class="mt-6 overflow-hidden rounded-lg border border-border bg-surface" aria-labelledby="h-nova">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h2 id="h-nova" class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('sources') ?></span>
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
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Adicionar
        </button>
    </form>
</section>

<section class="mt-6" aria-labelledby="h-fontes">
    <h2 id="h-fontes" class="flex items-center gap-2 font-display text-lg font-semibold text-text-primary">
        <span class="text-cyan"><?= Icon::nav('sources') ?></span> Fontes (<?= count($sources) ?>)
    </h2>
    <?php if ($sources === []): ?>
        <p class="mt-3 text-sm text-text-secondary">Nenhuma fonte cadastrada ainda.</p>
    <?php else: ?>
        <ul class="mt-3 space-y-2">
            <?php foreach ($sources as $s): ?>
                <li class="flex items-center justify-between gap-4 rounded-lg border-l-2 border-l-cyan border-y border-r border-border bg-surface px-4 py-3">
                    <div class="min-w-0">
                        <a href="<?= View::e($s['url']) ?>" target="_blank" rel="noopener noreferrer"
                           class="break-all font-medium text-text-primary hover:text-cyan"><?= View::e($s['url']) ?></a>
                        <?php if (!empty($s['note'])): ?>
                            <p class="mt-1 text-xs text-text-secondary"><?= View::e($s['note']) ?></p>
                        <?php endif; ?>
                        <p class="mt-1 text-xs text-text-muted"><?= View::e(date('d/m/Y', strtotime((string) $s['created_at']))) ?></p>
                    </div>
                    <form method="post" action="<?= $base ?>/<?= View::e($s['id']) ?>/delete"
                          data-confirm="Remover esta fonte?">
                        <?= Csrf::field() ?>
                        <button type="submit" title="Remover" aria-label="Remover"
                                class="shrink-0 rounded-md p-1.5 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">
                            <?= $deleteIcon ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
