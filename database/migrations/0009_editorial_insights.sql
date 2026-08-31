-- =============================================================================
-- Migration 0009 — editorial_insights (Centro de Inteligência Editorial)
-- =============================================================================
-- Contexto: Fase 8.3 (RF-014, fluxo-editorial §33). O Centro de Inteligência
--   responde às seis perguntas de análise (o que funciona / o que dá errado /
--   o que melhorou / o que não melhorou / o que a IA aprendeu / o que ajustar).
--   A análise é NARRATIVA e gerada sob demanda por uma chamada ao Gemini —
--   diferente do relatório mensal (8.1/8.2), que é só agregação de SQL.
-- Mudança: 100% aditiva. Guarda o histórico de análises por site; a tela mostra
--   a mais recente. `content` é o JSON retornado pelo Gemini (as seis respostas).
--   `cost`/tokens espelham `ai_executions` mas sem FK a `articles` (não há artigo).
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 8.3.
-- =============================================================================

CREATE TABLE `editorial_insights` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       BIGINT UNSIGNED NOT NULL,
  `content`       JSON NOT NULL,
  `model`         VARCHAR(100) NOT NULL,
  `cost`          DECIMAL(10,6) NOT NULL DEFAULT 0,
  `prompt_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `output_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `generated_by`  BIGINT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_editorial_insights_site` (`site_id`, `created_at`),
  CONSTRAINT `fk_editorial_insights_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_editorial_insights_user`
    FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
