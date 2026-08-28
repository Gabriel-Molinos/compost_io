-- =============================================================================
-- Migration 0003 — article_ai_notes: pareceres de cada passo da IA
-- =============================================================================
-- Contexto: os passos do pipeline (docs/technical/fila-ia.md, fluxo-editorial §21)
--   produzem JSON estruturado. Título/palavra-chave/fontes/corpo já têm colunas;
--   o restante (ângulo, rationale, lacunas da pesquisa, e principalmente os
--   pareceres de seo/compliance/review — Fase 4.4b) não tinha onde ficar.
-- Uma linha por (artigo, passo), payload = JSON da resposta daquele passo.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 4.4b.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `article_ai_notes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `step`       ENUM('planning','research','writing','seo','compliance','review') NOT NULL,
  `payload`    JSON            NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_ai_notes` (`article_id`,`step`),
  CONSTRAINT `fk_article_ai_notes_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
