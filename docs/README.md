# Índice da documentação

Mapa de todos os documentos do Editorial Dashboard (COMPOST), por área. Ponto de entrada geral: [README.md](../README.md) da raiz.

## Produto

- [Visão geral](product/visao-geral.md) — o que é, objetivo, princípios, conceito central
- [Roadmap e escopo](product/roadmap.md) — fases 1–9, critério de sucesso
- [Identidade visual](product/identidade-visual.md) — nome, mockups, paleta de cores
- [Onboarding de site](product/onboarding-site.md)
- [Onboarding de usuário](product/onboarding-usuario.md)

## Editorial

- [Fluxo editorial](editorial/fluxo-editorial.md) — usuários/sites, produção com IA, revisão/publicação/relatórios
- [Compliance de conteúdo](editorial/compliance.md) — regras de qualidade/AdSense, elementos de um post
- [SEO On-Page & Checklist Editorial](editorial/seo.md) — checklist de otimização para busca (título, meta, links, imagens, canibalização)

## Técnico

- [Arquitetura](technical/arquitetura.md) — stack, padrão MVC, diretórios
- [Integrações](technical/integracoes.md) — Gemini, Nano Banana, WordPress, MySQL, Beekeeper, NotebookLM
- [Segurança](technical/seguranca.md) — política de credenciais, checklist de deploy
- [Credenciais privadas](technical/credenciais-privadas.md) — **local, fora do Git**
- [Requisitos e modelagem](technical/requisitos.md) — RF/RB, permissões, autenticação, estados
- [Schema de banco de dados](technical/schema.md)
- [Convenções de código](technical/padroes-de-codigo.md)
- [UI/UX & Frontend Standards](technical/ui-ux-frontend.md) — padrão de interface, UX e acessibilidade (WCAG 2.2 AA)
- [Testes e observabilidade](technical/testes-e-observabilidade.md)
- [Setup e operações](technical/setup-e-operacoes.md) — getting started completo, CI
- [Diagramas de sequência](technical/diagrama-sequencia.md)

## Governança de IA

- [Regras para o Claude Code](ai/regras-claude-code.md)

## Decisões e referências

- [ADRs](decisions/README.md)
- [Glossário](glossario.md)
- [Referências oficiais](referencias.md)
- [CHANGELOG](../CHANGELOG.md)

## Como os documentos se relacionam

```mermaid
flowchart TB
    README[README raiz] --> Produto
    README --> Editorial
    README --> Tecnico[Técnico]
    README --> IA[Governança de IA]
    README --> Decisoes[Decisões e referências]

    subgraph Produto
        VisaoGeral[Visão geral]
        Roadmap
        Identidade[Identidade visual]
        OnbSite[Onboarding de site]
        OnbUser[Onboarding de usuário]
    end

    subgraph Editorial
        Fluxo[Fluxo editorial]
        Compliance
        SEO[SEO On-Page]
    end

    subgraph Tecnico
        Arquitetura
        Integracoes[Integrações]
        Seguranca[Segurança]
        Requisitos
        Schema
        Padroes[Convenções de código]
        UIUX[UI/UX & Frontend]
        Testes[Testes e observabilidade]
        Setup[Setup e operações]
        Diagramas[Diagramas de sequência]
    end

    subgraph IA
        Regras[Regras Claude Code]
    end

    subgraph Decisoes
        ADRs
        Glossario[Glossário]
        Referencias
        Changelog[CHANGELOG]
    end

    Fluxo --> Arquitetura
    Fluxo --> Requisitos
    Requisitos --> Schema
    Requisitos --> Arquitetura
    Arquitetura --> ADRs
    Integracoes --> ADRs
    Seguranca --> Regras
    Padroes --> Arquitetura
    UIUX --> Arquitetura
    UIUX --> Identidade
    Regras --> UIUX
    Setup --> Seguranca
    Testes --> Fluxo
    Diagramas --> Fluxo
    OnbSite --> Fluxo
    OnbUser --> Requisitos
    Compliance --> Fluxo
    SEO --> Fluxo
    Compliance --> SEO
```

> Renderiza como diagrama no GitHub/GitLab (suportam Mermaid nativamente). Se abrir num visualizador sem suporte a Mermaid, o bloco aparece como texto — a leitura ainda funciona, só não é visual.