// Pré-visualização instantânea de logo/foto de perfil (achado real
// 2026-09-15: a imagem só aparecia depois de salvar e a página recarregar —
// devia mostrar assim que o redator escolhe um arquivo ou uma logo da
// biblioteca). Nunca manda nada pro servidor — só troca o que aparece na
// tela até o formulário ser enviado de verdade.
//
// Transição (pedido do responsável, 2026-09-18, tela do site: "a parte de
// escolher a logo ter uma transição ao clicar em alguma"): o container que
// tem `data-avatar-transition` troca com crossfade — a imagem nova entra por
// cima (fade + "pop" + desfoque que some) enquanto a antiga sai — e o
// ancestral `[data-swap-pulse]` (a placa branca do topo) dá um pulso de luz.
// Sem o atributo a troca continua instantânea, como antes (tela de usuário,
// perfil). `prefers-reduced-motion` desliga a animação.
(function () {
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function readAsDataUrl(file, cb) {
        var reader = new FileReader();
        reader.onload = function (e) { cb(e.target.result); };
        reader.readAsDataURL(file);
    }

    function pulse(container) {
        var plate = container.closest('[data-swap-pulse]');
        if (!plate) return;
        plate.classList.remove('logo-pulse');
        void plate.offsetWidth; // reinicia a animação se clicarem em sequência
        plate.classList.add('logo-pulse');
    }

    // Troca o conteúdo do container por `html` (uma <img> ou o estado vazio).
    function swapHtml(container, html) {
        if (reduced || !container.hasAttribute('data-avatar-transition')) {
            container.innerHTML = html;
            return;
        }

        // Cliques em sequência: uma troca só termina depois de 420ms — o token
        // garante que só a mais recente limpa o que sobrou.
        var token = (container.__swapToken || 0) + 1;
        container.__swapToken = token;
        while (container.children.length > 1) {
            container.removeChild(container.firstElementChild);
        }
        var old = container.firstElementChild;

        var layer = document.createElement('span');
        layer.className = 'avatar-swap-layer';
        layer.innerHTML = html;
        container.appendChild(layer);

        if (old) old.classList.add('avatar-swap-out');
        void layer.offsetWidth; // fixa o estado inicial antes de animar
        layer.classList.add('avatar-swap-in');
        pulse(container);

        window.setTimeout(function () {
            if (container.__swapToken !== token) return;
            if (old && old.parentNode === container) container.removeChild(old);
            // A camada vira conteúdo normal (mesmo elemento, sem recarregar a imagem).
            while (layer.firstChild) container.insertBefore(layer.firstChild, layer);
            container.removeChild(layer);
        }, 440);
    }

    function swapPreview(container, src) {
        // Nunca copia a classe do elemento atual: antes da primeira foto, o
        // que está lá é o <span> das iniciais (classes de flex/cor bem
        // diferentes de um <img> de verdade) — usa sempre a classe "de img"
        // de propósito, gravada pelo PHP (Avatar::imgClass()).
        var className = container.getAttribute('data-avatar-img-class') || '';
        swapHtml(container, '<img src="' + src + '" alt="" class="' + className + '">');
    }

    document.querySelectorAll('[data-avatar-preview]').forEach(function (container) {
        // Escopo explícito (não `closest('form')`): em profile/edit.php a
        // pré-visualização fica FORA do <form> (é só leitura ali, quem
        // envia é um formulário vizinho) — o wrapper comum é quem sabe
        // onde procurar o input de arquivo, não a árvore do form.
        var scope = container.closest('[data-avatar-scope]');
        if (!scope) return;

        var initialHtml = container.innerHTML;
        var emptyTpl = scope.querySelector('template[data-avatar-empty]');
        var removeBox = scope.querySelector('input[name="remove_logo"], input[name="remove_avatar"]');
        var clearBtn = scope.querySelector('[data-library-clear]');

        // Escolher uma imagem nova desfaz um "remover" marcado antes — senão o
        // servidor (que processa o remover primeiro) ignoraria a escolha.
        function chose() {
            if (removeBox) removeBox.checked = false;
            if (clearBtn) clearBtn.classList.remove('hidden');
        }

        var fileInput = scope.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) return;
                // Upload manual tem prioridade sobre a biblioteca (SiteController::resolveLogo).
                scope.querySelectorAll('input[name="library_logo"]').forEach(function (r) { r.checked = false; });
                chose();
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
                if (fileInput) fileInput.value = '';
                chose();
                // Miniatura ainda não carregada (rolagem lazy) só tem data-src.
                var src = thumb ? (thumb.getAttribute('src') ? thumb.src : thumb.getAttribute('data-src')) : null;
                if (src) swapPreview(container, src);
            });
        });

        // "Remover logo": mostra o estado vazio na hora; desmarcar volta ao que estava.
        if (removeBox && emptyTpl) {
            removeBox.addEventListener('change', function () {
                swapHtml(container, removeBox.checked ? emptyTpl.innerHTML : initialHtml);
            });
        }

        // "Voltar à logo atual": solta a escolha da biblioteca e o arquivo escolhido.
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                scope.querySelectorAll('input[name="library_logo"]').forEach(function (r) { r.checked = false; });
                if (fileInput) fileInput.value = '';
                clearBtn.classList.add('hidden');
                swapHtml(container, initialHtml);
            });
        }
    });
})();
