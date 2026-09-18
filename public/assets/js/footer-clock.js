// Relógio ao vivo do cartão da sidebar (layout/_clock.php, incluído no
// layout/base.php duas vezes: sidebar desktop e menu mobile).
// new Date() é sempre o instante real (correto independente do fuso do
// aparelho); Intl.DateTimeFormat com timeZone fixo em America/Sao_Paulo
// converte pra Brasília na exibição — sem precisar sincronizar com o
// servidor nem confiar no relógio/fuso configurado no navegador do usuário.
// querySelectorAll (não só o primeiro): os dois cartões existem no DOM ao
// mesmo tempo (só um visível por vez via CSS) e atualizam juntos.
(function () {
    var roots = document.querySelectorAll('[data-clock-root]');
    if (roots.length === 0) {
        return;
    }

    var fmt = new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    // "sex." → "SEX", "set." → "SET" (o ponto da abreviação sai).
    function up(text) { return text.replace('.', '').toUpperCase(); }

    var lastSecond = -1;

    function tick() {
        var p = {};
        fmt.formatToParts(new Date()).forEach(function (part) { p[part.type] = part.value; });
        // hour12:false pode devolver "24" à meia-noite em alguns navegadores.
        var hour = p.hour === '24' ? '00' : p.hour;
        var sec = parseInt(p.second, 10);
        var date = p.day + ' ' + up(p.month) + ' ' + p.year;

        roots.forEach(function (root) {
            var set = function (sel, text) {
                var el = root.querySelector(sel);
                if (el && el.textContent !== text) { el.textContent = text; }
            };
            set('[data-clock-h]', hour);
            set('[data-clock-m]', p.minute);
            set('[data-clock-s]', p.second);
            set('[data-clock-date]', date);
            set('[data-clock-wd]', up(p.weekday));

            var bar = root.querySelector('[data-clock-bar]');
            if (bar) {
                // Na virada do minuto a barra volta a zero sem "rebobinar" animando.
                var wrap = sec === 0 && lastSecond !== -1;
                bar.classList.toggle('no-tween', wrap || lastSecond === -1);
                bar.style.setProperty('--sec', String(Math.min(1, (sec + 1) / 60)));
            }
        });
        lastSecond = sec;
    }

    tick();
    setInterval(tick, 1000);
})();
