# Editorial Dashboard — Plataforma Editorial Inteligente

Plataforma interna de gestão editorial com inteligência artificial, criada para centralizar e automatizar a produção de conteúdo de aproximadamente **60+ sites WordPress**. A IA cuida da parte operacional (pesquisa, produção, SEO, imagens); o Redator-Chefe decide o que publicar. **Nenhum artigo é publicado sem aprovação humana.**

Este README ficou curto de propósito — a documentação completa foi organizada em `docs/`, seguindo a mesma divisão descrita na [Arquitetura de diretórios](docs/technical/arquitetura.md#11-arquitetura-de-diretórios).

## Status atual

- **Última revisão:** 2026-09-01
- **Stack resumida:** PHP puro (backend + Views) + MySQL + Redis + Tailwind CSS (CLI standalone) — **sem Node.js**.
- **Mudança mais recente:** o frontend deixou de ser Next.js/React/TypeScript e passou a ser renderizado em PHP puro. Ver [ADR-008](docs/decisions/adr-008-frontend-php-puro.md).

## Por onde começar

1. Este bloco de **Status atual** — stack e decisão mais recente.
2. [docs/ai/regras-claude-code.md](docs/ai/regras-claude-code.md) — regras de aprovação e o que nunca fazer sem autorização explícita.
3. O documento específico relacionado à tarefa em mãos (ver Sumário abaixo).

## Decisões em aberto

- Valor numérico do limite de custo de IA por site/mês (o método de cálculo já está definido) → [docs/technical/testes-e-observabilidade.md](docs/technical/testes-e-observabilidade.md#95-controle-de-custo-de-ia-proposta)

## Sumário

> Índice completo com mapa visual dos documentos: [docs/README.md](docs/README.md)

**Produto**
- [Visão geral do produto](docs/product/visao-geral.md) — o que é, objetivo, princípios, conceito central
- [Roadmap e escopo](docs/product/roadmap.md) — fases 1–9, critério de sucesso, objetivo final
- [Identidade visual](docs/product/identidade-visual.md) — nome COMPOST, mockups, paleta de cores
- [Onboarding de um novo site](docs/product/onboarding-site.md) `[PROPOSTA]`
- [Onboarding de um novo usuário](docs/product/onboarding-usuario.md) `[PROPOSTA]`

**Editorial**
- [Fluxo editorial](docs/editorial/fluxo-editorial.md) — usuários e sites, produção com IA, revisão/publicação/relatórios
- [Compliance de conteúdo](docs/editorial/compliance.md) — regras de qualidade/AdSense e elementos obrigatórios de um post
- [SEO On-Page & Checklist Editorial](docs/editorial/seo.md) — checklist de SEO (título, meta descrição, links, imagens/WebP, canibalização, tags)

**Técnico**
- [Arquitetura](docs/technical/arquitetura.md) — stack, padrão MVC, diretórios
- [Integrações](docs/technical/integracoes.md) — Gemini, Nano Banana, WordPress, MySQL, Beekeeper, NotebookLM
- [Segurança](docs/technical/seguranca.md) — política de credenciais e checklist de deploy
- [Requisitos e modelagem](docs/technical/requisitos.md) — RF/RB, permissões, autenticação, máquina de estados, contratos de API
- [Schema de banco de dados](docs/technical/schema.md) — migration 0001 aplicada
- [Convenções de código](docs/technical/padroes-de-codigo.md) `[PROPOSTA]`
- [UI/UX & Frontend Standards](docs/technical/ui-ux-frontend.md) `[PROPOSTA]` — padrão de interface, UX e acessibilidade (Apple HIG + WCAG 2.2 AA + Material 3)
- [Testes e observabilidade](docs/technical/testes-e-observabilidade.md) — PHPUnit (`composer test`), cache, performance
- [Setup e operações](docs/technical/setup-e-operacoes.md) — backup, ambiente de dev, getting started completo
- [Diagrama de sequência](docs/technical/diagrama-sequencia.md) `[PROPOSTA]`

**Governança de IA**
- [Regras para o Claude Code](docs/ai/regras-claude-code.md) — o que precisa de aprovação, o que nunca fazer sozinho

**Decisões e referências**
- [ADRs (decisões arquiteturais)](docs/decisions/README.md)
- [Glossário](docs/glossario.md)
- [Referências oficiais](docs/referencias.md)
- [CHANGELOG](CHANGELOG.md) `[PROPOSTA]`

> ⚠️ Documentos marcados `[PROPOSTA]` ainda não foram validados pelo responsável do projeto — ver aviso completo no início de [docs/technical/setup-e-operacoes.md](docs/technical/setup-e-operacoes.md).

## Getting started (resumido)

```bash
git clone <url-do-repositorio>
cd editorial-dashboard
cp .env.example .env      # preencher com os valores reais (peça o arquivo local de credenciais)
composer install
./tailwindcss -i src/styles/input.css -o public/assets/css/app.css --watch
php -S localhost:8080 -t public
```

Node.js **não** é necessário (ver [ADR-008](docs/decisions/adr-008-frontend-php-puro.md)). Guia completo, pré-requisitos e comandos úteis: [docs/technical/setup-e-operacoes.md](docs/technical/setup-e-operacoes.md).

Testes automatizados (PHPUnit — [docs/technical/testes-e-observabilidade.md](docs/technical/testes-e-observabilidade.md#92-estratégia-de-testes)):

```bash
composer test:unit         # rápido, sem banco/Redis — seguro rodar sempre
composer test:integration  # bate no banco de dev de verdade
```
