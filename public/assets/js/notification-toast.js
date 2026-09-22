/**
 * Pop-up + som quando chega uma notificação nova (pedido do responsável,
 * 2026-09-22) — sem precisar recarregar a página nem instalar nada de
 * push/websocket: um GET leve a cada 20s em /notifications/poll.
 *
 * Fluxo:
 *   1. Primeira chamada (sem `after`) só pega o id mais recente — vira a
 *      "linha da água". Notificação que já existia antes de abrir a
 *      página NUNCA vira pop-up, só o que chegar depois, de verdade.
 *   2. Chamadas seguintes mandam esse id (`after=`); o servidor devolve só
 *      o que é mais novo — cada uma vira um cartão + toca o som.
 *   3. O selo do sino (layout/_dial.php, [data-notif-badge]) atualiza ao
 *      vivo com a contagem que o polling já traz de brinde.
 *
 * O som é sintetizado na hora via Web Audio API (osc. sweep de agudo pra
 * grave + envelope curto) — sem arquivo de áudio pra baixar/hospedar, e
 * sem tocar nada até o usuário interagir com a página pelo menos uma vez
 * (política de autoplay dos navegadores).
 */
(function () {
    'use strict';

    var POLL_MS = 20000;
    var TOAST_MS = 7000;
    var MAX_VISIBLE = 4;

    var root = document.getElementById('notif-toast-root');
    if (!root) {
        return;
    }

    var csrfToken = (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    })();

    // --- ícones (mesmos paths de App\Support\Icon::PATHS, só os usados aqui) ---
    var ICONS = {
        PUBLISH_SUCCESS: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        PUBLISH_FAILED: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        ATTENTION: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        SITE_ASSIGNED: '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        ARTICLE_READY: '<path d="M4 20l1-4.5L15.5 5l3.5 3.5L8.5 19 4 20Z"/><path d="M13 7l3.5 3.5"/>',
        FEEDBACK: '<path d="M4 4.5h16a1 1 0 0 1 1 1V15a1 1 0 0 1-1 1H10l-4.5 4V16H4a1 1 0 0 1-1-1V5.5a1 1 0 0 1 1-1Z"/><path d="M8 9.5h8M8 12.5h5"/>',
        DEFAULT: '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 2 6.5H4c.5-1 2-2.5 2-6.5Z"/><path d="M9.5 19.5a2.5 2.5 0 0 0 5 0"/>'
    };
    var CLOSE_ICON = '<path d="M6 6l12 12M18 6 6 18"/>';

    // Mesmo mapa de App\Support\Labels::notificationTone() — trio RGB igual
    // ao de .article-card--*/.article-card-tag--* (nunca um 3º mapa de cor).
    var TONES = {
        PUBLISH_SUCCESS: '61 240 122',
        ARTICLE_READY: '61 240 122',
        PUBLISH_FAILED: '255 92 122',
        SITE_ASSIGNED: '0 208 240',
        ATTENTION: '255 197 61',
        DEFAULT: '143 166 188'
    };

    function svg(paths) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" ' +
            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
    }

    // --- som: "bolha estourando" sintetizado, sem arquivo de áudio ---
    var audioCtx = null;
    var audioUnlocked = false;
    function ensureAudioContext() {
        if (audioCtx) return audioCtx;
        var Ctor = window.AudioContext || window.webkitAudioContext;
        if (!Ctor) return null;
        try {
            audioCtx = new Ctor();
        } catch (e) {
            audioCtx = null;
        }
        return audioCtx;
    }
    // Navegadores só deixam tocar áudio depois de alguma interação real do
    // usuário na página — "destrava" no primeiro clique/tecla, silencioso.
    ['click', 'keydown', 'touchstart'].forEach(function (evt) {
        window.addEventListener(evt, function unlock() {
            audioUnlocked = true;
            var ctx = ensureAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume().catch(function () {});
            }
        }, { once: true, passive: true });
    });

    function playBubblePop() {
        if (!audioUnlocked) return; // sem interação ainda: nunca tenta (evita erro/bloqueio silencioso repetido)
        var ctx = ensureAudioContext();
        if (!ctx) return;
        try {
            var t0 = ctx.currentTime;
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            // Sweep agudo -> grave rápido + ataque/decaimento curtos = "pop" de bolha.
            osc.frequency.setValueAtTime(1100, t0);
            osc.frequency.exponentialRampToValueAtTime(260, t0 + 0.11);
            gain.gain.setValueAtTime(0.0001, t0);
            gain.gain.exponentialRampToValueAtTime(0.45, t0 + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.16);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t0);
            osc.stop(t0 + 0.18);
        } catch (e) { /* som é enfeite — nunca deve quebrar o pop-up em si */ }
    }

    // --- selo do sino ao vivo (layout/_dial.php) ---
    function updateBellBadge(unread) {
        var bell = document.querySelector('[data-notif-bell]');
        if (!bell) return;
        var badge = bell.querySelector('[data-notif-badge]');
        if (unread <= 0) {
            if (badge) badge.remove();
            return;
        }
        var text = unread > 99 ? '99+' : String(unread);
        if (badge) {
            badge.textContent = text;
        } else {
            badge = document.createElement('span');
            badge.className = 'nav-badge';
            badge.setAttribute('data-notif-badge', '');
            badge.textContent = text;
            bell.appendChild(badge);
        }
    }

    // --- abrir a notificação ao clicar no toast: mesmo mecanismo do sino/lista
    // (POST real, não fetch — precisa navegar de verdade pro link depois de marcar como lida). ---
    function openNotification(id) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '/notifications/' + encodeURIComponent(id) + '/open';
        form.style.display = 'none';
        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrfToken;
        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    }

    function showToast(n) {
        var tone = TONES[n.type] || TONES.DEFAULT;
        var iconPaths = ICONS[n.type] || ICONS.DEFAULT;

        // Nunca deixa empilhar demais na tela — mais que MAX_VISIBLE, os mais
        // antigos saem primeiro (o mesmo pop-up ainda existe na central de
        // notificações inteira, isto aqui é só o aviso na hora).
        while (root.children.length >= MAX_VISIBLE) {
            root.removeChild(root.firstChild);
        }

        var el = document.createElement('div');
        el.className = 'notif-toast';
        el.style.setProperty('--tone', tone);
        el.setAttribute('role', 'button');
        el.setAttribute('tabindex', '0');

        el.innerHTML =
            '<span class="notif-toast-icon">' + svg(iconPaths) + '</span>' +
            '<span class="notif-toast-body">' +
                '<span class="notif-toast-title"></span>' +
                '<span class="notif-toast-message"></span>' +
            '</span>' +
            '<button type="button" class="notif-toast-close" aria-label="Fechar">' + svg(CLOSE_ICON) + '</button>';

        // Texto por textContent (nunca innerHTML) — título/mensagem vêm do banco.
        el.querySelector('.notif-toast-title').textContent = n.title;
        el.querySelector('.notif-toast-message').textContent = n.message;

        var dismissTimer = null;
        function dismiss() {
            if (dismissTimer) clearTimeout(dismissTimer);
            el.classList.add('is-leaving');
            el.addEventListener('animationend', function () { el.remove(); }, { once: true });
            // prefers-reduced-motion desliga a animação (CSS) — sem evento, some direto.
            setTimeout(function () { if (el.parentNode) el.remove(); }, 250);
        }

        el.addEventListener('click', function (e) {
            if (e.target.closest('.notif-toast-close')) {
                dismiss();
                return;
            }
            if (n.link) {
                openNotification(n.id);
            } else {
                dismiss();
            }
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                el.click();
            }
        });

        root.appendChild(el);
        dismissTimer = setTimeout(dismiss, TOAST_MS);
        playBubblePop();
    }

    var lastId = null;
    var polling = false;

    function poll() {
        if (polling) return; // nunca duas em voo — uma resposta lenta não deve empilhar chamadas
        polling = true;
        var url = '/notifications/poll' + (lastId !== null ? '?after=' + encodeURIComponent(lastId) : '');
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) return;
                lastId = data.latestId;
                updateBellBadge(data.unread);
                (data.notifications || []).forEach(showToast);
            })
            .catch(function () { /* falha de rede pontual — tenta de novo no próximo ciclo, sem alarme */ })
            .finally(function () { polling = false; });
    }

    // Só liga o polling quando a aba está visível — evita gastar
    // requisição/bateria com o usuário numa aba em segundo plano; ao
    // voltar, o próximo poll já pega o que acumulou.
    var timer = null;
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
