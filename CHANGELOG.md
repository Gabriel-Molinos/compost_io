# Changelog

> Estrutura sugerida — movida da antiga Parte 20 / seção 99 do README (`[PROPOSTA]`). Manter este arquivo atualizado ao final de cada fase do [Roadmap](docs/product/roadmap.md#71-roadmap-completo-fases-19).

## [Fase 1] - Fundação
### Adicionado
- Estrutura inicial do projeto (frontend, backend, banco)
- Conexão frontend/backend
- Documentação técnica inicial
- 2026-08-27 — `database/migrations/0001_initial_schema.sql` aplicada ao banco `redacao` (19 tabelas; ver [schema.md](docs/technical/schema.md))
- 2026-08-27 — docs: [Parte 20 — UI/UX & Frontend Standards](docs/technical/ui-ux-frontend.md) e [SEO On-Page & Checklist Editorial](docs/editorial/seo.md)
- 2026-08-27 — esqueleto da aplicação: `composer.json` (autoload PSR-4), front controller (`public/index.php`) + roteador manual, `Env` + `Connection` (PDO/SSL), View em PHP puro, layout base acessível + Tailwind CLI standalone, runner de migrations (`database/migrate.php`), `.env.example`. Rota `/` mostrando status do banco.

## [Fase 2] - Usuários e sites
### Adicionado
- Login e autenticação
- CRUD de usuários e sites
- Sistema de permissões

### Em progresso
- 2026-08-27 — autenticação por sessão nativa do PHP: `Session`, `Csrf`, `AuthService`, `AuthController`, guard `auth` no Router, telas de login/logout. Primeiro ADMIN via `database/seeds/create_admin.php`. Ver [requisitos §64.2](docs/technical/requisitos.md#642-autenticação). *(merge em `develop`)*
- 2026-08-27 — CRUD de usuários e sites (somente ADMIN) + vínculo `user_site`; `Router` com parâmetros e guard `admin`; `Validator` e `Form` (campos acessíveis); `ErrorController` 401/403/404. Cobre RF-002, RF-003, RF-004 (backend), RF-017. *(merge em `develop`)*

## [Fase 3] - Planejamento

### Adicionado
- 2026-08-27 — configuração editorial por site: área de trabalho `/sites/{id}` com abas; CRUD de **categorias** (nome único por site + diretrizes) e de **interesses / não-interesses** (intensidade 1–5). Acesso por site via `Controller::requireSite()` / `AuthService::canAccessSite()` — Redator-Chefe só nos sites vinculados.
- 2026-08-27 — **metas editoriais por site** (`/sites/{id}/goals`): CRUD de metas por período (`AAAA-MM`, único por site), total de artigos, diretrizes gerais e distribuição por categoria (`goal_categories`, reescrita em transação). Valida formato do período, trata período duplicado como erro de formulário e impede a soma por categoria passar do total.

Cobre RF-005 e RF-015. Fase 3 (Planejamento) concluída — mergeada em `develop` via `feature/editorial-config`.

## [Fase 4] - IA

### Em progresso (branch `feature/ia-fase-4`)
- 2026-08-27 — **Prompt Base** (fatia 4.1): arquivos de prompt versionados em `docs/ai/` (`base-editorial`, `planning`, `research`, `writing`, `seo`, `compliance`, `review`) + `PromptBuilder` (`src/Services/PromptBuilder.php`) que monta o prompt final em camadas — Prompt Base + Passo + Identidade do Site + Meta + Categoria + Brief (Memória Editorial fica para a Fase 6). Sem chamada a API externa. Pré-visualização por `php bin/prompt_preview.php <step> <site_id>`. Testado contra o site Gavsy (18 asserts).
- 2026-08-28 — **Integração Gemini** (fatia 4.2): `src/Integrations/` — interface `AIProvider` + `AIResult`, e `Gemini/` (`GeminiConfig`, `GeminiClient` cURL, `GeminiProvider`, `GeminiException`). Saída estruturada via `responseSchema`, contagem de tokens (inclui `thoughtsTokenCount` do modelo *thinking*). Bundle de CA versionado (`tools/cacert.pem`, `App\Support\CaBundle`) para HTTPS de saída. `.env`: `GEMINI_API_KEY`, `GEMINI_MODEL` (`gemini-2.5-pro`). Doc em `docs/integrations/gemini.md`. Testado com chamada real: `php bin/gemini_smoke.php`.

> Pode ser gerado manualmente ou, se o [padrão de commits](docs/technical/padroes-de-codigo.md#90-padrão-de-commits-proposta) for adotado, automatizado com ferramentas como `standard-version` ou `release-please`.

## Ver também

- [Roadmap](docs/product/roadmap.md) — fases que geram entradas aqui
- [Registro de alterações (regra 62.1)](docs/ai/regras-claude-code.md#621-registro-de-alterações-novo--adicionado-nesta-reorganização-não-fazia-parte-da-numeração-original-do-readme)
