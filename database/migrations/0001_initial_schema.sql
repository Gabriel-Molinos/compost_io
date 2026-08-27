-- =============================================================================
-- Migration 0001 — Schema inicial da plataforma COMPOST (Editorial Dashboard)
-- =============================================================================
-- Alvo:        MySQL 8.0 (DigitalOcean managed) · banco `redacao`
-- Base:        docs/technical/schema.md §87 (tabelas principais — rascunho)
--              docs/technical/schema.md §86 (convenções)
--              docs/decisions/adr-007-chave-primaria.md (PK BIGINT UNSIGNED AUTO_INCREMENT)
--              docs/technical/requisitos.md §65 (máquina de estados do artigo)
--              docs/editorial/seo.md + docs/editorial/compliance.md (campos de SEO, tags)
--              docs/technical/seguranca.md §46-47 (credenciais WordPress por site)
--
-- STATUS: RASCUNHO PARA REVISÃO — NÃO EXECUTAR SEM AUTORIZAÇÃO (regras-claude-code.md §55).
--
-- Como aplicar depois de aprovado (exemplo, sem senha no comando):
--   $env:DB_PASS = '...'      # PowerShell
--   mysql -h database-do-user-9074491-0.e.db.ondigitalocean.com -P 25060 \
--         -u estagio -p"$env:DB_PASS" --ssl-mode=REQUIRED redacao < 0001_initial_schema.sql
--
-- Convenções aplicadas (§86):
--   · nomes de tabela em snake_case, plural
--   · PK: `id` BIGINT UNSIGNED AUTO_INCREMENT (ADR-007)
--   · `created_at` / `updated_at` em TODAS as tabelas (a §87 às vezes só cita created_at;
--     a convenção §86 pede os dois — seguimos a §86)
--   · FK: `<tabela_singular>_id` BIGINT UNSIGNED, com índice + FOREIGN KEY
--   · soft delete (`deleted_at` nullable) onde faz sentido (artigos)
--
-- Marcadores:
--   [§87]  = tabela/coluna que já está no rascunho de schema
--   [+]    = adição além da §87 (justificada no comentário) — CONFIRMAR NA REVISÃO
--   [TODO] = ponto que ainda depende de decisão do responsável
-- =============================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Limpeza da tabela de teste encontrada no banco (colunas com espaços no nome,
-- `id` sem AUTO_INCREMENT, 0 linhas). Não faz parte do modelo.
-- DROP exige autorização explícita (regras-claude-code.md §53) — deixado comentado.
-- -----------------------------------------------------------------------------
-- DROP TABLE IF EXISTS `accounts`;


-- =============================================================================
-- 1. USUÁRIOS E SITES
-- =============================================================================

-- [§87] users
CREATE TABLE IF NOT EXISTS `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(191)    NOT NULL,
  `email`         VARCHAR(191)    NOT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,          -- password_hash() do PHP (requisitos.md §64.2)
  `role`          ENUM('ADMIN','REDATOR_CHEFE') NOT NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1, -- [+] desativar login sem apagar o usuário
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] sites
CREATE TABLE IF NOT EXISTS `sites` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(191)    NOT NULL,
  `niche`           VARCHAR(191)    NULL,
  `language`        VARCHAR(20)     NOT NULL DEFAULT 'pt-BR',
  `target_audience` VARCHAR(255)    NULL,
  `tone`            VARCHAR(100)    NULL,
  `wordpress_url`   VARCHAR(255)    NULL,             -- credencial fica em site_wordpress_connections
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1, -- [+]
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sites_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] user_site — vínculo N:N usuário <-> site (independente da função, fluxo-editorial §14)
CREATE TABLE IF NOT EXISTS `user_site` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `site_id`    BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_site` (`user_id`,`site_id`),
  KEY `idx_user_site_site` (`site_id`),
  CONSTRAINT `fk_user_site_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_site_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [+] site_authors — autores do WordPress de cada site (RF: "listar autores", integracoes §37).
--     Alvo da FK schedules.author_id (a §87 cita `author_id` mas não define a tabela).
CREATE TABLE IF NOT EXISTS `site_authors` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`              BIGINT UNSIGNED NOT NULL,
  `wordpress_author_id`  BIGINT UNSIGNED NULL,       -- id do autor no WordPress do site
  `name`                 VARCHAR(191)    NOT NULL,
  `is_active`            TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_authors_wp` (`site_id`,`wordpress_author_id`),
  CONSTRAINT `fk_site_authors_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [+] site_wordpress_connections — credencial WordPress isolada por site (seguranca.md §46-47).
