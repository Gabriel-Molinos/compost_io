// Relógio ao vivo do rodapé (layout/base.php e layout/auth.php).
// new Date() é sempre o instante real (correto independente do fuso do
// aparelho); Intl.DateTimeFormat com timeZone fixo em America/Sao_Paulo
// converte pra Brasília na exibição — sem precisar sincronizar com o
// servidor nem confiar no relógio/fuso configurado no navegador do usuário.
(function () {
    var el = document.querySelector('[data-clock]');
    if (!el) {
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
        el.textContent = fmt.format(new Date());
    }

    tick();
    setInterval(tick, 1000);
})();
