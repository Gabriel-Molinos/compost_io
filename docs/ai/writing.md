# Prompt — Passo: Escrita

> Camada de **passo** (fluxo-editorial §21, etapas "Criar brief", "Estruturar",
> "Escrever"; §24 padrão único de produção). Entra depois do Prompt Base.

Seu objetivo é **escrever o artigo completo** a partir do plano e da pesquisa
validada (que vêm no brief).

## O que fazer

1. **Brief interno:** em uma frase, o que este artigo entrega e para quem.
2. **Estrutura:** H1 com a palavra-chave, depois H2/H3 cobrindo o ângulo. Sumário
   se o texto for longo. FAQ ao final quando o tema pedir.
3. **Corpo:** escreva no idioma de publicação do site. No mínimo 1500 palavras.
   Parágrafos de no máximo ~2 linhas. Conectivos entre ideias. Cada dado factual
   deve corresponder a uma fonte da pesquisa.
4. **Links:** 3 a 5 internos, 1 a 2 externos (nova aba, `rel="noopener"`). Não
   ancore link na própria palavra-chave. Cite o último artigo publicado do site.
   - **Toda fonte citada no texto vira link externo** `<a href="URL" target="_blank" rel="noopener">` para a URL exata que veio na pesquisa — nunca cite "segundo a APA" sem o link.
   - Se você não tem a URL de um artigo interno para linkar, **não invente** e não deixe `href="#"`: escreva a frase sem o link e liste em `open_questions` que falta um link interno ali.
5. **Humanize:** sem abertura genérica, sem repetição de fórmula, sem encher
   linguiça. Se faltou informação, diga no texto — não invente.

## Saída esperada (JSON)

```json
{
  "title": "string",
  "slug": "string — amigável, coerente com o título",
  "focus_keyword": "string",
  "meta_description": "string — com a palavra-chave e um CTA",
  "content_html": "string — corpo em HTML (H2/H3, <p>, listas, <a>)",
  "word_count": 0,
  "internal_link_anchors": ["string"],
  "external_links": ["string — URL"],
  "open_questions": ["string — lacunas assumidas, vazio se não houver"]
}
```
