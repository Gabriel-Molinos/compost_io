/**
 * COMPOST — visualização em tamanho grande de uma imagem gerada por IA antes
 * de escolher a destacada (pedido do responsável, 2026-09-10). Qualquer
 * elemento com `data-lightbox="URL"` abre este diálogo compartilhado; clique
 * fora do painel fecha, sem enviar nenhum form (só visualização).
 */
(function () {
  'use strict';

  var dialog, imgEl, captionEl;

  function ensureDialog() {
    if (dialog) {
      return;
    }
    dialog = document.createElement('dialog');
    dialog.className = 'image-lightbox';
    dialog.innerHTML = '<img alt=""><p class="image-lightbox-caption"></p>';
    document.body.appendChild(dialog);
    imgEl = dialog.querySelector('img');
    captionEl = dialog.querySelector('.image-lightbox-caption');

    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) {
        dialog.close();
      }
    });
  }

  function bind(trigger) {
    if (trigger.dataset.lightboxBound === '1') {
      return;
    }
    trigger.dataset.lightboxBound = '1';
    trigger.addEventListener('click', function (e) {
      // Vários gatilhos ficam dentro de um <label> (escolha de imagem
      // destacada) — sem isso, o clique borbulharia pro label e marcaria/
      // desmarcaria o rádio junto, quando a intenção é só abrir o preview.
      e.preventDefault();
      e.stopPropagation();
      ensureDialog();
      imgEl.src = trigger.getAttribute('data-lightbox');
      imgEl.alt = trigger.getAttribute('aria-label') || '';
      // Descrição da imagem (alt) — pedido 2026-09-22: nas imagens de corpo
      // ela fica escondida na grade, só aparece aqui na ampliada.
      captionEl.textContent = trigger.getAttribute('data-lightbox-caption') || '';
      dialog.showModal();
    });
  }

  function init() {
    document.querySelectorAll('[data-lightbox]').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
