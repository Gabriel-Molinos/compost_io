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
4. **Tabelas:** quando o conteúdo tiver dados comparáveis lado a lado (preços,
   specs, prós/contras, prazos, planos, "X vs Y") — use `<table>` de verdade
   (`<thead><tr><th>` pro cabeçalho, `<tbody><tr><td>` pras linhas), nunca
   simule tabela com lista ou texto corrido. Só quando comparar/organizar
   dados de verdade ajuda a leitura — não force tabela em conteúdo narrativo
   que não tem nada tabular pra mostrar.
5. **Links:** até 3 a 5 internos (só se houver alvo real e relevante — ver
   abaixo), 1 a 2 externos (nova aba, `rel="noopener"`). Não ancore link na
   própria palavra-chave.
   - **Toda fonte citada no texto vira link externo** `<a href="URL" target="_blank" rel="noopener">` para a URL exata que veio na pesquisa — nunca cite "segundo a APA" sem o link.
   - **Link interno só pra uma URL da lista "ARTIGOS JÁ PUBLICADOS NESTE SITE"** (se essa camada vier no prompt) — nunca invente ou "chute" um caminho. Sem artigo relevante na lista (ou sem a lista), **não invente**: escreva a frase sem link e liste em `open_questions` que faltou um link interno ali.
6. **Humanize:** sem abertura genérica, sem repetição de fórmula, sem encher
   linguiça. Se faltou informação, diga no texto — não invente.

## Saída esperada (JSON)

```json
{
  "title": "string",
  "slug": "string — amigável, coerente com o título",
  "focus_keyword": "string",
  "meta_description": "string — com a palavra-chave e um CTA",
  "content_html": "string — corpo em HTML (H2/H3, <p>, listas, <a>, <table> quando houver dado comparável)",
  "word_count": 0,
  "internal_link_anchors": ["string"],
  "external_links": ["string — URL"],
  "open_questions": ["string — lacunas assumidas, vazio se não houver"]
}
```
