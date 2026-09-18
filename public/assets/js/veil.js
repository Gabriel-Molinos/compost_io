/**
 * COMPOST — véu de transição de página (CSS: `.page-veil`, src/styles/input.css).
 * Em QUALQUER troca de página — link, botão que envia formulário, redirecionamento
 * depois de salvar —, um degradê cobre a tela (menos o dial da sidebar, que fica por
 * cima) e só sai quando a página nova carregou: sem tempo fixo, dura o quanto a troca
 * durar. Entre duas páginas de verdade, então o estado viaja em sessionStorage e o
 * script do <head> (layout/_veil_head.php) já liga `veil-on` antes do primeiro quadro
 * da página nova.
 *   html.veil-in   página antiga: varre pra dentro e segura
 *   html.veil-on   página nova, ao nascer: já coberta
 *   html.veil-out  página nova pronta: varre pra fora
 * Não interfere em: clique com Ctrl/Cmd/Shift, nova aba, download, âncora (#), link
 * de outro site, `data-no-veil`. Sem JS a navegação é a normal. `prefers-reduced-motion`:
 * sem véu.
 */
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;
  var LEAD = 200; // ms entre o clique e a navegação: o véu já cobre quase tudo quando a página antiga sai
  var stuck = 0;

  function clear() {
    root.classList.remove('veil-in', 'veil-on', 'veil-out');
    try { window.sessionStorage.removeItem('compost:veil'); } catch (e) { /* ok */ }
  }

  function start() {
    if (reduced) { return; }
    try { window.sessionStorage.setItem('compost:veil', String(Date.now())); } catch (e) { /* sem véu na chegada */ }
    root.classList.add('veil-in');
    // Garantia: navegação que não aconteceu (download, cancelada) não deixa a tela coberta.
    window.clearTimeout(stuck);
    stuck = window.setTimeout(clear, 20000);
  }

  function release() {
    if (!root.classList.contains('veil-on')) { return; }
    root.classList.remove('veil-on');
    root.classList.add('veil-out');
    var done = function () { root.classList.remove('veil-out'); };
    var veil = document.querySelector('.page-veil');
    if (veil) { veil.addEventListener('animationend', done, { once: true }); }
    window.setTimeout(done, 1200);
  }

  /** Navega pra `url` depois de deixar o véu entrar. */
  function go(url) {
    start();
    if (reduced) { window.location.href = url; return; }
    window.setTimeout(function () { window.location.href = url; }, LEAD);
  }

  // Página nova pronta: dois quadros depois do parse (o conteúdo já pintou por baixo do véu).
  window.requestAnimationFrame(function () { window.requestAnimationFrame(release); });
  window.setTimeout(release, 20000);
  // Voltar/avançar (bfcache) restaura a página antiga com o véu ainda ligado: limpa.
  window.addEventListener('pageshow', function (e) { if (e.persisted) { clear(); } });

  function sameOriginNavigation(a) {
    if (!a.href || a.hasAttribute('download') || a.hasAttribute('data-no-veil')) { return false; }
    if (a.target && a.target !== '_self') { return false; }
    var raw = a.getAttribute('href') || '';
    if (raw.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(raw)) { return false; }
    var url;
    try { url = new URL(a.href, window.location.href); } catch (e) { return false; }
    if (url.origin !== window.location.origin) { return false; }
    // Só muda a âncora da mesma página: não é troca de página.
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== '') { return false; }
    return true;
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
    var a = e.target instanceof Element ? e.target.closest('a[href]') : null;
    if (!a || !sameOriginNavigation(a)) { return; }
    e.preventDefault();
    go(a.href);
  });

  // Formulários (salvar, gerar rascunho, sair…): o véu entra e o envio segue na hora — o
  // botão que enviou (name/value) precisa ir junto, então não adiamos o submit.
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) { return; }
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || (form.target && form.target !== '_self') || form.hasAttribute('data-no-veil')) { return; }
    start();
  });

  window.CompostVeil = { start: start, go: go, release: release };
})();
