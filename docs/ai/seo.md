# Prompt — Passo: SEO

> Camada de **passo** (fluxo-editorial §21, etapa "SEO"). Operacionaliza o
> checklist de política editorial `docs/editorial/seo.md` — este arquivo é o
> *prompt*; aquele é a *política*. Entra depois do Prompt Base.

Seu objetivo é **auditar o artigo escrito contra o checklist de SEO on-page** e
devolver as correções necessárias, sem reescrever o artigo do zero.

## Verifique (regras firmes)

| Item | Valor |
|---|---|
| Extensão | ≥ 1500 palavras |
| Palavra-chave no H1 | obrigatório |
| Palavra-chave destacada no corpo | ao menos 1× |
| Meta descrição | com palavra-chave + CTA |
| Slug | amigável, com a palavra-chave |
| Hierarquia H1 → H2 → H3 | correta, palavras-chave secundárias nos H2/H3 |
| Links internos | 3 a 5 **quando a seção "ARTIGOS JÁ PUBLICADOS NESTE SITE" (vem no prompt) listar pelo menos 3 artigos relevantes ao tema e o rascunho não usou nenhum/poucos deles**. Se a lista vier vazia ou sem pelo menos 3 alvos relevantes ao tema, **não é `block`** — no máximo `warn`, o site ainda não tem estoque suficiente pra exigir isso. |
| Links externos | 1 a 2, nova aba |
| Link na própria palavra-chave | proibido |
| Densidade da palavra-chave | sem keyword stuffing |
| Canibalização | se a keyword já existe no site → sinalizar, deixar em rascunho |
| Parágrafos | curtos; conectivos presentes |
| Imagens (cadência) | 1 a cada ~500 palavras (a geração é outro passo) |

## Saída esperada (JSON)

Todo texto livre abaixo (`item`, `fix`) é pro Redator-Chefe ler — **sempre em
português do Brasil**, mesmo que o artigo seja publicado em outro idioma.

```json
{
  "passes": true,
  "issues": [
    { "item": "string", "severity": "block | warn", "fix": "string — correção objetiva" }
  ],
  "revised_meta_description": "string — vazio se já estava ok",
  "revised_slug": "string — vazio se já estava ok",
  "cannibalization": "none | possible | high"
}
```

`passes` só é `true` se não houver nenhum issue de severidade `block`.
