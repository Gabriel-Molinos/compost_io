/**
 * Pixelito — painel de ajuda do canto inferior direito (layout/_pixelito.php,
 * pedido do responsável, 2026-09-25). Todo o conteúdo já vem renderizado pelo
 * servidor (src/Support/PixelitoGuide.php); aqui só há comportamento:
 *   · abrir/fechar (botão, Esc, clique fora) com foco e aria-expanded coerentes;
 *   · perguntas em "sanfona" — uma resposta aberta por vez;
 *   · busca instantânea (filtra pergunta + resposta, some com assunto vazio);
 *   · balãozinho "Precisa de ajuda?" uma única vez por sessão.
 * Sem requisição nenhuma, sem biblioteca — mesma filosofia do resto do projeto.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-pixelito]');
    if (!root) return;

    var fab = root.querySelector('[data-pixelito-fab]');
    var panel = root.querySelector('[data-pixelito-panel]');
    var closeBtn = root.querySelector('[data-pixelito-close]');
    var search = root.querySelector('[data-pixelito-search]');
    var empty = root.querySelector('[data-pixelito-empty]');
    var hint = root.querySelector('[data-pixelito-hint]');
    if (!fab || !panel) return;

    var items = [].slice.call(panel.querySelectorAll('[data-pixelito-item]'));
    var topics = [].slice.call(panel.querySelectorAll('[data-pixelito-topic]'));

    function isOpen() { return !panel.hidden; }

    function hideHint() {
        if (hint) hint.hidden = true;
    }

    function open() {
        hideHint();
        panel.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        // Foco na busca só em tela com teclado de verdade — no celular abriria o teclado virtual em cima da lista.
        if (search && window.matchMedia('(hover: hover)').matches) {
            search.focus({ preventScroll: true });
        }
    }

    function close(returnFocus) {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
        if (returnFocus) fab.focus({ preventScroll: true });
    }

    fab.addEventListener('click', function () {
        if (isOpen()) { close(false); } else { open(); }
    });
    if (closeBtn) closeBtn.addEventListener('click', function () { close(true); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) close(true);
    });
    document.addEventListener('click', function (e) {
        if (isOpen() && !root.contains(e.target)) close(false);
    });

    // --- sanfona: uma resposta por vez ---
    function setExpanded(item, expanded) {
        var q = item.querySelector('[data-pixelito-q]');
        var a = item.querySelector('[data-pixelito-a]');
        if (!q || !a) return;
        q.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        a.hidden = !expanded;
        item.classList.toggle('is-open', expanded);
    }
    panel.addEventListener('click', function (e) {
        var q = e.target.closest('[data-pixelito-q]');
        if (!q) return;
        var item = q.closest('[data-pixelito-item]');
        var willOpen = q.getAttribute('aria-expanded') !== 'true';
        items.forEach(function (it) { setExpanded(it, false); });
        setExpanded(item, willOpen);
    });

    // --- busca instantânea ---
    function applySearch() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var shown = 0;
        items.forEach(function (it) {
            var ok = term === '' || (it.getAttribute('data-text') || '').indexOf(term) !== -1;
            it.hidden = !ok;
            if (ok) shown += 1;
            // Buscando, abre a resposta quando só sobrou uma — poupa um clique.
            if (term === '') setExpanded(it, false);
        });
        topics.forEach(function (t) {
            t.hidden = t.querySelectorAll('[data-pixelito-item]:not([hidden])').length === 0;
        });
        if (empty) empty.hidden = shown > 0;
        if (term !== '' && shown === 1) {
            var only = items.filter(function (it) { return !it.hidden; })[0];
            if (only) setExpanded(only, true);
        }
    }
    if (search) {
        search.addEventListener('input', applySearch);
        search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    }

    // --- balãozinho de boas-vindas: 1x por sessão, nunca durante o tutorial ---
    function tourRunning() {
        try { return sessionStorage.getItem('compost_tour_step') !== null; } catch (e) { return false; }
    }
    try {
        if (hint && !tourRunning() && !sessionStorage.getItem('compost_pixelito_hint')) {
            sessionStorage.setItem('compost_pixelito_hint', '1');
            setTimeout(function () {
                if (isOpen() || tourRunning()) return;
                hint.hidden = false;
                setTimeout(hideHint, 6500);
            }, 2500);
        }
    } catch (e) { /* sessionStorage bloqueado: sem balão, o botão continua funcionando */ }
    if (hint) hint.addEventListener('click', open);
})();
