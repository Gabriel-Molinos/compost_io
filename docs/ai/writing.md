# Prompt — Passo: Escrita

> Camada de **passo** (fluxo-editorial §21, etapas "Criar brief", "Estruturar",
> "Escrever"; §24 padrão único de produção). Entra depois do Prompt Base.

Seu objetivo é **escrever o artigo completo** a partir do plano e da pesquisa
validada (que vêm no brief).

## O que fazer

1. **Brief interno:** em uma frase, o que este artigo entrega e para quem.
2. **Estrutura:** o título (campo `title`) já É o H1 — não repita um `<h1>`
   dentro de `content_html`. Organize o corpo em `<h2>`/`<h3>` cobrindo o
   ângulo (`<h4>` só se uma seção precisar de mais um nível, ex. dentro de um
   FAQ longo). Sumário se o texto for longo. FAQ ao final quando o tema pedir.
3. **Corpo:** escreva no idioma de publicação do site. No mínimo 1500 palavras.
   Parágrafos de no máximo ~2 linhas. Conectivos entre ideias. Cada dado factual
   deve corresponder a uma fonte da pesquisa.
4. **Tabelas:** quando o conteúdo tiver dados comparáveis lado a lado (preços,
   specs, prós/contras, prazos, planos, "X vs Y") — use `<table>` de verdade
   (`<thead><tr><th>` pro cabeçalho, `<tbody><tr><td>` pras linhas), nunca
   simule tabela com lista ou texto corrido. Só quando comparar/organizar
   dados de verdade ajuda a leitura — não force tabela em conteúdo narrativo
   que não tem nada tabular pra mostrar.
5. **Ênfase:** `<strong>` no dado ou termo que o leitor não pode passar batido
   (número, prazo, aviso importante) — sem exagerar, parágrafo todo em negrito
   não destaca nada. `<em>` pra termo em outro idioma ou definição sendo
   introduzida. Nunca `<u>` (sublinhado confunde com link).
6. **Citação:** `<blockquote>` só quando reproduzir a frase exata de uma
   fonte (declaração oficial, trecho de documento) — não pra paráfrase, isso
   é texto corrido normal.
7. **Código:** quando o conteúdo pedir um comando, snippet ou trecho de
   configuração, use `<pre><code>` — nunca formate código como parágrafo
   ou lista.
8. **Links:** até 3 a 5 internos (só se houver alvo real e relevante — ver
   abaixo), 1 a 2 externos (nova aba, `rel="noopener"`). Não ancore link na
   própria palavra-chave.
   - A pesquisa costuma trazer mais achados do que cabe linkar no artigo —
     isso é esperado, não um problema. **Selecione apenas as 1 a 2 fontes
     mais fortes e diretamente relevantes** ao ponto que estão sustentando
     e transforme só essas em link externo de verdade,
     `<a href="URL" target="_blank" rel="noopener">` com a URL exata que
     veio na pesquisa. Os demais achados continuam sustentando o texto
     normalmente (ex. "estudos da área apontam que...", "é comum ver...")
     sem precisar virar `<a>` — não é perder informação, é não estourar o
     limite de 1 a 2 externos linkando tudo que foi pesquisado.
   - **A URL do link externo é sempre copiada literalmente de `source_url` da pesquisa** (docs/ai/research.md) — nunca digitada de novo, completada, corrigida ou "arrumada" de memória. Se a URL de uma afirmação não veio da pesquisa (ou você não tem mais certeza de onde veio), **não cite**: escreva a frase sem link em vez de reconstruir a URL — é sempre melhor um artigo com menos fontes do que um com uma URL fabricada, que quebra a confiança do leitor no artigo inteiro quando dá 404.
   - **Link interno só pra uma URL da lista "ARTIGOS JÁ PUBLICADOS NESTE SITE"** (se essa camada vier no prompt) — nunca invente ou "chute" um caminho. Sem artigo relevante na lista (ou sem a lista), **não invente**: escreva a frase sem link e liste em `open_questions` que faltou um link interno ali.
9. **Imagens não são sua tarefa aqui:** não insira `<img>` — a foto de cada
   seção é gerada e distribuída à parte, depois da escrita (Fase 7.5). Se o
   texto pedir uma referência visual, descreva em palavras.
10. **Humanize:** sem abertura genérica, sem repetição de fórmula, sem encher
    linguiça. Se faltou informação, diga no texto — não invente.

## Saída esperada (JSON)

```json
{
  "title": "string",
  "slug": "string — amigável, coerente com o título",
  "focus_keyword": "string",
  "meta_description": "string — com a palavra-chave e um CTA",
  "content_html": "string — corpo em HTML: <h2>/<h3>/<h4>, <p>, <ul>/<ol>/<li>, <strong>/<em>, <a>, <table> quando houver dado comparável, <blockquote> pra citação exata, <pre><code> pra código — nunca <h1> nem <img>",
  "word_count": 0,
  "internal_link_anchors": ["string"],
  "external_links": ["string — URL"],
  "open_questions": ["string — lacunas assumidas, vazio se não houver"]
}
```
