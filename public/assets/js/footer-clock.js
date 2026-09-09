// Relógio ao vivo do rodapé (layout/base.php e layout/auth.php).
// new Date() é sempre o instante real (correto independente do fuso do
// aparelho); Intl.DateTimeFormat com timeZone fixo em America/Sao_Paulo
// converte pra Brasília na exibição — sem precisar sincronizar com o
// servidor nem confiar no relógio/fuso configurado no navegador do usuário.
(function () {
    // querySelectorAll (não só o primeiro): sidebar (desktop) e o menu
    // mobile carregam seu próprio [data-clock] — os dois existem no DOM ao
    // mesmo tempo (só um visível por vez via CSS), então precisam dos dois
    // atualizando juntos.
    var els = document.querySelectorAll('[data-clock]');
    if (els.length === 0) {
        return;
    }

    var fmt = new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    function tick() {
        var text = fmt.format(new Date());
        els.forEach(function (el) { el.textContent = text; });
    }

    tick();
    setInterval(tick, 1000);
})();
