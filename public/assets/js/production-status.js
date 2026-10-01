/**
 * Cards trancados da Produção (pedido do responsável, 2026-09-29: "não permitir
 * entrar no post até que ele esteja 100% pronto"). Antes, abrir um rascunho
 * gerando mostrava ele pela metade e a página recarregava inteira a cada 5s.
 *
 * Agora o card fica sem link enquanto gera ([data-generating-article]), e este
 * script:
 *   1. a cada 8s pergunta a situação só desses cards (GET .../production/status?ids=);
 *   2. atualiza o texto da etapa atual ([data-generating-step]) no lugar;
 *   3. quando um fica pronto (ou falha/trava), busca a MESMA URL da lista e troca
 *      SÓ aquele card (e os contadores do topo) pela versão nova do servidor —
 *      nunca recarrega a página nem monta card em JS (sem risco de divergir do PHP).
 *
 * Sem JS: o card continua trancado e o aviso "Rascunho pronto" chega pela
 * notificação normal (notification-toast.js / sino); um F5 destrava.
 * Com a aba em segundo plano o polling para — ao voltar, checa na hora.
 */
(function () {
    'use strict';

    var POLL_MS = 8000;

    var endpoint = window.location.pathname.replace(/\/$/, '') + '/status';
    var polling = false;
    var timer = null;

    function lockedCards() {
        // Consulta de novo a cada ciclo: o filtro em tempo real troca a lista inteira.
        return Array.prototype.slice.call(document.querySelectorAll('[data-generating-article]'));
    }

    function refreshFinished(ids) {
        return fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                ids.forEach(function (id) {
                    var current = document.querySelector('[data-article-card="' + id + '"]');
                    if (!current) return;
                    var fresh = doc.querySelector('[data-article-card="' + id + '"]');
                    if (fresh) {
                        current.replaceWith(document.importNode(fresh, true));
                    } else {
                        current.remove(); // saiu do filtro atual (ex.: aba "Em produção")
                    }
                });
                var top = document.querySelector('[data-results-top]');
                var newTop = doc.querySelector('[data-results-top]');
                if (top && newTop) top.innerHTML = newTop.innerHTML;
            });
    }

    function poll() {
        var cards = lockedCards();
        if (polling || cards.length === 0) return;
        polling = true;

        var ids = cards.map(function (card) { return card.getAttribute('data-generating-article'); });

        fetch(endpoint + '?ids=' + encodeURIComponent(ids.join(',')), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) return null;
                var finished = [];
                ids.forEach(function (id) {
                    var state = data.articles[id];
                    if (!state || !state.locked) {
                        finished.push(id);
                        return;
                    }
                    var card = document.querySelector('[data-generating-article="' + id + '"]');
                    var step = card && card.querySelector('[data-generating-step]');
                    if (step && step.textContent !== state.step) step.textContent = state.step;
                });
                return finished.length ? refreshFinished(finished) : null;
            })
            .catch(function () { /* falha pontual (rede, sessão) — tenta de novo no próximo ciclo */ })
            .finally(function () { polling = false; });
    }

    function start() {
        if (timer) return;
        poll();
        timer = setInterval(poll, POLL_MS);
    }

    function stop() {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { start(); }
    });

    if (!document.hidden) start();
})();
