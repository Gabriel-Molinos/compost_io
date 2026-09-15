# Prompt — Passo: Planejamento (escolha de tema)

> Camada de **passo** (fluxo-editorial §21, etapas "Escolher tema" e "Verificar
> duplicação"). Entra depois do Prompt Base e antes da identidade do site.

Seu objetivo neste passo é **escolher um tema de artigo** que atenda à meta do
período e ainda não tenha sido coberto pelo site.

## O que fazer

1. Leia a meta do período e a distribuição por categoria. Priorize as categorias
   que ainda estão longe do alvo.
2. Considere os interesses e não-interesses do site e as diretrizes da categoria.
3. Proponha **um** tema: título de trabalho, palavra-chave principal, categoria,
   ângulo (o que este artigo faz de diferente) e por que ele serve à meta.
   A categoria tem que ser **exatamente um nome** da lista "CATEGORIAS
   CADASTRADAS DO SITE" (vem no prompt) — nunca invente um nome novo, mesmo
   que pareça óbvio. Se nenhum nome da lista encaixar bem no tema, deixe
   `category` vazio em vez de forçar um nome que não existe no site.
4. **Verifique duplicação / canibalização:** compare a palavra-chave e o ângulo
   com a seção "POSTS JÁ PUBLICADOS NO WORDPRESS DESTE SITE" (quando ela vier
   no prompt — é a lista real do site, não um palpite). Se houver sobreposição
   de tema/palavra-chave com algum post dessa lista, sinalize e proponha um
   recorte diferente ou marque para revisão humana. Sem essa seção (site ainda
   sem WordPress conectado), siga só com o que souber pelas categorias/metas.

## Saída esperada (JSON)

```json
{
  "title": "string — título de trabalho",
  "focus_keyword": "string",
  "category": "string — nome exato de uma categoria da lista 'CATEGORIAS CADASTRADAS DO SITE', ou vazio se nenhuma encaixar",
  "angle": "string — o recorte específico deste artigo",
  "rationale": "string — como atende à meta",
  "cannibalization_risk": "none | possible | high",
  "cannibalization_note": "string — vazio se risk = none"
}
```

Não escreva o artigo neste passo. Apenas o plano.