--     app_password NUNCA em texto puro: guardar cifrado pela aplicação (§47).
CREATE TABLE IF NOT EXISTS `site_wordpress_connections` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`              BIGINT UNSIGNED NOT NULL,
  `username`             VARCHAR(191)    NOT NULL,
  `app_password_encrypted` VARBINARY(512) NOT NULL,  -- cifrado no backend, nunca plaintext (§47)
  `status`               VARCHAR(30)     NOT NULL DEFAULT 'UNVERIFIED', -- UNVERIFIED|OK|FAILED
  `last_verified_at`     TIMESTAMP       NULL,
  `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_wp_conn_site` (`site_id`),
  CONSTRAINT `fk_site_wp_conn_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =============================================================================
-- 2. CONFIGURAÇÃO EDITORIAL DO SITE
-- =============================================================================

-- [§87] categories — categorias fixas por site (fluxo-editorial §15; compliance.md)
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(191)    NOT NULL,
  `guidelines` TEXT            NULL,                  -- diretrizes da categoria (fluxo-editorial §16)
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_site_name` (`site_id`,`name`),
  CONSTRAINT `fk_categories_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [+] tags — rótulos transversais por site (decisão do checklist SEO: usar tags em vez de
--     criar categorias novas — seo.md "Categorização e tags"; era "pendência técnica").
CREATE TABLE IF NOT EXISTS `tags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(191)    NOT NULL,
  `slug`       VARCHAR(191)    NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_site_slug` (`site_id`,`slug`),
  CONSTRAINT `fk_tags_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] editorial_rules — interesses / não-interesses com intensidade 1-5 (fluxo-editorial §18-19)
CREATE TABLE IF NOT EXISTS `editorial_rules` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('INTEREST','NON_INTEREST') NOT NULL,
  `description` VARCHAR(255)    NOT NULL,
  `intensity`   TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_editorial_rules_site_type` (`site_id`,`type`),
  CONSTRAINT `fk_editorial_rules_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_editorial_rules_intensity` CHECK (`intensity` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =============================================================================
-- 3. METAS E PLANEJAMENTO
-- =============================================================================

-- [§87] goals — uma meta por período (fluxo-editorial §16)
CREATE TABLE IF NOT EXISTS `goals` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`            BIGINT UNSIGNED NOT NULL,
  `period`             VARCHAR(20)     NOT NULL,      -- ex.: "2026-09" (§87 chama de `period`)
  `total_articles`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `general_guidelines` TEXT            NULL,          -- diretrizes gerais da meta (fluxo-editorial §16)
  `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goals_site_period` (`site_id`,`period`),
  CONSTRAINT `fk_goals_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] goal_categories — quantas peças de cada categoria a meta pede
