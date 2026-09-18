<script>
    // Véu de transição (assets/js/veil.js): se a página anterior foi deixada por um clique/envio,
    // esta já nasce coberta (sem piscar) até o veil.js liberar. Vale 2 min (um envio que demora —
    // gerar rascunho com IA — ainda encontra o véu esperando).
    (function () {
        document.documentElement.classList.add('js');
        try {
            var t = parseInt(sessionStorage.getItem('compost:veil') || '', 10);
            sessionStorage.removeItem('compost:veil');
            if (t && Date.now() - t < 120000) { document.documentElement.classList.add('veil-on'); }
        } catch (e) { /* sem sessionStorage: sem véu */ }
    })();
</script>
