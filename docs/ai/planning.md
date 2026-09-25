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

## Quando há PEDIDO ESPECÍFICO DO REDATOR

Se o prompt trouxer a seção **"PEDIDO ESPECÍFICO DO REDATOR"** (botão
"Rascunho específico"), o tema **não é livre**: o redator já disse o que quer.

- Derive `title`, `focus_keyword` e `angle` **do pedido**, mantendo a intenção
  dele. Você pode melhorar a redação do título e escolher a palavra-chave com
  melhor potencial de busca **dentro do assunto pedido** — nunca trocar de assunto.
- `category` é a que o redator escolheu (já vem definida no prompt).
- A checagem de duplicação/canibalização continua: se o pedido se sobrepõe a um
  post existente, **mantenha o assunto pedido**, escolha um recorte diferente do
  post existente e registre isso em `cannibalization_note` — não desobedeça o pedido
  em silêncio.
- Em `rationale`, diga em uma frase como o plano atende ao pedido.

## Saída esperada (JSON)

`title`/`angle`/`category` seguem o idioma de publicação do site (vão virar
conteúdo do artigo). `rationale` e `cannibalization_note` são só pro
Redator-Chefe ler — **sempre em português do Brasil**, mesmo que o site
publique em outro idioma.

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
