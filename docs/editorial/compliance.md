# Compliance de Conteúdo `[NOVO — não fazia parte do README original, adicionado a pedido do responsável do projeto]`

Este documento reúne duas exigências que todo artigo produzido pela plataforma precisa atender antes de `APPROVED` (ver [Revisão humana — seção 27](fluxo-editorial.md#27-revisão-humana-estados-do-artigo)): os elementos estruturais de um post WordPress e as regras de compliance de conteúdo (qualidade/AdSense). Complementa o conceito de **Compliance** já citado na [Estrutura editorial de cada site (seção 15)](fluxo-editorial.md#15-estrutura-editorial-de-cada-site) e no [Glossário](../glossario.md).

O checklist de otimização para busca fica em [SEO On-Page & Checklist Editorial](seo.md) — dois itens são comuns aos dois documentos (extensão mínima do texto e distância entre links e blocos de anúncio).

## Elementos obrigatórios de um post WordPress

Baseado nos campos que a [WordPress REST API — Posts](../technical/integracoes.md#37-wordpress-rest-api) espera/expõe para criar e publicar um artigo (ver também [Integração WordPress — seção 31](fluxo-editorial.md#31-integração-wordpress)). Antes de um artigo ser considerado pronto para publicação, ele precisa ter:

- **Título** (`title`) — claro, específico, sem clickbait (ver regras de compliance abaixo).
- **Conteúdo** (`content`) — corpo do artigo em HTML, estruturado com headings (H2/H3), parágrafos curtos, listas onde fizer sentido.
- **Resumo/excerpt** (`excerpt`) — quando o tema do site usar.
- **Categoria** (`categories`) — pelo menos uma, dentre as categorias já cadastradas do site (ver [Categorias — seção 15](fluxo-editorial.md#15-estrutura-editorial-de-cada-site)). Categorias são fixas por site e precisam ser mantidas atualizadas — não criar categoria nova para um tema pontual; usar **tags** (ver [SEO — Categorização e tags](seo.md#categorização-e-tags)).
- **Slug** (`slug`) — amigável, coerente com o título.
- **Autor** (`author`) — definido no agendamento (ver [Agendamento — seção 30](fluxo-editorial.md#30-agendamento)).
- **Imagem destacada** (`featured_media`) — pelo menos uma das opções geradas pelo Nano Banana e escolhida pelo redator (ver [Imagens — seção 25](fluxo-editorial.md#25-imagens)).
- **Status** (`status`) — controlado pela [máquina de estados do artigo](../technical/requisitos.md#65-máquina-de-estados-do-artigo), só vira `publish` no WordPress quando o artigo estiver `APPROVED`/`SCHEDULED` na plataforma.
- **Data de publicação/agendamento** (`date`) — definida no agendamento.

## Regras de compliance de conteúdo (qualidade / AdSense)

Regras que todo artigo precisa seguir, com base nas políticas de conteúdo do Google AdSense. O post deve:

- trazer informação útil e relevante para o usuário;
- ter conteúdo original e não simplesmente reescrever outros sites;
- evitar informações enganosas ou falsas;
- deixar claras afirmações, limitações e condições;
- usar fontes confiáveis quando apresentar dados, estatísticas ou informações factuais;
- respeitar direitos autorais;
- não fazer promessas absolutas ou garantias sem fundamento;
- não usar linguagem sensacionalista ou enganosa;
- não criar conteúdo apenas para manipular mecanismos de busca;
- não repetir palavras-chave artificialmente;
- entregar aquilo que o título e a descrição prometem;
- ter **extensão mínima de 1500 palavras** (ver [SEO — Otimização on-page](seo.md#otimização-on-page));
- **manter distância entre os links do texto e os blocos de anúncio do Google** — não posicionar links colados a anúncios.

Essas regras se somam (não substituem) aos [Interesses e Não-interesses (seções 18–19)](fluxo-editorial.md#18-interesses) já configuráveis por site, e ao processo de [Compliance dentro do fluxo de produção da IA (seção 21)](fluxo-editorial.md#21-como-a-ia-deve-trabalhar).

**Documentação oficial (Google AdSense):**
- https://support.google.com/adsense/answer/10502938?hl=pt-BR
- https://support.google.com/adsense/answer/10008391?hl=pt-BR
- https://support.google.com/adsense/answer/48182?hl=pt-BR
- https://support.google.com/adsense/answer/23921?hl=pt-BR

## Ver também

- [SEO On-Page & Checklist Editorial](seo.md) — otimização para busca, checklist complementar a este
- [Fluxo editorial](fluxo-editorial.md) — onde este checklist entra no processo de revisão
- [Requisitos — Contrato da API](../technical/requisitos.md#67-contrato-da-api-endpoints)
- [Integrações — WordPress REST API](../technical/integracoes.md#37-wordpress-rest-api)
