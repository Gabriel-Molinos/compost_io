<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Pixelito;
use App\Support\PixelitoGuide;
use App\View;

/**
 * Pixelito, o ajudante do canto inferior direito (pedido do responsável, 2026-09-25).
 * Incluído por layout/base.php só pra quem está logado. Tudo renderizado aqui no
 * servidor — o JS (assets/js/pixelito.js) só abre/fecha, filtra e expande; nenhuma
 * requisição, nenhuma IA. Conteúdo do guia: src/Support/PixelitoGuide.php.
 *
 * Espera do escopo do base.php: $site (array|null), $sidebar['firstSiteId'], $tourSiteId.
 */

$pixelitoSiteId = is_array($site ?? null) && isset($site['id']) ? (int) $site['id'] : ($sidebar['firstSiteId'] ?? null);
$pixelitoTopics = PixelitoGuide::topics($pixelitoSiteId);
$pixelitoN = 0;
?>
<div class="pixelito" data-pixelito>
    <section id="pixelito-panel" class="pixelito-panel" data-pixelito-panel role="dialog" aria-label="Ajuda do Pixelito" hidden>
        <header class="pixelito-panel-head">
            <?= Pixelito::bubble('falando', 'lg') ?>
            <div class="min-w-0 flex-1">
                <p class="pixelito-panel-title">Oi, eu sou o Pixelito!</p>
                <p class="pixelito-panel-sub">Alguma dúvida sobre o COMPOST? Escolha uma pergunta ou busque.</p>
            </div>
            <button type="button" class="pixelito-close" data-pixelito-close aria-label="Fechar a ajuda">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="pixelito-search">
            <span aria-hidden="true" class="pixelito-search-icon"><?= Icon::nav('search') ?></span>
            <input type="search" data-pixelito-search autocomplete="off" placeholder="Buscar uma dúvida…" aria-label="Buscar uma dúvida">
        </div>

        <div class="pixelito-body" data-pixelito-body>
            <?php foreach ($pixelitoTopics as $topic): ?>
                <section data-pixelito-topic>
                    <h3 class="pixelito-topic"><?= View::e($topic['topic']) ?></h3>
                    <ul>
                        <?php foreach ($topic['items'] as $item): ?>
                            <?php $pixelitoN++; ?>
                            <li data-pixelito-item data-text="<?= View::e(mb_strtolower($item['q'] . ' ' . $item['a'])) ?>">
                                <button type="button" class="pixelito-q hover-card bg-surface" data-pixelito-q aria-expanded="false" aria-controls="pixelito-a-<?= $pixelitoN ?>">
                                    <span><?= View::e($item['q']) ?></span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div id="pixelito-a-<?= $pixelitoN ?>" class="pixelito-a" data-pixelito-a hidden>
                                    <p><?= View::e($item['a']) ?></p>
                                    <?php if ($item['link'] !== null): ?>
                                        <a href="<?= View::e($item['link']) ?>" class="btn btn-secondary pixelito-go px-3 py-1.5 text-xs">
                                            <?= View::e((string) $item['linkLabel']) ?>
                                            <span aria-hidden="true"><?= Icon::nav('arrow') ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <div class="pixelito-empty" data-pixelito-empty hidden>
                <?= Pixelito::bubble('sem-animo', 'lg') ?>
                <p>Não achei essa dúvida por aqui.</p>
                <a href="/feedback" class="btn btn-secondary pixelito-go px-3 py-1.5 text-xs">Conte para a equipe o que faltou <span aria-hidden="true"><?= Icon::nav('arrow') ?></span></a>
            </div>
        </div>

        <footer class="pixelito-panel-foot">
            <?php if ($tourSiteId !== null): ?>
                <a href="/?tour=1"><span aria-hidden="true"><?= Icon::nav('help') ?></span> Rever o tutorial</a>
            <?php endif; ?>
            <a href="/compliance"><span aria-hidden="true"><?= Icon::nav('shield') ?></span> Regras de compliance</a>
        </footer>
    </section>

    <p class="pixelito-hint" data-pixelito-hint hidden>Precisa de ajuda? Clique em mim!</p>

    <button type="button" class="pixelito-fab" data-pixelito-fab aria-expanded="false" aria-controls="pixelito-panel" aria-label="Abrir a ajuda do Pixelito" title="Precisa de ajuda? Pergunte ao Pixelito">
        <?= Pixelito::bubble('normal', 'xl') ?>
    </button>
</div>
