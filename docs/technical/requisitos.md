# Parte 9 — Requisitos, Regras de Negócio e Modelagem

### 63. Requisitos funcionais (RF)

- RF-001 — Usuário pode fazer login
- RF-002 — Admin pode criar site
- RF-003 — Admin pode atribuir site
- RF-004 — Redator só vê sites permitidos
- RF-005 — Redator pode criar meta
- RF-006 — IA pode gerar pauta

`[PROPOSTA — itens abaixo adicionados nesta reorganização, formalizando como RF fluxos já descritos em outras partes da documentação (não são funcionalidade nova)]`

- RF-007 — Redator-Chefe pode revisar um artigo em `IN_REVIEW` (ver [Revisão humana](../editorial/fluxo-editorial.md#27-revisão-humana-estados-do-artigo))
- RF-008 — Redator-Chefe pode aprovar um artigo, com o artigo passando a `APPROVED`
- RF-009 — Redator-Chefe pode rejeitar um artigo, informando motivo e justificativa (ver [Feedback](../editorial/fluxo-editorial.md#28-feedback))
- RF-010 — Sistema pode regenerar um artigo rejeitado, respeitando o limite de tentativas por linhagem (ver [Regeneração](../editorial/fluxo-editorial.md#29-regeneração))
- RF-011 — Redator-Chefe pode agendar um artigo aprovado (imagem, categoria, autor, data, horário — ver [Agendamento](../editorial/fluxo-editorial.md#30-agendamento))
- RF-012 — Sistema pode publicar um artigo agendado no WordPress do site correspondente (ver [Integração WordPress](../editorial/fluxo-editorial.md#31-integração-wordpress))
- RF-013 — Redator-Chefe pode visualizar relatórios (mensal, o que melhorou/não melhorou, aprendizados — ver [Relatórios](../editorial/fluxo-editorial.md#32-relatórios))
- RF-014 — Redator-Chefe pode visualizar o Centro de Inteligência Editorial do site (ver [seção 33](../editorial/fluxo-editorial.md#33-centro-de-inteligência-editorial))
- RF-015 — Redator-Chefe pode configurar categorias, interesses e não-interesses do site (ver [seções 15, 18, 19](../editorial/fluxo-editorial.md#15-estrutura-editorial-de-cada-site))
- RF-016 — Admin pode configurar a conexão WordPress (URL, usuário, credencial) de cada site (ver [Credenciais específicas por site](seguranca.md#46-credenciais-específicas-por-site))
- RF-017 — Admin pode convidar/cadastrar um novo usuário e vincular Redator-Chefe(s) a sites (ver [Onboarding de usuário](../product/onboarding-usuario.md))
- ...

### 64. Regras de negócio (RB)

- RB-001 — Somente ADMIN pode criar usuários.
- RB-002 — Redator só pode acessar sites vinculados.
- RB-003 — Somente APPROVED conta para a meta.
- RB-004 — Conteúdo rejeitado precisa ter motivo.
- RB-005 — Conteúdo aprovado pode ser agendado.
- RB-006 — Conteúdo não aprovado nunca pode ser publicado.

### 64.1 Tabela de permissões por recurso `[PROPOSTA]`

> Adicionado nesta reorganização, consolidando o que já estava descrito em prosa nas seções 14 e 51, e nas regras RB-001/RB-002 — nada de novo, só consolidado numa tabela.

| Recurso | ADMIN | REDATOR-CHEFE |
|---|---|---|
| Sites (criar/editar estrutura) | ✓ | — |
| Vincular usuário a site | ✓ | — |
| Usuários (criar/editar) | ✓ | — |
| Permissões de acesso | ✓ | — |
| Categorias / regras editoriais do site | ✓ (ou delega) | ✓ (nos sites vinculados) |
| Metas | — | ✓ (nos sites vinculados) |
| Produção / revisão de artigos | — | ✓ (nos sites vinculados) |
| Aprovar / rejeitar / regenerar artigo | — | ✓ (nos sites vinculados) |
| Agendamento e publicação | — | ✓ (nos sites vinculados) |
| Relatórios e Inteligência Editorial | Visão geral (todos os sites) | ✓ (nos sites vinculados) |
| Conexão WordPress por site (credenciais) | ✓ | — |
| Configurações globais da plataforma | ✓ | — |

> Base: [Usuários e permissões (seção 14)](../editorial/fluxo-editorial.md#14-usuários-e-permissões), [RB-001/RB-002](#64-regras-de-negócio-rb), [Regra de aprovação (seção 51)](../ai/regras-claude-code.md#51-regra-de-aprovação-e-controle-de-alterações). "Nos sites vinculados" reforça o isolamento por site (ver [seção 46](seguranca.md#46-credenciais-específicas-por-site)) — um Redator-Chefe nunca vê dado de um site ao qual não está vinculado.

### 64.2 Autenticação

> **Implementado na Fase 2** (branch `feature/login`). Decisão confirmada pelo responsável.

- **Sessão via PHP nativo** (`session_start()` / `$_SESSION`), sem biblioteca externa de autenticação/JWT — coerente com a filosofia "sem dependência pesada" das [ADR-002](../decisions/adr-002-php-pdo.md) e [ADR-006](../decisions/adr-006-fila-redis.md). Wrapper em `src/Support/Session.php`.
- Senha com hash (`password_hash` / `password_verify`, `PASSWORD_DEFAULT` = bcrypt), nunca em texto puro — coluna `users.password_hash`. Ver [Política de segurança para credenciais](seguranca.md#48-política-de-segurança-para-credenciais).
- Cookie de sessão `HttpOnly` sempre; `Secure` fora de `development`; `SameSite=Lax`; `session.use_strict_mode`; `session_regenerate_id` no login (contra fixation).
- **CSRF** em todo formulário POST (`src/Support/Csrf.php`, token por sessão + `hash_equals`).
- Rotas protegidas via flag `auth` no `Router`: `GET` sem sessão redireciona para `/login`, demais métodos → `401`.
- Primeiro `ADMIN` criado por `database/seeds/create_admin.php` (senha só por variável de ambiente).
- Sem "lembrar-me" / token de longa duração na primeira versão — pode entrar depois ([Mudança de escopo, seção 57](../ai/regras-claude-code.md#57-mudança-de-escopo)).

Alternativas registradas e não escolhidas: JWT stateless, ou sessão no Redis (útil com múltiplas instâncias na Fase 9). Ambas adicionam complexidade não justificada agora.

### 65. Máquina de estados do artigo

```
PLANNED
   ↓
IN_PROGRESS
   ↓
IN_REVIEW
   ├──→ APPROVED
   │       ↓
   │   SCHEDULED
   │       ↓
   │   PUBLISHED
   │
   ├──→ REVISION_REQUESTED
   │       ↓
   │   IN_PROGRESS
   │
   └──→ DISCARDED
```

### 65.1 Estado × ação possível por perfil `[PROPOSTA]`

> Adicionado nesta reorganização, cruzando a máquina de estados acima com as permissões da [seção 64.1](#641-tabela-de-permissões-por-recurso-proposta).

| Estado | ADMIN pode | REDATOR-CHEFE (no site) pode |
|---|---|---|
| `IN_PROGRESS` | Ver | Ver, acompanhar produção |
| `IN_REVIEW` | Ver | Revisar, aprovar, rejeitar |
| `APPROVED` | Ver | Agendar |
| `REVISION_REQUESTED` | Ver | Ver motivo, aguardar regeneração |
| `SCHEDULED` | Ver | Reagendar, cancelar agendamento |
| `PUBLISHED` | Ver | Ver, consultar em relatórios |
| `DISCARDED` | Ver histórico | Ver histórico |
| `BLOCKED` | Ver, é alertado | Decidir próximo passo (ver [Regeneração, seção 29](../editorial/fluxo-editorial.md#29-regeneração)) |

> Em todos os estados, ADMIN só enxerga dado agregado/visão geral (ver [seção 33](../editorial/fluxo-editorial.md#33-centro-de-inteligência-editorial)) — quem opera o artigo no dia a dia é o Redator-Chefe do site.

### 66. Modelo de dados conceitual

```
USER
 ↓
USER_SITE
 ↓
SITE
 ├── CATEGORY
 ├── EDITORIAL_RULE
 ├── GOAL
 │    └── GOAL_CATEGORY
 │
 └── ARTICLE
      ├── FEEDBACK
      ├── IMAGE
      ├── VERSION
      └── SCHEDULE
```

### 67. Contrato da API (endpoints)

```
GET    /api/sites
GET    /api/sites/:id
POST   /api/sites

GET    /api/articles
GET    /api/articles/:id
POST   /api/articles/:id/approve
POST   /api/articles/:id/reject

GET    /api/goals
POST   /api/goals

GET    /api/reports/monthly
```

> **`[PROPOSTA]`** — prefixar as rotas com `/api/v1/` já na primeira versão, sem custo extra (é só um prefixo no roteador). Ainda não decidido.

### 68. Contrato de endpoints internos (AJAX, exemplo)

Como a aplicação é um monólito PHP que renderiza HTML (ver [seção 5](../technical/arquitetura.md#5-stack-do-projeto--frontend) e [seção 10](../technical/arquitetura.md#10-padrão-mvc-adaptado)), a maioria das rotas responde HTML diretamente. Este contrato JSON se aplica apenas a endpoints internos consumidos por JavaScript vanilla no navegador (ex.: atualização assíncrona de um widget do dashboard), não a um frontend separado.

JavaScript vanilla solicita:
```
GET /api/dashboard
```

PHP responde:
```json
{
  "meta": {},
  "pendingReview": {},
  "production": {},
  "alerts": {}
}
```

### 69. Critérios de aceitação (exemplo)

**Funcionalidade:** Permissão por site

**Aceito quando:**

- ✓ Admin consegue vincular usuário ao site
- ✓ Usuário vê somente sites autorizados
- ✓ API bloqueia acesso não autorizado
- ✓ Frontend não expõe dados do site bloqueado

## Ver também

- [Fluxo editorial](../editorial/fluxo-editorial.md) — os processos por trás desses RF/RB
- [Schema de banco de dados](schema.md) — onde essas entidades viram tabela
- [Diagramas de sequência](diagrama-sequencia.md) — os fluxos de estado em nível de chamadas
- [Segurança](seguranca.md) — política de credenciais referenciada na autenticação
