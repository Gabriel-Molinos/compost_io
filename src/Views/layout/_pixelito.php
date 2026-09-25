<?php

declare(strict_types=1);

use App\Support\Icon;
use App\Support\Pixelito;
use App\Support\PixelitoGuide;
use App\View;

/**
 * Pixelito, o ajudante do canto inferior direito (pedido do responsável, 2026-09-25).
 * Incluído por layout/base.php só pra quem está logado.
 *
 * Funciona como um CHAT simulado (pedido 2026-09-25): a pessoa toca numa pergunta,
 * ela vira a mensagem dela, o Pixelito "digita" e a resposta pronta aparece letra por
 * letra. Não há IA nem requisição — as perguntas/respostas vêm daqui, renderizadas no
 * servidor (src/Support/PixelitoGuide.php), e assets/js/pixelito.js só encena a conversa.
 *
 * REGRA (pedido 2026-09-25): só existe UM Pixelito "vivo" na tela por vez. Com o chat aberto, é o do
 * TOPO do painel que fala (troca de expressão); os avatares das mensagens são só uma marca fixa de quem
 * falou; e o botão do canto vira um "X" (sem carinha). Nos avisos e no tutorial ele "vai" pra lá e a
 * bolinha do canto se retira — ver notification-toast.js / tour.js e o CSS html.pixelito-away-*.
 *
 * Espera do escopo do base.php: $site (array|null), $sidebar['firstSiteId'], $tourSiteId.
 */

$pixelitoSiteId = is_array($site ?? null) && isset($site['id']) ? (int) $site['id'] : ($sidebar['firstSiteId'] ?? null);
$pixelitoTopics = PixelitoGuide::topics($pixelitoSiteId);
?>
<div class="pixelito" data-pixelito>
    <section id="pixelito-panel" class="pixelito-panel" data-pixelito-panel role="dialog" aria-label="Conversa com o Pixelito" hidden>
        <header class="pixelito-panel-head">
            <?php // O Pixelito "vivo" do painel: pixelito.js troca a expressão dele (falando / normal / triste). ?>
            <?= Pixelito::bubble('normal', 'lg') ?>
            <div class="min-w-0 flex-1">
                <p class="pixelito-panel-title">Pixelito</p>
                <p class="pixelito-panel-sub">Seu ajudante no COMPOST</p>
            </div>
            <button type="button" class="pixelito-close" data-pixelito-close aria-label="Fechar a conversa">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <?php // A conversa. role="log": leitor de tela anuncia cada mensagem nova, inteira (o efeito de digitação é aria-hidden). ?>
        <div class="pixelito-log" data-pixelito-log role="log" aria-live="polite" aria-label="Conversa"
             data-greeting="<?= View::e(PixelitoGuide::GREETING) ?>"
             data-notfound="<?= View::e(PixelitoGuide::NOT_FOUND) ?>"
             data-notfound-link="<?= View::e(PixelitoGuide::NOT_FOUND_LINK) ?>"
             data-notfound-link-label="<?= View::e(PixelitoGuide::NOT_FOUND_LINK_LABEL) ?>"
             data-face-talking="<?= View::e(Pixelito::url('falando')) ?>"
             data-face-idle="<?= View::e(Pixelito::url('normal')) ?>"
             data-face-sad="<?= View::e(Pixelito::url('sem-animo')) ?>"></div>

        <?php // Perguntas prontas: tocar numa "envia" a pergunta. Some o que não bate com o que a pessoa digita. ?>
        <div class="pixelito-tray" data-pixelito-tray>
            <?php foreach ($pixelitoTopics as $topic): ?>
                <section data-pixelito-topic>
                    <h3 class="pixelito-topic"><?= View::e($topic['topic']) ?></h3>
                    <div class="pixelito-chips">
                        <?php foreach ($topic['items'] as $item): ?>
                            <button type="button" class="pixelito-q hover-card bg-surface" data-pixelito-q
                                    data-question="<?= View::e($item['q']) ?>"
                                    data-answer="<?= View::e($item['a']) ?>"
                                    data-link="<?= View::e((string) $item['link']) ?>"
                                    data-link-label="<?= View::e((string) $item['linkLabel']) ?>"
                                    data-text="<?= View::e(mb_strtolower($item['q'] . ' ' . $item['a'])) ?>"><?= View::e($item['q']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <form class="pixelito-compose" data-pixelito-form autocomplete="off">
            <input type="text" data-pixelito-search placeholder="Escreva sua dúvida…" aria-label="Escreva sua dúvida" maxlength="120">
            <button type="submit" class="pixelito-send" aria-label="Enviar" title="Enviar"><?= Icon::nav('arrow') ?></button>
        </form>

        <footer class="pixelito-panel-foot">
            <?php if ($tourSiteId !== null): ?>
                <a href="/?tour=1"><span aria-hidden="true"><?= Icon::nav('help') ?></span> Rever o tutorial</a>
            <?php endif; ?>
            <a href="/compliance"><span aria-hidden="true"><?= Icon::nav('shield') ?></span> Regras de compliance</a>
        </footer>
    </section>

    <p class="pixelito-hint" data-pixelito-hint hidden>Precisa de ajuda? Clique em mim!</p>

    <button type="button" class="pixelito-fab" data-pixelito-fab aria-expanded="false" aria-controls="pixelito-panel" aria-label="Abrir a conversa com o Pixelito" title="Precisa de ajuda? Pergunte ao Pixelito">
        <?= Pixelito::bubble('normal', 'xl') ?>
        <?php // Com o chat aberto o Pixelito está no topo do painel: aqui vira um "X" de fechar (CSS: .is-open). ?>
        <span class="pixelito-fab-x" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </span>
    </button>
</div>