CREATE TABLE IF NOT EXISTS `goal_categories` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `goal_id`      BIGINT UNSIGNED NOT NULL,
  `category_id`  BIGINT UNSIGNED NOT NULL,
  `target_count` INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goal_categories` (`goal_id`,`category_id`),
  KEY `idx_goal_categories_category` (`category_id`),
  CONSTRAINT `fk_goal_categories_goal` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_goal_categories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =============================================================================
-- 4. ARTIGOS
-- =============================================================================

-- [§87] articles
CREATE TABLE IF NOT EXISTS `articles` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`          BIGINT UNSIGNED NOT NULL,
  `category_id`      BIGINT UNSIGNED NULL,
  `goal_id`          BIGINT UNSIGNED NULL,
  `title`            VARCHAR(255)    NULL,            -- pode nascer sem título no estado PLANNED
  `status`           ENUM('PLANNED','IN_PROGRESS','IN_REVIEW','REVISION_REQUESTED',
                          'APPROVED','SCHEDULED','PUBLISHED','DISCARDED','BLOCKED')
                     NOT NULL DEFAULT 'PLANNED',      -- inclui PLANNED (requisitos.md §65) e BLOCKED (§87)
  -- SEO / compliance (seo.md, compliance.md) --------------------------------- [+]
  `slug`             VARCHAR(191)    NULL,            -- slug do post (elemento obrigatório, compliance.md)
  `focus_keyword`    VARCHAR(191)    NULL,            -- palavra-chave principal (checklist SEO)
  `meta_description` VARCHAR(320)    NULL,            -- meta descrição (checklist SEO)
  -- regeneração (fluxo-editorial §29) --------------------------------------------
  `lineage_id`       BIGINT UNSIGNED NULL,            -- [§87] agrupa tentativas do mesmo conteúdo.
                                                     -- [TODO] modelar "slot / linhagem" como tabela
                                                     -- própria está pendente (ver §29 e notas no fim).
  `attempt_number`   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `published_at`     TIMESTAMP       NULL,            -- [+] quando entrou em PUBLISHED (relatórios §32)
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       TIMESTAMP       NULL,            -- soft delete (§86): artigos descartados
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_site_slug` (`site_id`,`slug`),  -- MySQL permite múltiplos NULL; barra slug duplicado
  KEY `idx_articles_site_status` (`site_id`,`status`),
  KEY `idx_articles_goal` (`goal_id`),
  KEY `idx_articles_lineage` (`lineage_id`),
  KEY `idx_articles_site_keyword` (`site_id`,`focus_keyword`), -- checagem de canibalização (seo.md)
  CONSTRAINT `fk_articles_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_articles_goal` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] article_versions — cada versão do corpo do artigo
CREATE TABLE IF NOT EXISTS `article_versions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `content`    LONGTEXT        NOT NULL,              -- corpo em HTML
  `word_count` INT UNSIGNED    NULL,                  -- [+] usado na regra "≥ 1500 palavras" (seo.md)
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article_versions_article` (`article_id`),
  CONSTRAINT `fk_article_versions_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [+] article_tags — N:N artigo <-> tag
CREATE TABLE IF NOT EXISTS `article_tags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `tag_id`     BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_tags` (`article_id`,`tag_id`),
  KEY `idx_article_tags_tag` (`tag_id`),
  CONSTRAINT `fk_article_tags_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_article_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [+] article_sources — fontes usadas na pesquisa ("A plataforma deve registrar as
--     fontes utilizadas" — fluxo-editorial §24). Distinta de knowledge_sources (config NotebookLM).
CREATE TABLE IF NOT EXISTS `article_sources` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id`  BIGINT UNSIGNED NOT NULL,
  `url`         VARCHAR(1024)   NOT NULL,
  `title`       VARCHAR(255)    NULL,
  `publisher`   VARCHAR(191)    NULL,                 -- órgão/instituição (fluxo-editorial §24)
  `accessed_at` TIMESTAMP       NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article_sources_article` (`article_id`),
  CONSTRAINT `fk_article_sources_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] feedback — motivo + justificativa de uma rejeição (fluxo-editorial §28)
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id`    BIGINT UNSIGNED NOT NULL,
  `reason`        VARCHAR(50)     NOT NULL,           -- tema_fraco | informacao_incorreta | conteudo_superficial
                                                     -- | fora_do_tom | tema_repetido | nao_seguiu_meta
                                                     -- | problema_compliance | imagem_inadequada | outro
  `justification` TEXT            NOT NULL,
  `created_by`    BIGINT UNSIGNED NULL,               -- users.id (quem rejeitou)
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_article` (`article_id`),
  CONSTRAINT `fk_feedback_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] images — opções geradas pelo Nano Banana + qual foi escolhida (fluxo-editorial §25)
CREATE TABLE IF NOT EXISTS `images` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `url`        VARCHAR(1024)   NOT NULL,              -- caminho local / media library (integracoes §36.1)
  `role`       ENUM('FEATURED','BODY') NOT NULL DEFAULT 'FEATURED', -- [+] SEO: imagens de corpo (seo.md)
  `selected`   TINYINT(1)      NOT NULL DEFAULT 0,
  `prompt`     TEXT            NULL,                  -- [+] brief visual usado na geração
  `format`     VARCHAR(10)     NULL,                  -- [+] esperado 'webp' antes do upload (seo.md)
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_images_article` (`article_id`),
  CONSTRAINT `fk_images_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] schedules — agendamento de publicação (fluxo-editorial §30)
CREATE TABLE IF NOT EXISTS `schedules` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id`       BIGINT UNSIGNED NOT NULL,
  `author_id`        BIGINT UNSIGNED NULL,            -- site_authors.id
  `image_id`         BIGINT UNSIGNED NULL,            -- [+] imagem escolhida no agendamento (§30)
  `scheduled_date`   DATETIME        NOT NULL,        -- data + horário (§30)
  `status`           ENUM('PENDING','PUBLISHED','CANCELED','FAILED') NOT NULL DEFAULT 'PENDING',
  `wordpress_post_id` BIGINT UNSIGNED NULL,           -- id do post criado no WordPress
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_schedules_article` (`article_id`),
  KEY `idx_schedules_date_status` (`scheduled_date`,`status`),
  CONSTRAINT `fk_schedules_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedules_author` FOREIGN KEY (`author_id`) REFERENCES `site_authors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedules_image` FOREIGN KEY (`image_id`) REFERENCES `images` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =============================================================================
-- 5. IA E CONHECIMENTO
-- =============================================================================

-- [§87] ai_executions — cada etapa técnica da produção (fila Redis + custo + retry)
CREATE TABLE IF NOT EXISTS `ai_executions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id`    BIGINT UNSIGNED NOT NULL,
  `step`          ENUM('research','writing','seo','image','review') NOT NULL,
  `provider`      VARCHAR(50)     NULL,               -- ex.: 'gemini', 'nano-banana'
  `cost`          DECIMAL(12,6)   NULL,               -- custo da execução (testes-e-observabilidade §95)
  `status`        ENUM('QUEUED','RUNNING','SUCCESS','FAILED','RETRYING') NOT NULL DEFAULT 'QUEUED',
  `queue_job_id`  VARCHAR(191)    NULL,               -- job na fila Redis (§26, ADR-006)
  `queued_at`     TIMESTAMP       NULL,
  `started_at`    TIMESTAMP       NULL,
  `finished_at`   TIMESTAMP       NULL,
  `retry_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- política de retry (§96): até 3
  `error_message` TEXT            NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_executions_article_step` (`article_id`,`step`),
  KEY `idx_ai_executions_status` (`status`),
  CONSTRAINT `fk_ai_executions_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- [§87] knowledge_sources — registro interno das fontes do NotebookLM por site.
