# Prompt — Passo: Compliance

> Camada de **passo** (fluxo-editorial §21, etapa "Compliance"). Operacionaliza
> `docs/editorial/compliance.md` (elementos obrigatórios de um post + regras de
> qualidade/AdSense). Entra depois do Prompt Base.

Seu objetivo é **verificar se o artigo pode ser aprovado**, do ponto de vista de
compliance de conteúdo. Não reescreva o artigo — aponte o que impede a aprovação.

## Elementos obrigatórios do post

- Título claro, específico, sem clickbait
- Corpo em HTML com headings, parágrafos curtos, listas onde couber
- Categoria: exatamente uma das categorias já cadastradas do site (não criar nova)
- Slug amigável e coerente com o título
- Links internos (3 a 5) e externos (1 a 2): mesma regra do passo SEO —
  só bloqueie por "poucos links internos" se a seção "ARTIGOS JÁ
  PUBLICADOS NESTE SITE" (vem no prompt) listar pelo menos 3 artigos
  relevantes ao tema e o artigo não tiver usado nenhum/poucos. Lista
  vazia ou sem alvo relevante suficiente: não é `blocking`, no máximo
  `warnings`.
- (Autor e imagem destacada são definidos no agendamento — fora deste passo)

## Regras de qualidade / AdSense

O artigo **deve**: trazer informação útil e original; usar fontes confiáveis para
dados factuais; deixar claras afirmações, limitações e condições; respeitar
direitos autorais; ter ≥ 1500 palavras; entregar o que o título promete.

O artigo **não pode**: reescrever outros sites; conter informação enganosa ou
falsa; fazer promessa absoluta ou garantia sem fundamento; usar linguagem
sensacionalista; existir só para manipular busca; repetir palavra-chave
artificialmente; posicionar links colados a blocos de anúncio.

## Saída esperada (JSON)

```json
{
  "approved": true,
  "blocking": [
    { "rule": "string", "evidence": "string — trecho ou fato do artigo", "fix": "string" }
  ],
  "warnings": [
    { "rule": "string", "note": "string" }
  ]
}
```

`approved` só é `true` se `blocking` estiver vazio.
