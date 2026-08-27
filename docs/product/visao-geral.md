# Parte 1 — Visão Geral do Produto

### 1. Visão geral

A Editorial Dashboard é uma plataforma interna de gestão editorial com inteligência artificial criada para centralizar e automatizar a produção de conteúdo de aproximadamente **60+ sites WordPress**.

O sistema será utilizado por dois perfis principais:

- **Administrador**: responsável pela estrutura da plataforma, sites, usuários, permissões, nicho e idioma.
- **Redator-Chefe**: responsável pela estratégia editorial, metas, revisão, aprovação e agendamento dos conteúdos dos sites aos quais possui acesso.

A IA será responsável pela parte operacional da produção:

- planejamento de pautas;
- pesquisa;
- análise de conteúdo existente;
- produção do artigo;
- SEO;
- geração de imagens;
- validação;
- utilização de feedback editorial.

**Nenhum artigo deverá ser publicado sem aprovação humana.**

### 2. Objetivo do projeto

O objetivo é reduzir o trabalho operacional dos redatores e permitir que uma equipe pequena consiga administrar uma operação editorial com dezenas de sites.

A plataforma não será apenas um gerador de artigos. Ela será uma **central de gestão editorial**, onde:

- O redator define: o que precisa ser produzido e qual é a intenção editorial.
- A plataforma e a IA cuidam de: como pesquisar, estruturar e produzir.
- O redator decide: pode publicar.

### 3. Princípios do produto

A plataforma deve seguir os seguintes princípios:

**Simplicidade**
O usuário final não deve precisar entender programação ou inteligência artificial.

**Clareza**
Cada tela deve responder:
- Onde estou?
- O que está acontecendo?
- O que precisa da minha atenção?
- Qual é o próximo passo?

**Controle humano**
A IA produz, mas o redator aprova.

**Separação por site**
Cada site possui suas próprias regras, identidade e permissões.

**Escalabilidade**
A arquitetura deve permitir trabalhar com 60 sites ou mais sem reconstruir o sistema.

**Qualidade antes de velocidade**
Todos os artigos seguem um único padrão de produção. Não haverá seleção de "rápido", "normal" ou "premium". O prazo operacional esperado é de até 24 horas por artigo, mas isso não significa deixar a IA processando sem motivo durante 24 horas — significa que o processo completo (pesquisa, produção e validação) terá esse prazo máximo.

### 4. Conceito central (fluxo macro)

```
INTENÇÃO
   ↓
PLANEJAMENTO
   ↓
PESQUISA
   ↓
PRODUÇÃO
   ↓
VALIDAÇÃO
   ↓
REVISÃO HUMANA
   ↓
APROVAÇÃO
   ↓
AGENDAMENTO
   ↓
WORDPRESS
   ↓
PUBLICAÇÃO
```

## Ver também

- [Roadmap e escopo](roadmap.md) — como esses princípios viram fases
- [Fluxo editorial](../editorial/fluxo-editorial.md) — o conceito central em detalhe
- [Identidade visual](identidade-visual.md) — nome e visual da plataforma
- [Regras para o Claude Code](../ai/regras-claude-code.md) — como esses princípios se traduzem em governança de desenvolvimento
