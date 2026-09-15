/**
 * COMPOST — editor visual do corpo do artigo (production/show.php, "Editar
 * corpo"), pedido real do responsável (2026-09-14): o redator não devia
 * precisar entender HTML pra editar, igual o editor clássico do WordPress.
 * TinyMCE substitui o textarea de HTML puro; ao submeter o formulário, ele
 * mesmo sincroniza o HTML de volta pro textarea escondido — o servidor
 * recebe exatamente o mesmo campo `content_html` de sempre
 * (`ProductionController::updateContent()` não mudou nada).
 *
 * Os botões "Inserir" dos painéis de link (interno/externo) e o de "Link
 * personalizado" inserem direto no cursor do editor, em vez de copiar pra
 * área de transferência — colar HTML como texto puro dentro de um editor
 * visual apareceria como texto literal, não como link de verdade.
 */
(function () {
  'use strict';

  function feedback(btn, message) {
    var original = btn.textContent;
    btn.textContent = message;
    btn.disabled = true;
    setTimeout(function () {
      btn.textContent = original;
      btn.disabled = false;
    }, 1500);
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text);
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy');
    } catch (e) {
      // sem fallback — raro hoje em dia.
    }
    document.body.removeChild(ta);
  }

  function getEditor() {
    return window.tinymce ? tinymce.get('content-html-editor') : null;
  }

  function bindLinkButtons() {
    document.querySelectorAll('[data-insert-link]').forEach(function (btn) {
      if (btn.dataset.insertBound === '1') {
        return;
      }
      btn.dataset.insertBound = '1';
      btn.addEventListener('click', function () {
        var snippet = btn.getAttribute('data-insert-link') || '';
        var editor = getEditor();
        if (editor) {
          editor.insertContent(snippet);
          editor.focus();
          feedback(btn, 'Inserido!');
        } else {
          // Editor ainda não carregou (raro) — cai pra copiar, redator cola à mão.
          copyToClipboard(snippet);
          feedback(btn, 'Copiado!');
        }
      });
    });

    document.querySelectorAll('[data-open-link-dialog]').forEach(function (btn) {
      if (btn.dataset.insertBound === '1') {
        return;
      }
      btn.dataset.insertBound = '1';
      btn.addEventListener('click', function () {
        var editor = getEditor();
        if (editor) {
          editor.focus();
          editor.execCommand('mceLink');
        }
      });
    });
  }

  function init() {
    var textarea = document.getElementById('content-html-editor');
    if (!textarea || !window.tinymce) {
      return;
    }

    tinymce.init({
      selector: '#content-html-editor',
      height: 480,
      menubar: false,
      statusbar: false,
      branding: false,
      promotion: false,
      language: 'pt_BR',
      // Barra de ferramentas continua no escuro (combina com o resto do
      // app) — só o CORPO do texto (o que o redator lê e edita) virou
      // deliberadamente sem estilo nenhum da identidade visual (achado real
      // 2026-09-14, pedido explícito): fundo branco, texto preto, fonte
      // padrão — pra ler igual um documento normal, sem Orbitron/cyan/HUD
      // no meio do texto do artigo.
      skin: 'oxide-dark',
      content_css: 'default',
      plugins: 'lists link table code',
      toolbar: 'blocks | bold italic underline | bullist numlist | blockquote | link unlink | table | code',
      block_formats: 'Parágrafo=p; Título 2=h2; Título 3=h3; Título 4=h4; Pré-formatado=pre',
      link_default_target: '_blank',
      content_style:
        'body { background: #ffffff; color: #1e1e1e; ' +
        'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; ' +
        'font-size: 16px; line-height: 1.6; } ' +
        'a { color: #0073aa; } a:hover { color: #00a0d2; } ' +
        '::selection { background: #b3d4fc; color: #1e1e1e; }',
    });

    bindLinkButtons();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