--     Integração FUTURA (integracoes §40) — tabela criada agora só para o registro interno (§40.8).
CREATE TABLE IF NOT EXISTS `knowledge_sources` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       BIGINT UNSIGNED NOT NULL,
  `notebook_id`   VARCHAR(191)    NULL,
  `source_id`     VARCHAR(191)    NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `type`          VARCHAR(50)     NULL,               -- pdf | url | doc | ...
  `reference_url` VARCHAR(1024)   NULL,
  `status`        VARCHAR(30)     NOT NULL DEFAULT 'PENDING',
  `added_by`      BIGINT UNSIGNED NULL,               -- users.id (responsável, §40.8)
  `added_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_sources_site` (`site_id`),
  CONSTRAINT `fk_knowledge_sources_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_knowledge_sources_user` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =============================================================================
-- FIM DA MIGRATION
-- =============================================================================
-- Tabelas criadas: 19
--   users, sites, user_site, site_authors, site_wordpress_connections,
--   categories, tags, editorial_rules, goals, goal_categories,
--   articles, article_versions, article_tags, article_sources, feedback,
--   images, schedules, ai_executions, knowledge_sources
--
-- -----------------------------------------------------------------------------
-- PENDÊNCIAS / TABELAS CANDIDATAS — decisão do responsável antes de criar:
-- -----------------------------------------------------------------------------
-- 1. article_lineages (ou content_slots) — hoje `articles.lineage_id` é só uma
--    coluna agrupadora, sem FK. A §29 fala em "3 tentativas por linhagem" e
--    "2 linhagens completas -> BLOCKED", o que sugere modelar Slot -> Linhagem ->
--    Artigo. Fica como decisão de design (não incluído para não inventar
--    estrutura fora da §87).
--
-- 2. editorial_memory — "memória editorial = preferências aprendidas do histórico"
--    (glossário; roadmap Fase 6). Ainda não há tabela nem formato definido.
--
-- 3. site_ai_budgets — limite de custo de IA por site/mês (§95). O MÉTODO de
--    cálculo existe, mas o VALOR numérico é decisão pendente registrada — sem
--    isso a tabela não tem forma final.
--
-- 4. Autenticação: NÃO há tabela de sessão/token — a proposta (§64.2) é sessão
--    nativa do PHP (arquivo/Redis), sem JWT nem "lembrar-me". Nada a criar aqui.
--
-- 5. `articles.status` pode ganhar um estado `ERROR` (§96). Isso é ALTER TABLE
--    futuro e passa pela §55.
-- =============================================================================
