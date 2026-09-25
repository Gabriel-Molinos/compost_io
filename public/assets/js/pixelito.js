/**
 * Pixelito — o ajudante do canto inferior direito, como um CHAT simulado
 * (layout/_pixelito.php, pedido do responsável, 2026-09-25): a pessoa toca numa
 * pergunta, ela vira a mensagem dela, o Pixelito "digita" e a resposta PRONTA
 * aparece letra por letra. Nada de IA nem de requisição — todo o conteúdo já vem
 * renderizado pelo servidor (src/Support/PixelitoGuide.php); aqui só se encena a conversa:
 *   · abrir/fechar (botão, Esc, clique fora), foco e aria-expanded coerentes;
 *   · perguntas prontas ("chips"): tocar envia; a caixa de baixo filtra e, no Enter,
 *     envia a 1ª que bate — se nada bate, o Pixelito responde que não achou;
 *   · digitação: "…" → texto letra por letra → link; tocar na mensagem pula a digitação;
 *   · balãozinho "Precisa de ajuda?" uma única vez por sessão.
 * Acessibilidade: o leitor de tela lê a resposta inteira de uma vez (.sr-only); a versão
 * digitada é aria-hidden. Com prefers-reduced-motion não há efeito de digitação.
 *
 * REGRA: só um Pixelito "vivo" por vez. Com o chat aberto, o do TOPO do painel é quem fala (troca de
 * expressão: falando enquanto digita, normal parado, triste no "não achei"); o avatar de cada mensagem
 * é fixo (só marca quem falou); o botão do canto vira um "X". A classe html.pixelito-chat-open avisa
 * os pop-ups de notificação (notification-toast.js) pra não trazerem outra carinha enquanto isso.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-pixelito]');
    if (!root) return;

    var fab = root.querySelector('[data-pixelito-fab]');
    var panel = root.querySelector('[data-pixelito-panel]');
    var log = root.querySelector('[data-pixelito-log]');
    var closeBtn = root.querySelector('[data-pixelito-close]');
    var face = panel ? panel.querySelector('.pixelito-panel-head .pixelito-bubble') : null; // o Pixelito vivo do topo
    var faceImg = face ? face.querySelector('img') : null;
    var form = root.querySelector('[data-pixelito-form]');
    var search = root.querySelector('[data-pixelito-search]');
    var hint = root.querySelector('[data-pixelito-hint]');
    if (!fab || !panel || !log) return;

    var chips = [].slice.call(panel.querySelectorAll('[data-pixelito-q]'));
    var topics = [].slice.call(panel.querySelectorAll('[data-pixelito-topic]'));
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var busy = false;      // o Pixelito está "digitando": ignora novas perguntas até terminar
    var greeted = false;   // a saudação só na 1ª abertura da página
    var skipCurrent = null; // função que completa a digitação em andamento

    function isOpen() { return !panel.hidden; }
    function hideHint() { if (hint) hint.hidden = true; }

    // Troca a expressão do Pixelito do topo (com um "pulinho" pra notar a troca).
    function setFace(kind) {
        var url = log.getAttribute('data-face-' + kind);
        if (!faceImg || !url || faceImg.getAttribute('src') === url) return;
        faceImg.src = url;
        face.classList.remove('is-swap');
        void face.offsetWidth; // reinicia a animação
        face.classList.add('is-swap');
    }
    function scrollDown() { log.scrollTop = log.scrollHeight; }

    // ---------- abrir / fechar ----------
    function open() {
        hideHint();
        panel.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        fab.setAttribute('aria-label', 'Fechar a conversa com o Pixelito');
        root.classList.add('is-open');
        document.documentElement.classList.add('pixelito-chat-open');
        // Já traz as 3 carinhas pro cache: a troca de expressão nunca "pisca" esperando a imagem.
        ['talking', 'idle', 'sad'].forEach(function (k) { new Image().src = log.getAttribute('data-face-' + k) || ''; });
        // Se um pop-up de aviso estava com o Pixelito, ele volta pro painel (notification-toast.js escuta isto).
        document.dispatchEvent(new CustomEvent('pixelito:chat-open'));
        if (!greeted) {
            greeted = true;
            botSay(log.getAttribute('data-greeting') || '', null);
        }
        // Foco na caixa só em tela com mouse/teclado — no celular abriria o teclado virtual por cima da conversa.
        if (search && window.matchMedia('(hover: hover)').matches) {
            search.focus({ preventScroll: true });
        }
        scrollDown();
    }
    function close(returnFocus) {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        fab.setAttribute('aria-label', 'Abrir a conversa com o Pixelito');
        root.classList.remove('is-open');
        document.documentElement.classList.remove('pixelito-chat-open');
        setFace('idle');
        if (returnFocus) fab.focus({ preventScroll: true });
    }
    fab.addEventListener('click', function () { if (isOpen()) { close(false); } else { open(); } });
    if (closeBtn) closeBtn.addEventListener('click', function () { close(true); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) close(true); });
    document.addEventListener('click', function (e) { if (isOpen() && !root.contains(e.target)) close(false); });

    // ---------- mensagens ----------
    function el(tag, cls) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        return node;
    }

    function userSay(text) {
        var msg = el('div', 'pixelito-msg pixelito-msg--user');
        var bubble = el('div', 'pixelito-text');
        bubble.textContent = text;
        msg.appendChild(bubble);
        log.appendChild(msg);
        scrollDown();
    }

    // Avatar fixo ao lado de cada fala do Pixelito (só marca quem falou — SEMPRE a mesma carinha;
    // quem muda de expressão é o do topo do painel, ver setFace).
    function avatar() {
        var wrap = el('span', 'pixelito-bubble');
        var img = document.createElement('img');
        img.src = log.getAttribute('data-face-idle');
        img.alt = '';
        img.width = 96;
        img.height = 96;
        img.draggable = false;
        wrap.appendChild(img);
        return { wrap: wrap, img: img };
    }

    /**
     * O Pixelito responde: "…" por um instante, depois digita `text` e, no fim, mostra o link.
     * `link` = { href, label } ou null. `sad` = usa a carinha triste (resposta "não achei").
     */
    function botSay(text, link, sad, onDone) {
        busy = true;
        setFace(sad ? 'sad' : 'talking'); // o do topo fala (ou fica triste no "não achei")

        var msg = el('div', 'pixelito-msg pixelito-msg--bot');
        var av = avatar();
        var bubble = el('div', 'pixelito-text');
        msg.appendChild(av.wrap);
        msg.appendChild(bubble);
        log.appendChild(msg);

        // Leitor de tela: a fala inteira, de uma vez. O efeito visual abaixo é aria-hidden.
        var full = el('span', 'sr-only');
        full.textContent = text;
        var typed = el('span', 'pixelito-typed');
        typed.setAttribute('aria-hidden', 'true');
        bubble.appendChild(full);
        bubble.appendChild(typed);

        function finish() {
            skipCurrent = null;
            typed.textContent = text;
            setFace(sad ? 'sad' : 'idle'); // terminou de falar (o triste continua até a próxima pergunta)
            if (link && link.href) {
                var a = el('a', 'btn btn-secondary pixelito-go');
                a.href = link.href;
                a.textContent = link.label + ' →';
                bubble.appendChild(a);
            }
            busy = false;
            msg.classList.add('is-done');
            scrollDown();
            if (onDone) onDone();
        }

        if (reduceMotion) { finish(); return; }

        // 1) "digitando…" (três pontinhos)
        var dots = el('span', 'pixelito-dots');
        dots.setAttribute('aria-hidden', 'true');
        dots.innerHTML = '<span></span><span></span><span></span>';
        typed.appendChild(dots);
        scrollDown();

        var timer = null;
        skipCurrent = function () { if (timer) clearTimeout(timer); finish(); };
        bubble.addEventListener('click', function () { if (skipCurrent) skipCurrent(); });

        timer = setTimeout(function () {
            // 2) letra por letra: ~18 ms por caractere, no mínimo 0,7 s e no máximo 2,6 s no total
            typed.textContent = '';
            var total = Math.min(2600, Math.max(700, text.length * 18));
            var tick = 24;
            var perTick = Math.max(1, Math.ceil(text.length / (total / tick)));
            var i = 0;
            (function step() {
                i = Math.min(text.length, i + perTick);
                typed.textContent = text.slice(0, i);
                scrollDown();
                if (i >= text.length) { finish(); return; }
                timer = setTimeout(step, tick);
            })();
        }, 650);
    }

    // ---------- perguntas ----------
    function ask(chip) {
        if (busy) return;
        var q = chip.getAttribute('data-question') || '';
        chip.classList.add('is-asked');
        userSay(q);
        var href = chip.getAttribute('data-link');
        botSay(
            chip.getAttribute('data-answer') || '',
            href ? { href: href, label: chip.getAttribute('data-link-label') || 'Abrir' } : null,
            false
        );
    }
    panel.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-pixelito-q]');
        if (chip) ask(chip);
    });

    function visibleChips() {
        return chips.filter(function (c) { return !c.hidden; });
    }

    // A caixa de baixo filtra as perguntas prontas enquanto se digita.
    function applyFilter() {
        var term = search ? search.value.trim().toLowerCase() : '';
        chips.forEach(function (c) {
            c.hidden = term !== '' && (c.getAttribute('data-text') || '').indexOf(term) === -1;
        });
        topics.forEach(function (t) {
            t.hidden = t.querySelectorAll('[data-pixelito-q]:not([hidden])').length === 0;
        });
    }
    if (search) search.addEventListener('input', applyFilter);

    // Enviar: se bate com alguma pergunta, envia a 1ª (a mensagem mostrada é a PERGUNTA do guia,
    // pra a resposta sempre fazer sentido); se nada bate, o Pixelito diz que não achou.
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (busy) return;
            var typedText = search.value.trim();
            if (typedText === '') return;
            // Prefere a pergunta cujo TÍTULO tem o que foi digitado; senão, a 1ª que só cita na resposta.
            var term = typedText.toLowerCase();
            var candidates = visibleChips();
            var match = candidates.filter(function (c) {
                return (c.getAttribute('data-question') || '').toLowerCase().indexOf(term) !== -1;
            })[0] || candidates[0];
            search.value = '';
            applyFilter();
            if (match) {
                ask(match);
            } else {
                userSay(typedText);
                botSay(
                    log.getAttribute('data-notfound') || '',
                    { href: log.getAttribute('data-notfound-link'), label: log.getAttribute('data-notfound-link-label') || 'Falar com a equipe' },
                    true
                );
            }
        });
    }

    // ---------- balãozinho de boas-vindas: 1x por sessão, nunca durante o tutorial ----------
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
