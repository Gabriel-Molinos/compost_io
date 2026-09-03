-- =============================================================================
-- Migration 0012 — editorial_memory (memória editorial curada)
-- =============================================================================
-- Contexto: pendência registrada desde a Fase 6.3 (schema.md §87 nota em
--   `editorial_rules`/`feedback`) e reaberta no fechamento da Fase 8/9. Até
--   aqui, a "memória editorial" injetada em todo prompt era só derivada em
--   tempo real do histórico (últimas 8 rejeições em `feedback` + últimos 10
--   artigos aprovados — ver `ArticlePipeline::siteMemoryContext()`), sem
--   nenhuma curadoria: some da janela assim que fica velha o suficiente.
-- Mudança: tabela de "lições" duradouras por site, com curadoria SEMPRE
--   humana (Redator-Chefe/Admin) — nunca gerada sozinha pela IA (Regra de
--   não-invenção, regras-claude-code.md §58). Uma lição pode ser escrita do
--   zero ou promovida de um `feedback` existente (`source_feedback_id`,
--   opcional). `active` permite desativar sem apagar o histórico. Entra
--   SOMADA ao que já existia — não substitui o feedback bruto recente.
-- 100% aditiva.
-- =============================================================================

CREATE TABLE `editorial_memory` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`             BIGINT UNSIGNED NOT NULL,
  `lesson`              TEXT NOT NULL,
  `source_feedback_id`  BIGINT UNSIGNED NULL,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`          BIGINT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_editorial_memory_site_active` (`site_id`, `active`),
  CONSTRAINT `fk_editorial_memory_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_editorial_memory_feedback`
    FOREIGN KEY (`source_feedback_id`) REFERENCES `feedback` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_editorial_memory_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
