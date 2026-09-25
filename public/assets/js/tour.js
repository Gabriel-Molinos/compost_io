// Tutorial guiado (só Redator-Chefe, layout/base.php só carrega este script
// quando o usuário tem pelo menos 1 site vinculado — window.COMPOST_TOUR_SITE_ID).
// Passo a passo real sobre a UI de verdade (não uma página de ajuda à parte):
// cada passo destaca um elemento com [data-tour="..."] na página atual; quando
// o próximo passo mora em outra URL, o botão "Próximo" navega pra lá e o
// progresso (índice do passo) fica em sessionStorage até a página carregar de
// novo. Sem biblioteca externa — mesma filosofia do resto do projeto
// (confirm-dialog.js, select-enhance.js).
//
// Entre cada seção do site (Interesses → Metas → Produção → Calendário →
// Relatórios → Inteligência) tem um passo "ponteiro" à parte, que só
// destaca o link certo na sidebar antes de navegar — sem isso, o "Próximo"
// pulava direto de conteúdo pra conteúdo sem nunca mostrar ONDE na sidebar
// aquele próximo passo mora (pedido do responsável, 2026-09-08).
(function () {
    'use strict';

    if (typeof window.COMPOST_TOUR_SITE_ID === 'undefined') {
        return;
    }

    var STORAGE_STEP = 'compost_tour_step';
    var STORAGE_SEEN = 'compost_tour_seen';
    var STORAGE_HISTORY = 'compost_tour_history';

    function siteUrl() {
        return '/sites/' + window.COMPOST_TOUR_SITE_ID;
    }

    // { index } = próximo passo, mesma página. { index, url } = navega antes.
    function next(i) { return { index: i + 1 }; }
    function nextAt(i, url) { return { index: i + 1, url: url }; }

    function isHome(path) { return path === '/'; }
    function isSiteOverview(path) { return /^\/sites\/\d+$/.test(path); }
    function isSiteRules(path) { return /^\/sites\/\d+\/rules$/.test(path); }
    function isSiteGoals(path) { return /^\/sites\/\d+\/goals$/.test(path); }
    function isSiteProduction(path) { return /^\/sites\/\d+\/production$/.test(path); }
    function isArticleShow(path) { return /^\/sites\/\d+\/production\/\d+$/.test(path); }
    function isSiteCalendar(path) { return /^\/sites\/\d+\/calendar$/.test(path); }
    function isSiteReports(path) { return /^\/sites\/\d+\/reports$/.test(path); }
    function isSiteIntelligence(path) { return /^\/sites\/\d+\/intelligence$/.test(path); }

    // Passo "ponteiro": destaca o link da próxima seção na sidebar (cada
    // link de aba de site carrega data-tour="tab-{chave}", ver _dial.php)
    // antes de navegar pra ela.
    function pointerStep(matchFn, tabKey, label, destUrl) {
        return {
            match: matchFn,
            selector: '[data-tour="tab-' + tabKey + '"]',
            title: label,
            text: 'Clique em "' + label + '" no menu lateral.',
            cta: 'Próximo',
            resolveNext: function (i) { return nextAt(i, destUrl()); },
        };
    }

    // Cada passo: match(pathname) diz se ele pertence à página atual;
    // selector é o alvo a destacar (null = card centralizado, sem
    // spotlight — só usado nos dois "bookends", boas-vindas e final, que
    // não apontam pra nada porque não são uma ação); resolveNext(i)
    // decide o que "Próximo" faz.
    var STEPS = [
        {
            match: isHome,
            selector: null,
            title: 'Bem-vindo(a) à COMPOST',
            text: 'Vamos te mostrar o passo a passo pra produzir conteúdo com IA. Leva menos de dois minutos.',
            cta: 'Começar',
            resolveNext: next,
        },
        {
            match: isHome,
            selector: '[data-tour="quick-sites-target"]',
            title: 'Seus sites',
            text: 'Aqui estão os sites que você gerencia. Clique num deles pra abrir — vamos abrir este.',
            cta: 'Próximo',
            resolveNext: function (i) { return nextAt(i, siteUrl()); },
        },
        {
            match: isSiteOverview,
            selector: '[data-tour="site-tabs"]',
            title: 'Seções do site',
            text: 'Identidade editorial, categorias, interesses, memória, metas, calendário, relatórios, inteligência... tudo sobre este site fica aqui. Vamos ver as principais, uma por uma.',
            cta: 'Próximo',
            resolveNext: next,
        },
        pointerStep(isSiteOverview, 'rules', 'Interesses', function () { return siteUrl() + '/rules'; }),
        {
            match: isSiteRules,
            selector: '[data-tour="rules-panels"]',
            title: 'Interesses e não-interesses',
            text: 'Guiam a IA na hora de escolher pauta: interesses puxam a IA pra certos temas, não-interesses evitam outros. Cada regra tem intensidade de 1 (leve) a 5 (forte).',
            cta: 'Próximo',
            resolveNext: next,
        },
        pointerStep(isSiteRules, 'goals', 'Metas', function () { return siteUrl() + '/goals'; }),
        {
            match: isSiteGoals,
            selector: '[data-tour="new-goal"]',
            title: 'Metas editoriais',
            text: 'Uma meta cobre um mês e define quantos artigos produzir — no total e, se quiser, por categoria. Clique aqui pra criar uma.',
            cta: 'Próximo',
            resolveNext: next,
        },
        pointerStep(isSiteGoals, 'production', 'Produção', function () { return siteUrl() + '/production'; }),
        {
            match: isSiteProduction,
            selector: '[data-tour="generate-form"]',
            title: 'Geração automática',
            text: 'Todo dia a IA já gera 1 rascunho sozinha pra este site. Use este formulário só quando quiser um rascunho extra.',
            cta: 'Próximo',
            resolveNext: next,
        },
        {
            match: isSiteProduction,
            selector: '[data-filter-tabs]',
            title: 'Filtrar por status',
            text: 'Filtre os rascunhos por status: em andamento, aprovados, atenção ou descartados.',
            cta: 'Próximo',
            resolveNext: next,
        },
        {
            match: isSiteProduction,
            selector: '[data-tour="article-list"]',
            title: 'Revisar um rascunho',
            text: 'Clique em qualquer rascunho pra abrir, ler o texto completo e decidir o que fazer.',
            cta: 'Próximo',
            // Se tiver algum rascunho "Em revisão" agora, pula direto pra ele
            // e mostra Aprovar/Rejeitar de verdade (próximo passo); sem
            // nenhum em revisão no momento, pula esse passo extra e segue
            // pro ponteiro do Calendário — não trava o tour à toa.
            resolveNext: function (i, target) {
                var href = target && target.getAttribute('data-tour-review-href');
                return href ? nextAt(i, href) : { index: i + 2 };
            },
        },
        {
            match: isArticleShow,
            selector: '[data-tour="review-actions"]',
            title: 'Aprovar ou rejeitar',
            text: '"Aprovar" libera o artigo pra agendamento e publicação. "Rejeitar" pede motivo + justificativa e devolve pra IA regenerar com base nesse feedback — até 3 tentativas antes de bloquear.',
            cta: 'Próximo',
            resolveNext: function (i) { return nextAt(i, siteUrl() + '/production'); },
        },
        pointerStep(isSiteProduction, 'calendar', 'Calendário', function () { return siteUrl() + '/calendar'; }),
        {
            match: isSiteCalendar,
            selector: '[data-tour="calendar-grid"]',
            title: 'Calendário',
            text: 'Depois de aprovado e agendado, acompanhe as publicações aqui — dá pra arrastar um card pra reagendar.',
            cta: 'Próximo',
            resolveNext: next,
        },
        pointerStep(isSiteCalendar, 'reports', 'Relatórios', function () { return siteUrl() + '/reports'; }),
        {
            match: isSiteReports,
            selector: '[data-tour="reports-summary"]',
            title: 'Relatórios',
            text: 'Desempenho do mês — produzidos, aprovados, publicados, taxa de aprovação, custo de IA — com comparação automática contra o mês anterior.',
            cta: 'Próximo',
            resolveNext: next,
        },
        pointerStep(isSiteReports, 'intelligence', 'Inteligência', function () { return siteUrl() + '/intelligence'; }),
        {
            match: isSiteIntelligence,
            selector: '[data-tour="intelligence-header"]',
            title: 'Inteligência editorial',
            text: 'A IA analisa os números e os motivos de rejeição dos últimos meses e escreve, em texto corrido, o que está funcionando e o que ajustar.',
            cta: 'Próximo',
            resolveNext: next,
        },
        {
            match: isSiteIntelligence,
            selector: null,
            title: 'Pronto!',
            text: 'Você já sabe o essencial. Quando quiser rever, o link "Tutorial" continua aqui na barra lateral.',
            cta: 'Concluir',
            resolveNext: next,
        },
    ];

    function getStep() {
        var raw = sessionStorage.getItem(STORAGE_STEP);
        return raw === null ? null : parseInt(raw, 10);
    }

    function setStep(i) { sessionStorage.setItem(STORAGE_STEP, String(i)); }
    function clearTour() {
        sessionStorage.removeItem(STORAGE_STEP);
        sessionStorage.removeItem(STORAGE_HISTORY);
    }

    // Histórico de passos já vistos (não índices previsíveis — o passo de
    // "Revisar um rascunho" pode pular 1 ou 2 posições adiante dependendo
    // se existe rascunho em revisão, então "voltar" precisa lembrar o que
    // foi mostrado de verdade, não só fazer índice-1). Cada entrada é a
    // página (pathname, sem querystring) onde aquele passo apareceu; só
    // pathname porque "?tour=1" da entrada forçada não deve ser reusado ao
    // voltar pra ela (senão reiniciaria o tour em vez de retomar o passo).
    function getHistory() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_HISTORY) || '[]');
        } catch (e) {
            return [];
        }
    }
    function setHistory(h) { sessionStorage.setItem(STORAGE_HISTORY, JSON.stringify(h)); }
    function recordHistory(i) {
        var h = getHistory();
        if (h.length > 0 && h[h.length - 1].index === i) {
            return; // mesmo passo re-renderizado (resize, resume) — não duplica
        }
        h.push({ index: i, path: location.pathname });
        setHistory(h);
    }

    function start() {
        setStep(0);
        setHistory([]);
        if (location.pathname !== '/') {
            location.href = '/';
            return;
        }
        render();
    }

    function goBack() {
        var h = getHistory();
        if (h.length < 2) {
            return; // já é o primeiro passo mostrado — não tem pra onde voltar
        }
        h.pop();
        var prev = h[h.length - 1];
        setHistory(h);
        if (prev.path !== location.pathname) {
            setStep(prev.index);
            location.href = prev.path;
            return;
        }
        goToStep(prev.index);
    }

    var overlayEl, spotlightEl, tooltipEl;

    // Só existe UM Pixelito na tela por vez: durante o tutorial ele "mora" no balão do tour, então a
    // bolinha do canto (layout/_pixelito.php) se retira — CSS: html.pixelito-away-tour. Sai no teardown.
    var AWAY_CLASS = 'pixelito-away-tour';

    function ensureDom() {
        if (overlayEl) { return; }
        document.documentElement.classList.add(AWAY_CLASS);
        overlayEl = document.createElement('div');
        overlayEl.className = 'tour-overlay';
        spotlightEl = document.createElement('div');
        spotlightEl.className = 'tour-spotlight';
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'tour-tooltip';
        document.body.appendChild(overlayEl);
        document.body.appendChild(spotlightEl);
        document.body.appendChild(tooltipEl);
    }

    function teardown() {
        document.documentElement.classList.remove(AWAY_CLASS); // mesmo sem DOM montado (a classe pode ter vindo do <head>)
        if (!overlayEl) { return; }
        [overlayEl, spotlightEl, tooltipEl].forEach(function (el) { el.remove(); });
        overlayEl = spotlightEl = tooltipEl = null;
    }

    function finish() {
        clearTour();
        localStorage.setItem(STORAGE_SEEN, '1');
        teardown();
    }

    function skip() { finish(); }

    function goToStep(i) {
        var step = STEPS[i];
        if (!step) { finish(); return; }
        if (!step.match(location.pathname)) {
            // A página atual não é onde esse passo deveria aparecer (o
            // usuário navegou por conta própria pra outro lugar) — encerra
            // sem forçar nada, silenciosamente.
            clearTour();
            teardown();
            return;
        }
        setStep(i);
        render();
    }

    function render() {
        var i = getStep();
        if (i === null) { return; }
        var step = STEPS[i];
        if (!step || !step.match(location.pathname)) {
            clearTour();
            teardown();
            return;
        }

        var target = step.selector ? document.querySelector(step.selector) : null;
        if (step.selector && !target) {
            // Alvo não existe nesta carga da página (ex.: site sem nenhum
            // rascunho ainda, [data-tour="article-list"] nem chega a
            // renderizar) — pula esse passo específico sem travar o tour.
            goToStep(i + 1);
            return;
        }

        recordHistory(i);
        ensureDom();

        var canGoBack = getHistory().length > 1;
        // O Pixelito é quem "fala" o tutorial: bolinha ao lado do título, com a expressão do momento.
        var titleHtml = '<div class="tour-tooltip-head">' + pixelitoBubble(mood(step, i))
            + '<p class="tour-tooltip-title">' + escapeHtml(step.title) + '</p></div>';
        var textHtml = '<p class="tour-tooltip-text">' + escapeHtml(step.text) + '</p>';
        var progressHtml = '<p class="tour-tooltip-progress">Passo ' + (i + 1) + ' de ' + STEPS.length + '</p>';
        var ctaLabel = escapeHtml(step.cta);
        var backHtml = canGoBack ? '<button type="button" class="tour-back">← Anterior</button>' : '';
        tooltipEl.innerHTML = titleHtml + textHtml + progressHtml
            + '<div class="tour-tooltip-actions">'
            + '<button type="button" class="tour-skip">Pular tutorial</button>'
            + '<div class="tour-tooltip-actions-right">' + backHtml
            + '<button type="button" class="btn btn-primary tour-next">' + ctaLabel + '</button>'
            + '</div></div>';

        tooltipEl.querySelector('.tour-skip').addEventListener('click', skip);
        if (canGoBack) {
            tooltipEl.querySelector('.tour-back').addEventListener('click', goBack);
        }
        tooltipEl.querySelector('.tour-next').addEventListener('click', function () {
            var result = step.resolveNext(i, target);
            if (result.url) {
                setStep(result.index);
                location.href = result.url;
                return;
            }
            goToStep(result.index);
        });

        positionAround(target);
    }

    function positionAround(target) {
        if (!target) {
            spotlightEl.style.display = 'none';
            tooltipEl.classList.add('tour-tooltip-centered');
            return;
        }
        tooltipEl.classList.remove('tour-tooltip-centered');
        spotlightEl.style.display = 'block';

        var place = function () {
            var rect = target.getBoundingClientRect();
            var pad = 6;
            spotlightEl.style.top = (rect.top - pad) + 'px';
            spotlightEl.style.left = (rect.left - pad) + 'px';
            spotlightEl.style.width = (rect.width + pad * 2) + 'px';
            spotlightEl.style.height = (rect.height + pad * 2) + 'px';

            var tRect = tooltipEl.getBoundingClientRect();
            var spaceBelow = window.innerHeight - rect.bottom;
            var top = spaceBelow > tRect.height + 24
                ? rect.bottom + 14
                : Math.max(12, rect.top - tRect.height - 14);
            var left = Math.min(Math.max(12, rect.left), window.innerWidth - tRect.width - 12);
            tooltipEl.style.top = top + 'px';
            tooltipEl.style.left = left + 'px';
        };

        // Alvo dentro do dial da sidebar (dial.js): traz o item pro centro antes de destacar.
        if (window.CompostWheel) { window.CompostWheel.reveal(target); }
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        place();
        setTimeout(place, 350); // depois do smooth-scroll assentar
        setTimeout(place, 800); // e depois do giro do tambor
    }

    // Expressão do Pixelito por momento do tutorial: acolhe na abertura, comemora no
    // fim, chama a atenção nos passos que só apontam pro menu; nos demais, explica.
    // (nomes válidos: App\Support\Pixelito::EXPRESSIONS; o base vem de window.COMPOST_PIXELITO)
    function mood(step, i) {
        if (i === 0) { return 'falando'; }
        if (i === STEPS.length - 1) { return 'falando-orgulhoso'; }
        if (step.selector && step.selector.indexOf('[data-tour="tab-') === 0) { return 'falando-confiante'; }
        return 'falando';
    }

    function pixelitoBubble(expr) {
        var px = window.COMPOST_PIXELITO;
        if (!px) { return ''; } // layout sem o mapa: o tutorial funciona igual, só sem a carinha
        return '<span class="pixelito-bubble"><img src="' + px.base + expr + '.webp" alt="" width="96" height="96" draggable="false"></span>';
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forced = new URLSearchParams(location.search).get('tour') === '1';
        if (forced) {
            start();
            return;
        }
        var resuming = getStep() !== null;
        if (resuming) {
            render();
            return;
        }
        if (location.pathname === '/' && !localStorage.getItem(STORAGE_SEEN)) {
            start();
            return;
        }
        document.documentElement.classList.remove(AWAY_CLASS); // nenhum tour rodando: a bolinha do canto fica
    });
})();
