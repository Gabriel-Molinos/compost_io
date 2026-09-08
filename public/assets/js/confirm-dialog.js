/**
 * COMPOST — troca o confirm() nativo do navegador por um <dialog> desenhado
 * com a identidade do produto (pedido do responsável, 2026-09-08: "nada de
 * alerts no site"). Qualquer <form> com `data-confirm="mensagem"` passa a
 * abrir este diálogo em vez de submeter direto; confirmar reenvia o form de
 * verdade (mesma ação/CSRF/método — nada muda no back-end).
 */
(function () {
  'use strict';

  var dialog, messageEl, confirmBtn, cancelBtn, pendingForm;

  function ensureDialog() {
    if (dialog) {
      return;
    }
    dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.innerHTML =
      '<div class="confirm-dialog-panel">' +
        '<div class="confirm-dialog-icon" aria-hidden="true">' +
          '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" ' +
            'stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>' +
            '<path d="M12 9v4"/><path d="M12 17h.01"/>' +
          '</svg>' +
        '</div>' +
        '<p class="confirm-dialog-message"></p>' +
        '<div class="confirm-dialog-actions">' +
          '<button type="button" class="confirm-dialog-cancel">Cancelar</button>' +
          '<button type="button" class="confirm-dialog-confirm">Confirmar</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(dialog);

    messageEl = dialog.querySelector('.confirm-dialog-message');
    confirmBtn = dialog.querySelector('.confirm-dialog-confirm');
    cancelBtn = dialog.querySelector('.confirm-dialog-cancel');

    cancelBtn.addEventListener('click', function () {
      pendingForm = null;
      dialog.close();
    });
    confirmBtn.addEventListener('click', function () {
      var form = pendingForm;
      pendingForm = null;
      dialog.close();
      if (!form) {
        return;
      }
      form.dataset.confirmed = '1';
      if (form.requestSubmit) {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
    // Clique fora do painel (no próprio <dialog>, que cobre a tela toda
    // atrás do painel) fecha sem confirmar — mesmo comportamento de
    // clicar fora de qualquer outro popup do app.
    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) {
        pendingForm = null;
        dialog.close();
      }
    });
  }

  function bind(form) {
    if (form.dataset.confirmBound === '1') {
      return;
    }
    form.dataset.confirmBound = '1';
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1') {
        form.dataset.confirmed = '';
        return; // já passou pelo diálogo — deixa submeter de verdade.
      }
      e.preventDefault();
      ensureDialog();
      messageEl.textContent = form.dataset.confirm;
      pendingForm = form;
      dialog.showModal();
      cancelBtn.focus();
    });
  }

  function init() {
    document.querySelectorAll('form[data-confirm]').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
