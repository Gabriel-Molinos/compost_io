/**
 * COMPOST — sidebar radial "dial" (pedido do responsável, 2026-09-18: uma sidebar
 * redonda tipo "rotary carousel / circular carousel drag / radial selector /
 * rotary dial", com transição de rolagem dos itens ao mudar de seção e um
 * degradê cobrindo a página na troca). O CSS (bloco "Sidebar radial" em
 * src/styles/input.css) desenha o disco, o anel e as marcas; aqui é a geometria
 * e o movimento:
 *   - os itens (`a.dial-item` dentro de `[data-ring]`) ficam ao longo de um ARCO
 *     (o anel é um círculo de centro fora da tela, à esquerda — mesma fórmula do
 *     CSS: Ro = 1,1017·H) e o do centro é o selecionado; ao mudar, o mostrador
 *     GIRA (itens e marcas) até o item novo;
 *   - ao carregar uma página, o dial NASCE na posição da página anterior
 *     (sessionStorage) e ROLA até o item da página nova;
 *   - clicar num item fora do centro rola até ele e só então navega;
 *   - arrastar (mouse/toque), roda do mouse, setas ↑/↓ e Tab giram o dial; parado
 *     ~2,6 s ele volta pro item da página atual;
 *   - com poucos itens cabendo no arco a lista não dá a volta (sem repetição);
 *   - o × recolhe a coluna (desktop, lembrado em localStorage) / fecha a gaveta
 *     (celular); o botão redondo reabre.
 * Véu de transição: ao clicar num item, um degradê cobre tudo menos a sidebar e
 * só sai depois que a página nova carregou — sem tempo fixo (ver `.page-veil`).
 * Sem JS a lista continua normal e usável; `prefers-reduced-motion`: nada anima
 * (o dial aparece já na posição certa, ainda em arco).
 */
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;
  var desktop = window.matchMedia('(min-width: 1024px)');
  var dials = [];

  // ── Recolher / abrir ────────────────────────────────────────────────────
  function isOpen() { return desktop.matches ? !root.classList.contains('dial-closed') : root.classList.contains('dial-open'); }

  function setOpen(open) {
    if (desktop.matches) {
      root.classList.toggle('dial-closed', !open);
      try { window.localStorage.setItem('compost:dial', open ? 'open' : 'closed'); } catch (e) { /* sem lembrar */ }
    } else {
      root.classList.toggle('dial-open', open);
    }
    // A geometria do arco não muda com a largura, mas garante o desenho certo depois da animação.
    window.setTimeout(function () { dials.forEach(function (d) { d.layout(); }); }, 520);
  }

  document.addEventListener('click', function (e) {
    var t = e.target instanceof Element ? e.target.closest('[data-dial-toggle]') : null;
    if (t) { setOpen(!isOpen()); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !desktop.matches && root.classList.contains('dial-open')) { setOpen(false); }
  });
  // Gaveta aberta no celular e a tela virou desktop (ou o contrário): não deixa estado preso.
  desktop.addEventListener('change', function () { root.classList.remove('dial-open'); });

  // ── Véu de transição de página (CSS: `.page-veil`) ──────────────────────
  function startVeil() {
    if (reduced) { return; }
    try { window.sessionStorage.setItem('compost:veil', String(Date.now())); } catch (e) { /* sem véu na chegada */ }
    root.classList.add('veil-in');
  }

  function releaseVeil() {
    if (!root.classList.contains('veil-on')) { return; }
    root.classList.remove('veil-on');
    root.classList.add('veil-out');
    var done = function () { root.classList.remove('veil-out'); };
    var veil = document.querySelector('.page-veil');
    if (veil) { veil.addEventListener('animationend', done, { once: true }); }
    window.setTimeout(done, 1200); // garantia: nunca deixa o véu preso
  }

  // Página nova pronta: dois quadros depois do parse (o conteúdo já pintou por baixo do véu).
  window.requestAnimationFrame(function () { window.requestAnimationFrame(releaseVeil); });
  window.setTimeout(releaseVeil, 10000);
  // Voltar/avançar (bfcache) restaura a página antiga com o véu ainda ligado: limpa.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { root.classList.remove('veil-in', 'veil-on', 'veil-out'); }
  });
  // Itens fora do dial giratório (lista simples sem JS de arco, atalhos redondos) também usam o véu.
  document.addEventListener('click', function (e) {
    var a = e.target instanceof Element ? e.target.closest('.dial a[href]') : null;
    if (!a || a.closest('.dial-ring.is-live') || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
    if (a.target && a.target !== '_self') { return; }
    startVeil();
  });

  // ── Matemática do mostrador ─────────────────────────────────────────────
  function mod(x, n) { return ((x % n) + n) % n; }
  function wrap(x, n) { x = mod(x, n); return x > n / 2 ? x - n : x; }
  function clamp(x, a, b) { return Math.max(a, Math.min(b, x)); }

  function storage(key, value) {
    try {
      if (value === undefined) { return window.sessionStorage.getItem(key); }
      window.sessionStorage.setItem(key, String(value));
    } catch (e) { /* armazenamento bloqueado: só perde a animação de chegada */ }
    return null;
  }

  function init(dial) {
    var panel = dial.querySelector('.dial-panel');
    var ring = dial.querySelector('[data-ring]');
    var ticks = dial.querySelector('[data-dial-ticks]');
    if (!panel || !ring) { return; }
    var links = [].slice.call(ring.querySelectorAll('a.dial-item'));
    var n = links.length;
    if (n === 0) { return; }
    var key = 'compost:dial:pos';

    var g = {};            // geometria (recalculada em layout())
    var loop = false;
    var activeIdx = 0;
    links.forEach(function (a, i) { if (a.getAttribute('aria-current') === 'page') { activeIdx = i; } });

    var pos = activeIdx;
    var raf = 0;
    var idle = 0;

    // Mesma fórmula do CSS: o arco externo passa por (W, H/2) e recua 12% de H nas pontas.
    function layout() {
      var H = dial.clientHeight || window.innerHeight;
      var W = panel.clientWidth || 368;
      var em = parseFloat(window.getComputedStyle(root).fontSize) || 16;
      var s = 0.12 * H;
      g.H = H;
      g.W = W;
      g.band = 9 * em;                                    // --band
      g.Ro = ((H / 2) * (H / 2) + s * s) / (2 * s);
      g.Rm = g.Ro - g.band / 2;                              // raio da linha dos itens (meio do anel)
      g.cx = W - g.Ro;                                       // centro do círculo (fora da tela)
      g.itemW = g.band - 1.5 * em;
      g.slot = clamp(H * 0.075, 50, 66);                     // distância entre itens ao longo do arco
      g.step = g.slot / g.Rm;                                // ângulo entre itens (rad)
      g.V = Math.max(1, Math.floor((H - 150) / g.slot));     // quantos cabem no arco
      if (g.V % 2 === 0) { g.V -= 1; }
      g.half = g.V / 2 + 0.5;
      loop = n > g.V;
      render(pos);
    }

    function render(p) {
      if (!g.Rm) { return; }
      for (var i = 0; i < n; i++) {
        var d = loop ? wrap(i - p, n) : i - p;
        var a = Math.abs(d);
        var el = links[i];
        if (a > g.half + 0.5) {
          el.style.opacity = '0';
          el.style.pointerEvents = 'none';
          el.removeAttribute('data-center');
          continue;
        }
        var th = d * g.step;
        var xm = g.cx + g.Rm * Math.cos(th);                 // ponto no meio do anel
        var ym = g.H / 2 + g.Rm * Math.sin(th);
        var left = xm - g.itemW / 2;
        var sc = 0.88 - 0.02 * Math.min(a, 4) + 0.2 * Math.max(0, 1 - a);
        var op = clamp(1 - 0.075 * a, 0.38, 1) * clamp(g.half + 0.5 - a, 0, 1);
        el.style.setProperty('--dt', 'translate3d(' + left.toFixed(1) + 'px,' + ym.toFixed(1) + 'px,0) rotate(' + (th * 0.55).toFixed(4) + 'rad) scale(' + sc.toFixed(3) + ')');
        el.style.opacity = op.toFixed(3);
        el.style.zIndex = String(100 - Math.round(a * 10));
        el.style.pointerEvents = op < 0.1 ? 'none' : '';
        if (a < 0.5) { el.setAttribute('data-center', ''); } else { el.removeAttribute('data-center'); }
      }
      // As marcas do mostrador giram junto com a seleção (mesmo passo angular).
      if (ticks) { dial.style.setProperty('--spin', (-p * g.step * 180 / Math.PI).toFixed(3) + 'deg'); }
    }

    function stop() { if (raf) { window.cancelAnimationFrame(raf); raf = 0; } }

    function limit(target) { return loop ? target : clamp(target, 0, n - 1); }

    function rollTo(target, ms, done) {
      stop();
      var to = loop ? pos + wrap(target - pos, n) : clamp(target, 0, n - 1); // caminho mais curto no círculo
      var from = pos;
      if (reduced || ms <= 0 || Math.abs(to - from) < 0.001) {
        pos = loop ? mod(to, n) : to;
        render(pos);
        if (done) { done(); }
        return;
      }
      var t0 = 0;
      (function step(t) {
        if (!t0) { t0 = t; }
        var k = Math.min(1, (t - t0) / ms);
        var e = 1 - Math.pow(1 - k, 3); // easeOutCubic: desacelera como um mostrador
        pos = from + (to - from) * e;
        render(pos);
        if (k < 1) {
          raf = window.requestAnimationFrame(step);
        } else {
          raf = 0;
          pos = loop ? mod(to, n) : to;
          render(pos);
          if (done) { done(); }
        }
      })(performance.now());
    }

    function scheduleReturn(ms) {
      window.clearTimeout(idle);
      idle = window.setTimeout(function () { rollTo(activeIdx, 560); }, ms);
    }

    ring.classList.add('is-live');
    layout();
    window.addEventListener('resize', layout);

    // Chegada: nasce onde a página anterior deixou e rola até o item desta página.
    var prev = parseFloat(storage(key) || '');
    if (!isNaN(prev) && prev >= 0 && prev < n && prev !== activeIdx) { pos = prev; }
    storage(key, activeIdx);
    render(pos);
    if (pos !== activeIdx) { window.setTimeout(function () { rollTo(activeIdx, 800); }, 150); }

    // Clique num item fora do centro: rola até ele e só então navega.
    ring.addEventListener('click', function (e) {
      var a = e.target instanceof Element ? e.target.closest('a.dial-item') : null;
      if (!a || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
      var i = links.indexOf(a);
      if (i < 0) { return; }
      storage(key, i);
      startVeil();
      var dist = loop ? Math.abs(wrap(i - pos, n)) : Math.abs(i - pos);
      if (dist < 0.5) { return; } // já no centro: navegação normal
      e.preventDefault();
      window.clearTimeout(idle);
      rollTo(i, 360, function () { window.location.href = a.href; });
    });

    // Arrastar (mouse/toque) gira o dial, com inércia; ao soltar, encaixa no item mais próximo.
    // O arrasto só "pega" depois de ~6px, então um clique simples num item continua sendo clique.
    var drag = null;
    var justDragged = false;
    links.forEach(function (a) { a.setAttribute('draggable', 'false'); });
    panel.addEventListener('pointerdown', function (e) {
      if (e.button !== 0 || (e.target instanceof Element && e.target.closest('[data-nodrag]'))) { return; }
      drag = { id: e.pointerId, y0: e.clientY, pos0: pos, moved: false, lastY: e.clientY, lastT: performance.now(), v: 0 };
    });
    panel.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.id) { return; }
      var dy = e.clientY - drag.y0;
      if (!drag.moved) {
        if (Math.abs(dy) < 6) { return; }
        drag.moved = true;
        stop();
        window.clearTimeout(idle);
        dial.classList.add('is-dragging');
        try { panel.setPointerCapture(e.pointerId); } catch (err) { /* ignora */ }
      }
      var now = performance.now();
      var dt = Math.max(1, now - drag.lastT);
      drag.v = 0.8 * drag.v + 0.2 * ((e.clientY - drag.lastY) / dt); // px/ms suavizado
      drag.lastY = e.clientY;
      drag.lastT = now;
      pos = limit(drag.pos0 - dy / g.slot); // arrastar pra cima faz a seleção descer
      render(pos);
    });
    function endDrag(e) {
      if (!drag || e.pointerId !== drag.id) { return; }
      var d = drag;
      drag = null;
      if (!d.moved) { return; }
      dial.classList.remove('is-dragging');
      justDragged = true;
      window.setTimeout(function () { justDragged = false; }, 0);
      // Inércia: continua um pouco no sentido do arrasto (no máx. 3 itens) e encaixa.
      var carry = clamp(-(d.v * 220) / g.slot, -3, 3);
      rollTo(Math.round(pos + carry), 420);
      scheduleReturn(3200);
    }
    panel.addEventListener('pointerup', endDrag);
    panel.addEventListener('pointercancel', endDrag);
    // Depois de um arrasto o navegador ainda dispara "click" no item solto: engole.
    panel.addEventListener('click', function (e) {
      if (justDragged) { e.preventDefault(); e.stopImmediatePropagation(); }
    }, true);

    // Roda do mouse gira o dial.
    var lastWheel = 0;
    panel.addEventListener('wheel', function (e) {
      if (Math.abs(e.deltaY) < 4) { return; }
      e.preventDefault();
      var now = performance.now();
      if (now - lastWheel < 130) { return; }
      lastWheel = now;
      rollTo(Math.round(pos) + (e.deltaY > 0 ? 1 : -1), 260);
      scheduleReturn(2600);
    }, { passive: false });

    // Foco (Tab) e setas: o dial acompanha.
    ring.addEventListener('focusin', function (e) {
      var a = e.target instanceof Element ? e.target.closest('a.dial-item') : null;
      var i = a ? links.indexOf(a) : -1;
      if (i < 0) { return; }
      rollTo(i, 320);
      scheduleReturn(4000);
    });
    ring.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
      var i = links.indexOf(document.activeElement);
      if (i < 0) { return; }
      e.preventDefault();
      var next = i + (e.key === 'ArrowDown' ? 1 : -1);
      links[loop ? mod(next, n) : clamp(next, 0, n - 1)].focus({ preventScroll: true });
    });

    dials.push({
      dial: dial,
      layout: layout,
      reveal: function (el, holdMs) {
        var i = links.indexOf(el);
        if (i < 0) { return; }
        rollTo(i, 380);
        scheduleReturn(holdMs || 30000);
      },
    });
  }

  document.querySelectorAll('[data-dial]').forEach(init);

  // Tutorial guiado (tour.js): antes de destacar um item, traz ele pro centro do dial.
  window.CompostWheel = {
    reveal: function (el) {
      for (var i = 0; i < dials.length; i++) {
        var ringEl = dials[i].dial.querySelector('[data-ring]');
        if (ringEl && ringEl.contains(el) && el !== ringEl) { dials[i].reveal(el); return true; }
      }
      return false;
    },
  };
})();
