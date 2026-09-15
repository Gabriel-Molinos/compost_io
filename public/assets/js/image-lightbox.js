/**
 * COMPOST — visualização em tamanho grande de uma imagem gerada por IA antes
 * de escolher a destacada (pedido do responsável, 2026-09-10). Qualquer
 * elemento com `data-lightbox="URL"` abre este diálogo compartilhado; clique
 * fora do painel fecha, sem enviar nenhum form (só visualização).
 */
(function () {
  'use strict';

  var dialog, imgEl;

  function ensureDialog() {
    if (dialog) {
      return;
    }
    dialog = document.createElement('dialog');
    dialog.className = 'image-lightbox';
    dialog.innerHTML = '<img alt="">';
    document.body.appendChild(dialog);
    imgEl = dialog.querySelector('img');

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
