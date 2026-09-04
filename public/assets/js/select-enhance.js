/**
 * COMPOST — troca a caixa visível de todo <select> do app por um combobox
 * (botão + <ul role="listbox">) desenhado com a identidade do produto
 * (src/styles/input.css, regras `.select-trigger`/`.select-listbox`).
 *
 * Por quê: a lista que abre ao clicar num <select> é o popup NATIVO do
 * navegador — não existe CSS confiável pra estilizar item, hover, padding
 * ou item selecionado ali (color-scheme:dark só troca a paleta de base,
 * continua com a cara "sistema operacional", nada a ver com o resto do
 * app). Progressive enhancement: o <select> original nunca sai do DOM,
 * só fica visualmente escondido (opacity:0, ainda ocupa o espaço) —
 * continua dono do name/value que o form envia e da validação nativa
 * (`required`). Se este script não rodar (JS desabilitado, erro, bloqueio),
 * o <select> segue 100% visível e funcional com o próprio popup nativo —
 * ver o fallback em `select{...}` no CSS.
 */
(function () {
  'use strict';

  var uid = 0;

  function enhance(select) {
    if (select.dataset.enhanced === '1' || select.multiple || select.hasAttribute('data-no-enhance')) {
      return;
    }
    select.dataset.enhanced = '1';
    uid += 1;
    var listboxId = 'select_listbox_' + uid;

    var wrapper = document.createElement('div');
    wrapper.className = 'select-shell ' + select.className;
    select.className = '';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'select-trigger';
    btn.setAttribute('role', 'combobox');
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', listboxId);
    if (select.id) {
      btn.id = select.id;
      select.removeAttribute('id');
    }
    ['aria-invalid', 'aria-describedby'].forEach(function (attr) {
      if (select.hasAttribute(attr)) {
        btn.setAttribute(attr, select.getAttribute(attr));
      }
    });
    if (select.disabled) {
      btn.disabled = true;
    }

    var labelSpan = document.createElement('span');
    labelSpan.className = 'select-trigger-label';
    btn.appendChild(labelSpan);

    var listbox = document.createElement('ul');
    listbox.className = 'select-listbox';
    listbox.id = listboxId;
    listbox.setAttribute('role', 'listbox');
    listbox.hidden = true;

    var items = [];
    Array.prototype.forEach.call(select.options, function (opt, index) {
      var li = document.createElement('li');
      li.className = 'select-option';
      li.setAttribute('role', 'option');
      li.id = listboxId + '_o' + index;
      li.textContent = opt.textContent;
      if (opt.disabled) {
        li.classList.add('is-disabled');
        li.setAttribute('aria-disabled', 'true');
      }
      // Guarda contra "mouseenter fantasma": ao abrir a lista, o Chromium
      // refaz o hit-test embaixo do cursor parado (ele não se moveu, só o
      // DOM mudou) e dispara mouseenter na opção que ficou por baixo —
      // isso pisava o item ativo escolhido por teclado logo depois de abrir
      // (ArrowDown seguido de "sem querer" voltar pro item sob o mouse).
      // Mouse de verdade sempre tem movementX/Y != 0; o hit-test sintético
      // fica em (0,0) por não ter havido deslocamento real do ponteiro.
      li.addEventListener('mouseenter', function (e) {
        if (e.movementX === 0 && e.movementY === 0) { return; }
        setActive(index);
      });
      li.addEventListener('click', function () {
        if (opt.disabled) { return; }
        commit(index);
        close(true);
      });
      listbox.appendChild(li);
      items.push(li);
    });

    var activeIndex = select.selectedIndex < 0 ? 0 : select.selectedIndex;

    function syncFromSelect() {
      var opt = select.options[select.selectedIndex];
      labelSpan.textContent = opt ? opt.textContent : '';
      items.forEach(function (li, index) {
        var selected = index === select.selectedIndex;
        li.setAttribute('aria-selected', selected ? 'true' : 'false');
        li.classList.toggle('is-selected', selected);
      });
    }

    function setActive(index) {
      activeIndex = index;
      items.forEach(function (li, i) { li.classList.toggle('is-active', i === index); });
      btn.setAttribute('aria-activedescendant', items[index] ? items[index].id : '');
    }

    function moveActive(delta) {
      var len = items.length;
      if (!len) { return; }
      var i = activeIndex;
      for (var step = 0; step < len; step += 1) {
        i = (i + delta + len) % len;
        if (!items[i].classList.contains('is-disabled')) {
          setActive(i);
          items[i].scrollIntoView({ block: 'nearest' });
          return;
        }
      }
    }

    function commit(index) {
      var opt = select.options[index];
      if (!opt || opt.disabled) { return; }
      select.selectedIndex = index;
      syncFromSelect();
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function onDocClick(e) {
      if (!wrapper.contains(e.target)) { close(false); }
    }
    function onDocKeydown(e) {
      switch (e.key) {
        case 'ArrowDown': e.preventDefault(); moveActive(1); break;
        case 'ArrowUp': e.preventDefault(); moveActive(-1); break;
        case 'Home': e.preventDefault(); setActive(0); items[0] && items[0].scrollIntoView({ block: 'nearest' }); break;
        case 'End': e.preventDefault(); setActive(items.length - 1); items[items.length - 1] && items[items.length - 1].scrollIntoView({ block: 'nearest' }); break;
        case 'Enter':
        case ' ':
          e.preventDefault();
          commit(activeIndex);
          close(true);
          break;
        case 'Escape':
          e.preventDefault();
          close(true);
          break;
        case 'Tab':
          close(false);
          break;
        default:
          break;
      }
    }

    function open() {
      if (btn.disabled || !listbox.hidden) { return; }
      listbox.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      setActive(select.selectedIndex < 0 ? 0 : select.selectedIndex);
      var activeItem = items[activeIndex];
      if (activeItem) { activeItem.scrollIntoView({ block: 'nearest' }); }
      document.addEventListener('click', onDocClick, true);
      document.addEventListener('keydown', onDocKeydown, true);
    }

    function close(focusBtn) {
      if (listbox.hidden) { return; }
      listbox.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      btn.removeAttribute('aria-activedescendant');
      document.removeEventListener('click', onDocClick, true);
      document.removeEventListener('keydown', onDocKeydown, true);
      if (focusBtn) { btn.focus(); }
    }

    btn.addEventListener('click', function () {
      if (listbox.hidden) { open(); } else { close(true); }
    });
    btn.addEventListener('keydown', function (e) {
      var opens = e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ';
      if (listbox.hidden && opens) {
        e.preventDefault();
        open();
      }
    });

    // Select mudou por fora (outro script) — mantém o botão em dia.
    select.addEventListener('change', syncFromSelect);

    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    select.insertAdjacentElement('beforebegin', wrapper);
    wrapper.appendChild(btn);
    wrapper.appendChild(listbox);
    wrapper.appendChild(select);

    syncFromSelect();
  }

  function init() {
    document.querySelectorAll('select').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
