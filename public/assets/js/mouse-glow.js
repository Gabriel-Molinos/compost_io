// Brilho de fundo que segue o mouse (.app-bg::before, src/styles/input.css).
// Só define as CSS vars --mx/--my em % do viewport — throttled via
// requestAnimationFrame pra não empilhar leitura de mousemove a cada pixel.
// Sem JS ou com prefers-reduced-motion, o CSS já tem fallback (centro fixo /
// efeito desligado) — este script é puramente decorativo, nunca bloqueia nada.
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var raf = null;
    var root = document.documentElement;

    window.addEventListener('mousemove', function (e) {
        if (raf !== null) {
            return;
        }
        raf = requestAnimationFrame(function () {
            var x = (e.clientX / window.innerWidth) * 100;
            var y = (e.clientY / window.innerHeight) * 100;
            root.style.setProperty('--mx', x + '%');
            root.style.setProperty('--my', y + '%');
            raf = null;
        });
    }, { passive: true });
})();
