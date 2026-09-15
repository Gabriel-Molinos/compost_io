// Pré-visualização instantânea de logo/foto de perfil (achado real
// 2026-09-15: a imagem só aparecia depois de salvar e a página recarregar —
// devia mostrar assim que o redator escolhe um arquivo ou uma logo da
// biblioteca). Nunca manda nada pro servidor — só troca o que aparece na
// tela até o formulário ser enviado de verdade.
(function () {
    function readAsDataUrl(file, cb) {
        var reader = new FileReader();
        reader.onload = function (e) { cb(e.target.result); };
        reader.readAsDataURL(file);
    }

    function swapPreview(container, src) {
        // Nunca copia a classe do elemento atual: antes da primeira foto, o
        // que está lá é o <span> das iniciais (classes de flex/cor bem
        // diferentes de um <img> de verdade) — usa sempre a classe "de img"
        // de propósito, gravada pelo PHP (Avatar::imgClass()).
        var className = container.getAttribute('data-avatar-img-class') || '';
        container.innerHTML = '<img src="' + src + '" alt="" class="' + className + '">';
    }

    document.querySelectorAll('[data-avatar-preview]').forEach(function (container) {
        // Escopo explícito (não `closest('form')`): em profile/edit.php a
        // pré-visualização fica FORA do <form> (é só leitura ali, quem
        // envia é um formulário vizinho) — o wrapper comum é quem sabe
        // onde procurar o input de arquivo, não a árvore do form.
        var scope = container.closest('[data-avatar-scope]');
        if (!scope) return;

        var fileInput = scope.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) return;
                readAsDataUrl(file, function (dataUrl) { swapPreview(container, dataUrl); });
            });
        }

        // Seletor visual da biblioteca de logos (sites/form.php) — cada
        // thumbnail já carrega a imagem real, só reaproveita o src dela.
        scope.querySelectorAll('input[name="library_logo"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) return;
                var label = radio.closest('label');
                var thumb = label ? label.querySelector('img') : null;
                if (thumb) swapPreview(container, thumb.src);
            });
        });
    });
})();
