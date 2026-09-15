# Prompt — Passo: Imagem (brief visual)

> Camada de **passo** (fluxo-editorial §21, etapa "Imagem"; §25 "Brief visual").
> Entra depois do Prompt Base. **Este passo não gera imagem** — ele produz o
> *brief* (a descrição) que o serviço Nano Banana usa depois (fatia 5.2/5.3).
> A política de cadência e alt text é `docs/editorial/seo.md#imagens`.

Seu objetivo é **transformar o artigo já produzido em um conjunto de briefs de
imagem** coerentes com a identidade visual do site — uma imagem destacada e as
imagens de corpo necessárias.

## O que fazer

1. **Leia o artigo produzido** (vem na camada "ARTIGO PRODUZIDO") e a identidade
   do site (nicho, público, tom, identidade editorial).
2. **Imagem destacada (`FEATURED`):** uma só. Deve representar o tema central do
   artigo, funcionar como thumbnail e respeitar o tom do site.
3. **Imagens de corpo (`BODY`):** cadência de **1 a cada ~500 palavras**
   (um texto de 1500 palavras tem ~2 imagens de corpo, além da destacada).
   Cada uma ilustra uma seção específica — indique perto de qual trecho entra.
4. **`prompt` (descrição para o gerador):** escreva em **inglês**, concreto e
   visual (cena, enquadramento, luz, estilo, paleta). **Sem texto embutido na
   imagem** — nenhuma letra, palavra, número ou legenda visível na cena.
   Modelos de imagem tendem a "alucinar" texto garranchado mesmo sem pedir,
   principalmente quando a composição inclui objetos com texto (placa, tela
   com conteúdo legível, livro/página aberta, cartaz, embalagem com rótulo) —
   **evite esses objetos na composição**, não só evite pedir texto neles.
   Sem logotipos de terceiros, sem rostos de pessoas reais/públicas. Nada que
   gere problema de compliance/AdSense (`docs/editorial/compliance.md`).
5. **`alt_text`:** descreve a imagem para leitores de tela, no idioma de
   publicação do site. Inclua a palavra-chave **só quando for pertinente** —
   não em todas (`seo.md#imagens`).
6. **`aspect_ratio`:** `16:9` para a destacada por padrão; para as de corpo use
   o que melhor servir (`16:9`, `4:3`, `1:1`).
7. **Não invente dados do artigo.** A imagem ilustra, não afirma fatos novos.

## Saída esperada (JSON)

```json
{
  "style_notes": "string — direção de arte comum a todas as imagens (paleta, estilo, mood)",
  "images": [
    {
      "role": "FEATURED | BODY",
      "prompt": "string — descrição visual em inglês, pronta para o gerador",
      "alt_text": "string — no idioma do site",
      "aspect_ratio": "1:1 | 3:2 | 4:3 | 16:9 | 9:16",
      "placement": "string — vazio se FEATURED; se BODY, o H2/trecho perto do qual entra"
    }
  ],
  "notes": ["string — avisos ou suposições pro Redator-Chefe, sempre em português do Brasil, vazio se não houver"]
}
```

Regras firmes: exatamente **uma** imagem com `role: "FEATURED"`; o número de
`BODY` acompanha a contagem de palavras (1 a cada ~500).
