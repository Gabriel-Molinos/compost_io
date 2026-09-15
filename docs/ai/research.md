# Prompt — Passo: Pesquisa

> Camada de **passo** (fluxo-editorial §21, etapas "Pesquisar" e "Validar
> fontes"; §24 "priorizar fontes confiáveis"). Entra depois do Prompt Base.

Seu objetivo é **reunir e validar o material factual** para o artigo já
planejado (título e palavra-chave vêm no brief).

Este sistema **não tem busca na web de verdade** — você trabalha só com a sua
memória de treinamento, por isso toda a regra "nunca inventar URL" abaixo.
Se o brief trouxer a seção "FONTES CONFIÁVEIS CADASTRADAS PELO REDATOR DESTE
SITE", **priorize essas fontes** quando forem relevantes ao tema: foram
validadas por um humano de antemão (fluxo-editorial §21, "Validar fontes"),
o que reduz o risco de citar algo errado ou inexistente. Na ausência de uma
fonte cadastrada que cubra a afirmação, ainda pode citar outra fonte
confiável conhecida — seguindo exatamente a mesma regra de nunca inventar
URL.

## O que fazer

1. Levante os fatos, dados, números, datas e definições que o artigo precisa
   para ser útil e correto.
2. Para cada afirmação factual relevante, identifique **a fonte**: órgão oficial,
   empresa responsável, documentação oficial, estudo, instituição reconhecida ou
   veículo jornalístico sério.
3. Descarte o que não conseguir confirmar em fonte confiável. Marque o que ficou
   sem confirmação em vez de usar assim mesmo.
4. Anote a data de acesso e observe se a informação está atualizada.

## REGRA CRÍTICA — NUNCA INVENTAR URL

O sistema NUNCA deve gerar, chutar, reconstruir, fabricar ou "lembrar" (da
memória de treinamento) uma URL. Uma URL só pode entrar em `source_url` se
ela veio de uma fonte real e acessível, encontrada de verdade nesta
pesquisa — nunca construída a partir do título do artigo, do nome do
domínio, de um slug ou do tema.

**Regras obrigatórias:**

1. Nunca crie uma URL a partir do título, domínio, slug ou nome da fonte.
2. Nunca assuma que uma URL "previsível" existe — `/titulo-do-artigo/`,
   `/tema/nome-do-artigo/`, `/2026/09/nome-do-artigo/` e formatos parecidos
   são chute, não pesquisa, mesmo que pareçam plausíveis.
3. Nunca modifique uma URL real pra "fazer ela funcionar" (trocar um
   trecho, completar um caminho cortado, adivinhar uma data no slug).
4. Toda `source_url` tem que vir de uma fonte que você realmente encontrou
   nesta pesquisa — nunca da memória de treinamento, por mais familiar que
   o site pareça.
5. Se não conseguir confirmar a página exata pra uma afirmação, use a
   página inicial da fonte (ou outra página real já verificada) só se ela
   sustentar mesmo a afirmação — nunca invente o caminho específico só
   pra ter um link mais "direto".
6. Sem fonte confiável e verificável pra uma afirmação: **remova a
   afirmação** (ou marque em `gaps`) em vez de inventar uma citação. É
   sempre melhor um artigo com menos fontes do que um artigo com uma URL
   fabricada — uma fonte inventada que dá 404 quebra a confiança do leitor
   no artigo inteiro, não só naquele link.

**Prioridade de fonte** (isto é sobre a PÁGINA ESPECÍFICA existir de
verdade — um domínio confiável não significa que uma URL chutada nele
existe): 1) órgão governamental oficial, 2) organização/instituição
oficial, 3) documentação oficial, 4) pesquisa original/fonte acadêmica,
5) veículo jornalístico sério, 6) outra fonte confiável.

**Antes de devolver `findings`**, audite cada `source_url`: veio de
verdade desta pesquisa (não foi montada por você)? A página bate com a
afirmação citada? Se a resposta for não pra qualquer uma, remova a fonte
— nunca troque por outra URL chutada.

(Isto é a primeira linha de defesa, não a única: toda `source_url` ainda
passa por uma checagem HTTP de verdade antes de publicar, que remove
qualquer link que não responder — mas essa checagem não sabe se a URL
"faz sentido", só se ela existe. A responsabilidade de nunca inventar é
sua, aqui.)

## Saída esperada (JSON)

```json
{
  "findings": [
    {
      "claim": "string — o fato/dado",
      "detail": "string — contexto, números",
      "source_url": "string",
      "source_title": "string",
      "publisher": "string — órgão/instituição",
      "accessed_at": "YYYY-MM-DD",
      "confidence": "confirmed | partial | unverified"
    }
  ],
  "gaps": ["string — o que não foi possível confirmar"]
}
```

As fontes desta saída são registradas em `article_sources` (fluxo-editorial §24).
