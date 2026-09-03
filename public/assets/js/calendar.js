/**
 * Reagendar por arrastar-e-soltar no calendário (Fase 9, pendência da Fase 7).
 * Melhoria progressiva: sem JS, a página continua funcionando normalmente —
 * reagendar/cancelar seguem disponíveis na tela do artigo.
 *
 * Arrasta um item PENDING pra outro dia -> POST /sites/{id}/calendar/reschedule
 * (article_id, new_date) -> recarrega a página com a data nova.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var items = document.querySelectorAll('[data-schedule-article]');
        var cells = document.querySelectorAll('[data-calendar-day]');
        if (items.length === 0 || cells.length === 0) {
            return;
        }

        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
        var endpoint = window.location.pathname.replace(/\/$/, '') + '/reschedule';

        items.forEach(function (el) {
            el.setAttribute('draggable', 'true');
            el.classList.add('cursor-grab');
            el.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', el.getAttribute('data-schedule-article'));
                e.dataTransfer.effectAllowed = 'move';
            });
        });

        cells.forEach(function (cell) {
            cell.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                cell.classList.add('bg-cyan/10');
            });
            cell.addEventListener('dragleave', function () {
                cell.classList.remove('bg-cyan/10');
            });
            cell.addEventListener('drop', function (e) {
                e.preventDefault();
                cell.classList.remove('bg-cyan/10');

                var articleId = e.dataTransfer.getData('text/plain');
                var newDate = cell.getAttribute('data-calendar-day');
                if (!articleId || !newDate) {
                    return;
                }

                var params = new URLSearchParams();
                params.set('article_id', articleId);
                params.set('new_date', newDate);
                params.set('_token', token);

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            window.location.reload();
                        } else {
                            window.alert(data.error || 'Não foi possível reagendar.');
                        }
                    })
                    .catch(function () {
                        window.alert('Erro de rede ao reagendar — tente de novo.');
                    });
            });
        });
    });
})();
