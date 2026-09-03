# Parte 14 — Schema de Banco de Dados

> **Estado:** a migration inicial **[`database/migrations/0001_initial_schema.sql`](../../database/migrations/0001_initial_schema.sql)** foi aplicada ao banco `redacao` (DigitalOcean, MySQL 8.0) em **2026-08-27**, após aprovação do responsável ([seção 55](../ai/regras-claude-code.md#55-banco-de-dados--regras-específicas-de-alteração)). Este documento reflete o que existe no banco. Alterações futuras entram como novas migrations numeradas + atualização aqui ([seção 62.1](../ai/regras-claude-code.md#621-registro-de-alterações-novo--adicionado-nesta-reorganização-não-fazia-parte-da-numeração-original-do-readme)).

### 86. Convenções de schema

- Nomes de tabelas em `snake_case`, plural (ex.: `articles`, `editorial_rules`).
- Chave primária: `id` — `BIGINT UNSIGNED AUTO_INCREMENT`. Decisão registrada em [ADR-007](../decisions/adr-007-chave-primaria.md).
- `created_at` **e** `updated_at` em todas as tabelas (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `updated_at` com `ON UPDATE CURRENT_TIMESTAMP`).
- Chaves estrangeiras: `<tabela_singular>_id`, `BIGINT UNSIGNED`, com índice e `FOREIGN KEY` apontando pra `id` da tabela referenciada. `ON DELETE CASCADE` quando o filho não existe sem o pai; `ON DELETE SET NULL` quando é referência opcional.
- Soft delete onde fizer sentido (`deleted_at` nullable, hoje em `articles`) em vez de `DELETE` físico — coerente com a [regra de nunca apagar dados sem autorização](../ai/regras-claude-code.md#53-alterações-que-nunca-devem-ser-executadas-sem-autorização).
- Engine `InnoDB`, charset `utf8mb4`, collation `utf8mb4_0900_ai_ci`.
- **Atenção — `ANSI_QUOTES` ativo no servidor:** neste MySQL da DigitalOcean, aspas duplas são delimitador de identificador, não string. Em SQL e no código PHP, usar **aspas simples** para literais e **crase** para identificadores.

### 87. Tabelas — estado atual (migration 0001)

**19 tabelas.** As marcadas `[+]` foram adicionadas além do rascunho original desta seção — justificativa no cabeçalho da migration.

```
users
  id, name, email (unique), password_hash, role (ADMIN | REDATOR_CHEFE), is_active, timestamps

sites
  id, name, niche, language, target_audience, tone, wordpress_url, is_active, timestamps

user_site                         -- N:N usuário <-> site (unique user_id+site_id)
  id, user_id, site_id, timestamps

site_authors                      -- [+] autores do WordPress por site; alvo de schedules.author_id
  id, site_id, wordpress_author_id, name, is_active, timestamps

site_wordpress_connections        -- [+] credencial WP isolada por site (seguranca §46-47)
  id, site_id (unique), username, app_password_encrypted (VARBINARY — cifrado, nunca plaintext),
  status, last_verified_at, timestamps

categories                        -- categorias fixas por site (unique site_id+name)
  id, site_id, name, guidelines, timestamps

tags                              -- [+] rótulos transversais por site (decisão do checklist SEO)
  id, site_id, name, slug, timestamps   -- unique site_id+slug

editorial_rules                   -- interesses / não-interesses
  id, site_id, type (INTEREST | NON_INTEREST), description, intensity (CHECK 1-5), timestamps

goals                             -- uma meta por período (unique site_id+period)
  id, site_id, period (ex.: "2026-09"), total_articles, general_guidelines, timestamps

goal_categories
  id, goal_id, category_id, target_count, timestamps   -- unique goal_id+category_id

articles
  id, site_id, category_id?, goal_id?, title?,
  status (PLANNED | IN_PROGRESS | IN_REVIEW | REVISION_REQUESTED | APPROVED | SCHEDULED |
          PUBLISHED | DISCARDED | BLOCKED),
  slug?, focus_keyword?, meta_description?,          -- [+] SEO (seo.md / compliance.md)
  lineage_id?, attempt_number, published_at?,        -- lineage_id: só coluna agrupadora (ver 87.2)
  timestamps, deleted_at?                            -- unique site_id+slug ; index site_id+focus_keyword

article_versions
  id, article_id, content (LONGTEXT), word_count?, timestamps   -- word_count: regra "≥ 1500 palavras"

article_tags                      -- [+] N:N artigo <-> tag (unique article_id+tag_id)
  id, article_id, tag_id, timestamps

article_sources                   -- [+] fontes usadas na pesquisa (fluxo-editorial §24)
  id, article_id, url, title?, publisher?, accessed_at?, timestamps

feedback                          -- motivo + justificativa de rejeição (fluxo-editorial §28)
  id, article_id, reason (VARCHAR — lista em evolução), justification, created_by?, timestamps

images                            -- opções do Nano Banana + escolha do redator
  id, article_id, url, role (FEATURED | BODY), alt_text?, selected, prompt?, format?, timestamps

schedules                         -- agendamento de publicação
  id, article_id, author_id?, image_id?, scheduled_date (DATETIME),
  status (PENDING | PUBLISHED | CANCELED | FAILED), wordpress_post_id?, timestamps

ai_executions                     -- cada etapa técnica (fila Redis + custo + retry)
  id, article_id, step (research | writing | seo | image | review), provider?, cost (DECIMAL),
  status (QUEUED | RUNNING | SUCCESS | FAILED | RETRYING),
  queue_job_id?, queued_at?, started_at?, finished_at?, retry_count, error_message?, timestamps

knowledge_sources                 -- registro interno das fontes NotebookLM (integração FUTURA, §40)
  id, site_id, notebook_id?, source_id?, name, type?, reference_url?, status, added_by?,
  added_at, updated_at

editorial_insights                -- [+] Centro de Inteligência Editorial (RF-014, §33) — análise narrativa por site
  id, site_id, content (JSON — as 6 respostas da §33), model, cost (DECIMAL),
  prompt_tokens, output_tokens, generated_by?, created_at
```

`?` = coluna nullable.

Além dessas, o runner [`database/migrate.php`](../../database/migrate.php) mantém a tabela de controle **`schema_migrations`** (`filename`, `applied_at`) — não é do modelo de domínio, só registra quais migrations já rodaram.

**Migrations posteriores à 0001** (Fases 4–7):
- `0002` — `ai_executions.step`: +`planning`, +`compliance` no ENUM.
- `0003` — nova tabela **`article_ai_notes`** (`article_id`, `step`, `payload` JSON): parecer de cada passo da IA (planning…review).
- `0004` — `sites.editorial_identity` (TEXT): descrição de voz/estilo do site para o `PromptBuilder`.
- `0005` — `images.alt_text` (VARCHAR 500); `article_ai_notes.step`: +`image` no ENUM. Fase 5.3.
- `0006` — `categories.wordpress_category_id` (BIGINT UNSIGNED, nullable) + `UNIQUE (site_id, wordpress_category_id)`: id da categoria no WordPress do site. Fase 7.3.
- `0007` — `schedules.wp_media_ids` (VARCHAR 500, JSON): ids de mídia criados no WordPress por este agendamento, para limpar ao atualizar/retirar. Fase 7.5.
- `0008` — `articles.review_started_at` / `reviewed_at` (TIMESTAMP nullable): janelas do "tempo médio de revisão" do relatório mensal. Fase 8.1.
- `0009` — nova tabela **`editorial_insights`**: histórico das análises do Centro de Inteligência Editorial por site (uma chamada paga ao Gemini por geração). Fase 8.3.
- `0010` — `articles.status`: +`ERROR` no ENUM — falha técnica definitiva do pipeline rodando via fila (retries esgotados). Fase 9.1b.

### 87.1 Autenticação — sem tabela

O login (RF-001) usa **sessão nativa do PHP** ([requisitos §64.2](requisitos.md#642-autenticação)) — não há tabela de sessão nem de token. `users.password_hash` guarda o hash (`password_hash()`).

### 87.2 Pendências de modelagem `[PROPOSTA]`

Não entraram na migration 0001 — dependem de decisão do responsável:

- **`article_lineages` / `content_slots`** — a Fase 6.2 usa `articles.lineage_id` de forma **auto-referente** (o 1º artigo da cadeia aponta para si; regeneração cria nova linha com o mesmo `lineage_id` e `attempt_number + 1`). Cobre "3 tentativas por linhagem → `BLOCKED`". O conceito de **múltiplas linhagens por slot** ("2 linhagens completas → `BLOCKED`") foi adiado — se for necessário, aí sim modelar Slot → Linhagem → Artigo.
- **`editorial_memory`** — a Fase 6.3 **não** criou tabela: a "memória editorial" é derivada em tempo de geração do próprio histórico (`feedback` recente do site + artigos já aprovados), injetada no prompt via `PromptBuilder::memoryLayer()`. Uma tabela de "lições curadas/resumidas pela IA" continua possível para a Fase 8 (Inteligência Editorial).
- ~~`site_ai_budgets`~~ — **não precisou** (Fase 9.2): o limite de custo de IA por site/mês ([seção 95](testes-e-observabilidade.md#95-controle-de-custo-de-ia-proposta)) é calculado dinamicamente a cada carregamento (`CostBudgetService`), sem guardar número fixo em tabela.
- ~~`articles.status = 'ERROR'`~~ — **implementado** (Fase 9.1b, migration `0010`): estado para falha técnica definitiva do pipeline rodando via fila ([seção 96](testes-e-observabilidade.md#96-política-de-retry--falha-da-ia-proposta)). Motivo vem de `ai_executions.error_message`, sem coluna nova.

### 87.3 Tabela `accounts` — legado, ignorar

A tabela **`accounts`** (2 colunas com espaços no nome, `id` sem `AUTO_INCREMENT`, 0 linhas) é um teste antigo, fora do modelo — **não** é a tabela de login (essa é `users`). Decisão do responsável (2026-08-27): **deixar como está** — está isolada, sem FK, não atrapalha. Não usar. `DROP` só se algum dia incomodar, sob a [seção 53](../ai/regras-claude-code.md#53-alterações-que-nunca-devem-ser-executadas-sem-autorização).

## Ver também

- [`database/migrations/0001_initial_schema.sql`](../../database/migrations/0001_initial_schema.sql) — a migration aplicada
- [Requisitos — modelo de dados conceitual](requisitos.md#66-modelo-de-dados-conceitual)
- [Integrações — armazenamento de imagens](integracoes.md#361-armazenamento-das-imagens-geradas-proposta)
- [ADR-007 — Chave primária das tabelas](../decisions/adr-007-chave-primaria.md)
