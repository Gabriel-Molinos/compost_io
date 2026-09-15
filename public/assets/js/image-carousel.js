/**
 * COMPOST — carrossel simples pra escolha de imagem destacada
 * (production/show.php): um card por vez, setas pra navegar, opção de
 * selecionar sempre visível embaixo da imagem atual (pedido do responsável,
 * 2026-09-10 — antes era um grid com todas as opções de uma vez). A legenda
 * (botão de selecionar + alt text) fica FORA do trilho que desliza — sincronizada
 * por índice — porque o texto do alt varia de tamanho e bagunçava a
 * centralização das setas quando fazia parte do card que desliza.
 */
(function () {
  'use strict';

  function setup(root) {
    var track = root.querySelector('[data-carousel-track]');
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-slide]'));
    var captions = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-caption]'));
    if (!track || slides.length === 0) {
      return;
    }
    var prevBtn = root.querySelector('[data-carousel-prev]');
    var nextBtn = root.querySelector('[data-carousel-next]');
    var counter = root.querySelector('[data-carousel-counter]');

    function radioOf(caption) {
      return caption.querySelector('[data-select-image-input]');
    }

    // Começa mostrando a opção já selecionada (se houver) em vez de sempre a 1a.
    var current = captions.findIndex(function (caption) {
      var input = radioOf(caption);
      return input && input.checked;
    });
    if (current < 0) {
      current = 0;
    }

    if (slides.length < 2) {
      if (prevBtn) prevBtn.hidden = true;
      if (nextBtn) nextBtn.hidden = true;
    }

    function render() {
      // Translada a trilha inteira — cada slide ocupa 100% da largura, então
      // o deslocamento é sempre um múltiplo exato de 100% (slide a slide).
      track.style.transform = 'translateX(-' + (current * 100) + '%)';
      captions.forEach(function (caption, i) {
        caption.hidden = i !== current;
      });
      if (counter) {
        counter.textContent = (current + 1) + ' / ' + slides.length;
      }
    }

    function go(delta) {
      current = (current + delta + slides.length) % slides.length;
      render();
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { go(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(1); });

    // Botão de selecionar dá feedback visual na hora (pílula muda de cor,
    // ícone de check aparece) sem esperar o submit do form + reload da
    // página — só um rádio marcado por vez no grupo inteiro, então ao
    // marcar um, desmarca visualmente todos os outros.
    captions.forEach(function (caption) {
      var input = radioOf(caption);
      if (!input) {
        return;
      }
      input.addEventListener('change', function () {
        captions.forEach(function (c) {
          var pill = c.querySelector('[data-select-image-pill]');
          var icon = c.querySelector('[data-select-image-icon]');
          var text = c.querySelector('[data-select-image-text]');
          var isThis = radioOf(c) === input;
          if (pill) pill.classList.toggle('image-select-pill-selected', isThis);
          if (icon) icon.hidden = !isThis;
          if (text) text.textContent = isThis ? 'Imagem selecionada' : 'Usar esta imagem';
        });
      });
    });

    // Posiciona no slide inicial (pode não ser o 1º, se já houver seleção)
    // sem animar esse posicionamento — só as trocas por clique deslizam.
    track.style.transition = 'none';
    render();
    void track.offsetHeight; // força reflow antes de reativar a transição (CSS já define o transition padrão)
    track.style.transition = '';
  }

  function init() {
    document.querySelectorAll('[data-carousel]').forEach(setup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
