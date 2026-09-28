/**
 * Filtro em tempo real genérico — extraído de `sites/production/index.php`
 * (2026-09-28, pedido do responsável ao redesenhar "Todos os posts": mesmo
 * mecanismo, sem reescrever). Qualquer página com este contrato de atributos
 * ganha filtro sem reload:
 *
 *   [data-filter-form]    o <form method="get">
 *   [data-results-top]    região trocada (ex.: contadores/abas)
 *   [data-results-body]   região trocada (a lista de resultados em si)
 *   [data-filter-auto]    campo (select etc.) que filtra sozinho no `change`
 *   [data-filter-search]  campo de texto que filtra com debounce no `input`
 *   [data-clear-filters]  link "Limpar filtros" — sempre no DOM, só `hidden` muda
 *
 * Busca a MESMA URL (querystring) via fetch — compartilhável/atualizável com
 * F5 — e troca só as duas regiões, sem mexer no resto da página (foco e
 * cursor do campo de busca continuam quietos). Qualquer falha (rede, sessão
 * expirada, HTML inesperado) cai numa navegação normal pra mesma URL, então
 * nunca deixa a tela numa versão velha sem avisar.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-filter-form]');
        var top = document.querySelector('[data-results-top]');
        var body = document.querySelector('[data-results-body]');
        if (!form || !top || !body) return;

        var clear = form.querySelector('[data-clear-filters]');
        var search = form.querySelector('[data-filter-search]');
        var timer = null;
        var inflight = null;

        function filterUrl() {
            var params = new URLSearchParams();
            new FormData(form).forEach(function (value, key) {
                if (value !== '') params.append(key, value);
            });
            var qs = params.toString();
            return form.getAttribute('action') + (qs ? '?' + qs : '');
        }

        function setBusy(busy) {
            [top, body].forEach(function (el) {
                el.classList.toggle('opacity-50', busy);
                el.setAttribute('aria-busy', busy ? 'true' : 'false');
            });
        }

        function update() {
            if (timer) { clearTimeout(timer); timer = null; }
            var url = filterUrl();
            if (inflight) inflight.abort();
            var mine = inflight = new AbortController();
            setBusy(true);

            fetch(url, { credentials: 'same-origin', signal: mine.signal })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newTop = doc.querySelector('[data-results-top]');
                    var newBody = doc.querySelector('[data-results-body]');
                    if (!newTop || !newBody) throw new Error('resposta sem as regiões de resultado');
                    top.innerHTML = newTop.innerHTML;
                    body.innerHTML = newBody.innerHTML;
                    if (clear) {
                        var newClear = doc.querySelector('[data-clear-filters]');
                        clear.hidden = !newClear || newClear.hidden;
                    }
                    history.replaceState(null, '', url);
                    setBusy(false);
                })
                .catch(function (err) {
                    if (err.name === 'AbortError') return; // trocada por uma consulta mais nova
                    window.location.href = url;
                })
                .finally(function () {
                    if (inflight === mine) inflight = null;
                });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            update();
        });

        form.querySelectorAll('[data-filter-auto]').forEach(function (field) {
            field.addEventListener('change', update);
        });

        if (search) {
            search.addEventListener('input', function () {
                if (timer) clearTimeout(timer);
                timer = setTimeout(update, 350);
            });
        }
    });
})();
