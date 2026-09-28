/**
 * Reordenar as imagens de corpo por arrastar DIRETO NO TEXTO do artigo (pedido
 * do responsável 2026-09-28: "tem que aparecer no corpo do post, não escrito
 * onde ela vai ficar, elas têm que aparecer no corpo do post e poder arrastar
 * pra mudar") — a ordem escolhida é a ordem em que elas entram no artigo
 * publicado (`BodyImageInjector`), então arrastar aqui é decidir onde cada
 * imagem fica de verdade.
 *
 * Melhoria progressiva: sem JS, o painel "Reordenar sem arrastar" (sempre no
 * HTML, com um <select> de posição por imagem) faz o mesmo via formulário
 * comum. Com JS, esse painel some (evita duplicar o controle) e dá pra
 * arrastar as figuras direto dentro do corpo — ao soltar, manda a ordem
 * inteira pra `ProductionController::reorderImages()` (mesma rota do
 * fallback) e recarrega a página, pra o corpo/legendas virem sempre frescos
 * do servidor (nunca uma conta duplicada aqui em JS que possa divergir da
 * publicação de verdade).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.querySelector('[data-image-reorder]');
        if (!body) {
            return;
        }

        var figures = function () {
            return Array.prototype.slice.call(body.querySelectorAll('[data-image-id]'));
        };
        if (figures().length < 2) {
            return;
        }

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
        var endpoint = body.getAttribute('data-reorder-action');
        var dragEl = null;
        var originalOrder = figures().map(function (f) { return f.getAttribute('data-image-id'); });

        body.setAttribute('data-image-reorder-js', '1');
        // O <select> de posição por imagem (fallback sem JS) some — arrastar já faz o mesmo.
        document.querySelectorAll('[data-image-reorder-fallback]').forEach(function (el) {
            el.classList.add('hidden');
        });

        figures().forEach(function (fig) {
            fig.classList.add('cursor-grab');
            fig.addEventListener('dragstart', function (e) {
                dragEl = fig;
                fig.classList.add('opacity-50');
                e.dataTransfer.effectAllowed = 'move';
            });
            fig.addEventListener('dragend', function () {
                fig.classList.remove('opacity-50');
                dragEl = null;
                maybeSave();
            });
            fig.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragEl || dragEl === fig) {
                    return;
                }
                var rect = fig.getBoundingClientRect();
                var before = (e.clientY - rect.top) / rect.height < 0.5;
                fig.parentNode.insertBefore(dragEl, before ? fig : fig.nextSibling);
            });
        });

        function maybeSave() {
            var order = figures().map(function (f) { return f.getAttribute('data-image-id'); });
            if (order.join(',') === originalOrder.join(',')) {
                return; // soltou no mesmo lugar — nada mudou, não gasta uma requisição à toa
            }

            var params = new URLSearchParams();
            order.forEach(function (id) { params.append('image_ids[]', id); });
            params.set('_token', token);

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: params.toString(),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        window.alert(data.error || 'Não foi possível salvar a nova ordem.');
                        window.location.reload();
                    }
                })
                .catch(function () {
                    window.alert('Erro de rede ao salvar a ordem — tente de novo.');
                    window.location.reload();
                });
        }
    });
})();
