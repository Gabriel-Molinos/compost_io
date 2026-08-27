# SEO On-Page & Checklist Editorial `[NOVO — não fazia parte do README original, adicionado a pedido do responsável do projeto]`

Checklist de SEO on-page que todo artigo produzido pela plataforma precisa atender antes de `APPROVED` (ver [Revisão humana — seção 27](fluxo-editorial.md#27-revisão-humana-estados-do-artigo)). É verificado em dois momentos: pela IA na etapa de **SEO** e **Compliance** do [fluxo de produção (seção 21)](fluxo-editorial.md#21-como-a-ia-deve-trabalhar), e pelo Redator-Chefe na revisão humana.

Como este documento se relaciona com os demais:

- **Regras SEO por site** (config específica — [seção 15](fluxo-editorial.md#15-estrutura-editorial-de-cada-site)): este checklist é a **linha de base comum a todos os sites**; as regras específicas de cada site somam por cima, não substituem.
- **[Compliance de conteúdo](compliance.md)** (qualidade / AdSense): somam, não substituem. Dois itens aparecem nos dois documentos — extensão mínima e distância entre links e anúncios.
- **Prompt futuro `docs/ai/seo.md`** ([seção 23](fluxo-editorial.md#23-estrutura-futura-de-prompts)): operacionaliza este checklist para a IA. O `docs/ai/` é o *prompt*; este documento é a *política editorial* que o prompt implementa.

## Otimização on-page

- [ ] Palavra-chave principal no **título / H1** da página.
- [ ] Palavra-chave destacada em **negrito ou itálico** ao menos uma vez.
- [ ] **Meta descrição** otimizada com a palavra-chave e um call-to-action.
- [ ] **Slug** amigável e otimizado com a palavra-chave (reforça o elemento `slug` de [compliance.md](compliance.md#elementos-obrigatórios-de-um-post-wordpress)).
- [ ] Texto com **no mínimo 1500 palavras** (regra fixa da plataforma).
- [ ] **Hierarquia de títulos** adequada — H1 → H2 → H3 — com palavras-chave secundárias nos subtítulos.
- [ ] **Sumário** para textos longos.

## Links e estrutura

- [ ] **Links internos: 3 a 5** por texto.
- [ ] **Links externos: 1 a 2** por texto, sempre abrindo em nova aba (`target="_blank"` + `rel="noopener"`).
- [ ] **Vincular o texto atual ao último artigo publicado** do site — evitar conteúdo órfão.
- [ ] **Não** inserir link ancorado na própria palavra-chave.
- [ ] **Após a publicação:** testar todos os links e conferir se o redirecionamento está correto (encaixa na verificação de publicação da [seção 31](fluxo-editorial.md#31-integração-wordpress)).

## Imagens

- [ ] **Imagem destacada** otimizada, com a palavra-chave no nome do arquivo / alt.
- [ ] **Alt text** com a palavra-chave nas imagens em que for pertinente (não em todas).
- [ ] **Todas as imagens em formato WebP.**
- [ ] Ao menos **uma imagem personalizada no corpo** do texto com a palavra-chave (pode ser a mesma da imagem destacada).
- [ ] **Cadência: 1 imagem a cada 500 palavras** — um texto de 1500 palavras tem ~3 imagens.

## Qualidade do conteúdo

- [ ] Quebrar blocos de texto gerados por IA para melhor legibilidade; em texto original, parágrafos de **no máximo 2 linhas (entre 20 e 25 palavras)**.
- [ ] **Humanizar** o texto — remover padrões robóticos de escrita (reforça "linguagem clara" dos [Interesses, seção 18](fluxo-editorial.md#18-interesses) e "não usar linguagem sensacionalista" da [compliance](compliance.md#regras-de-compliance-de-conteúdo-qualidade--adsense)).
- [ ] **Verificar dados, datas e informações** — garantir veracidade dos fatos e atualização das informações.
- [ ] **Revisar possível plágio** / garantir conteúdo original (reforça compliance "conteúdo original e não simplesmente reescrever outros sites").
- [ ] Usar **palavras de transição** (conectivos).

## Categorização e tags

- [ ] **Não criar categorias novas** sem necessidade — as categorias de cada site são fixas e precisam ser mantidas atualizadas (ver [Categorias, seção 15](fluxo-editorial.md#15-estrutura-editorial-de-cada-site)).
- [ ] Para agrupar temas novos, usar **tags** em vez de novas categorias.
- [ ] **Densidade de palavra-chave** adequada — evitar keyword stuffing (reforça os [Não-interesses, seção 19](fluxo-editorial.md#19-não-interesses) e a regra de compliance "não repetir palavras-chave artificialmente").
- [ ] **Canibalização:** se a palavra-chave já foi usada em um artigo anterior do site, **avisar e deixar o artigo em rascunho** (não publicar), para evitar conteúdo duplicado. Liga-se ao passo "Verificar duplicação" da [seção 21](fluxo-editorial.md#21-como-a-ia-deve-trabalhar) e à [Regra de transparência (seção 59)](../ai/regras-claude-code.md#59-regra-de-transparência).
- [ ] **Manter distância entre os links do texto e os blocos de anúncio do Google** (ver [compliance / AdSense](compliance.md#regras-de-compliance-de-conteúdo-qualidade--adsense)).

> As tabelas **`tags`** e **`article_tags`** já existem no banco (ver [schema — seção 87](../technical/schema.md#87-tabelas--estado-atual-migration-0001)). Falta ainda incluir `tags` na lista de responsabilidades da [WordPress REST API (seção 37)](../technical/integracoes.md#37-wordpress-rest-api) — formalizar quando a integração WordPress for detalhada (Fase 7 do [roadmap](../product/roadmap.md#71-roadmap-completo-fases-19)).

## Elementos de enriquecimento

Usar quando fizer sentido para o tema:

- **Tabelas.**
- **Campo de dúvidas frequentes (FAQ).**
- **Infográficos.**
- **Artes personalizadas** com a palavra-chave.

## Regras firmes — referência rápida

Valores obrigatórios, para consulta na revisão:

| Item | Valor |
|---|---|
| Extensão do texto | ≥ 1500 palavras |
| Links internos | 3 a 5 |
| Links externos | 1 a 2 (nova aba) |
| Imagens | 1 a cada 500 palavras (todas em WebP) |
| Parágrafo (texto original) | ≤ 2 linhas / 20–25 palavras |
| Palavra-chave no H1 | obrigatório |
| Meta descrição | obrigatória, com palavra-chave + CTA |

## Reforço de compliance / AdSense

Estes itens deste checklist também constam da [compliance.md](compliance.md) e valem como regra de compliance:

- **Extensão mínima de 1500 palavras** por artigo.
- **Distância entre os links do texto e os blocos de anúncio do Google.**
- **Evitar criar novas categorias** — elas precisam ficar atualizadas.

## Ver também

- [Compliance de conteúdo](compliance.md) — elementos obrigatórios de um post e regras de qualidade/AdSense
- [Fluxo editorial](fluxo-editorial.md) — [Regras SEO por site (seção 15)](fluxo-editorial.md#15-estrutura-editorial-de-cada-site), [fluxo de produção da IA (seção 21)](fluxo-editorial.md#21-como-a-ia-deve-trabalhar), [revisão humana (seção 27)](fluxo-editorial.md#27-revisão-humana-estados-do-artigo)
- [Glossário](../glossario.md) — palavra-chave, meta descrição, slug, canibalização, conteúdo órfão, tag
- [Referências oficiais](../referencias.md#82-referências-oficiais-links) — Google Search Central
