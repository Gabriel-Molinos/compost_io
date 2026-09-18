/**
 * COMPOST — hover dos botões (.btn) e dos cards clicáveis (.hover-card),
 * src/styles/input.css: a luz e o próprio elemento acompanham o mouse. Este
 * script só alimenta quatro variáveis CSS no elemento sob o cursor; quem
 * desenha tudo (holofote, "puxão", brilho, troca de cor) é o CSS:
 *   --mx / --my  posição do mouse dentro do botão, em px (centro do holofote)
 *   --dx / --dy  -1..1, quanto o mouse está à esquerda/direita e acima/abaixo
 *                do centro (o botão "puxa" ~2px nessa direção)
 * Delegado no document (um listener só, em vez de um por botão), então vale
 * também pra botões que entram depois na página sem recarregar — mesma razão
 * do confirm-dialog.js. Melhoria progressiva: sem este script o botão
 * continua com cor, brilho e hover, só sem o acompanhamento do mouse.
 * `prefers-reduced-motion`: o holofote ainda segue o mouse (é só luz), mas o
 * botão não se desloca (--dx/--dy ficam em 0).
 */
(function () {
  'use strict';

  // Botões, cards clicáveis e seleções (.pick — radios/checkboxes em cartão, chips)
  // (src/styles/input.css) compartilham as mesmas variáveis.
  var TARGETS = '.btn, .hover-card, .pick, .dial-item, .dial-btn';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var current = null;
  var lastEvent = null;
  var frame = 0;

  function settle(btn) {
    if (!btn) { return; }
    btn.style.removeProperty('--dx');
    btn.style.removeProperty('--dy');
    // --mx/--my ficam onde estavam: o holofote some por opacity, sem pular
    // pro centro enquanto apaga.
  }

  function paint() {
    frame = 0;
    if (!current || !lastEvent) { return; }
    var rect = current.getBoundingClientRect();
    if (!rect.width || !rect.height) { return; }
    var x = lastEvent.clientX - rect.left;
    var y = lastEvent.clientY - rect.top;
    current.style.setProperty('--mx', x.toFixed(1) + 'px');
    current.style.setProperty('--my', y.toFixed(1) + 'px');
    if (!reduced) {
      current.style.setProperty('--dx', Math.max(-1, Math.min(1, (x / rect.width) * 2 - 1)).toFixed(3));
      current.style.setProperty('--dy', Math.max(-1, Math.min(1, (y / rect.height) * 2 - 1)).toFixed(3));
    }
  }

  document.addEventListener('pointermove', function (e) {
    if (e.pointerType === 'touch') { return; }
    var btn = e.target instanceof Element ? e.target.closest(TARGETS) : null;
    if (btn !== current) {
      settle(current);
      current = btn;
    }
    if (!btn || btn.disabled) { return; }
    lastEvent = e;
    if (!frame) { frame = window.requestAnimationFrame(paint); }
  }, { passive: true });

  // Mouse saiu da janela: assenta o botão que estava sob ele.
  document.addEventListener('pointerout', function (e) {
    if (!e.relatedTarget) {
      settle(current);
      current = null;
    }
  });
})();
