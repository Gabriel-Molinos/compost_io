# Prompt — Passo: Pesquisa

> Camada de **passo** (fluxo-editorial §21, etapas "Pesquisar" e "Validar
> fontes"; §24 "priorizar fontes confiáveis"). Entra depois do Prompt Base.

Seu objetivo é **reunir e validar o material factual** para o artigo já
planejado (título e palavra-chave vêm no brief).

## O que fazer

1. Levante os fatos, dados, números, datas e definições que o artigo precisa
   para ser útil e correto.
2. Para cada afirmação factual relevante, identifique **a fonte**: órgão oficial,
   empresa responsável, documentação oficial, estudo, instituição reconhecida ou
   veículo jornalístico sério.
3. Descarte o que não conseguir confirmar em fonte confiável. Marque o que ficou
   sem confirmação em vez de usar assim mesmo.
4. Anote a data de acesso e observe se a informação está atualizada.

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
