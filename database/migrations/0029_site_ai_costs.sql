-- =============================================================================
-- Migration 0029 — nova tabela site_ai_costs
-- =============================================================================
-- Contexto: achado real 2026-09-28 — o orçamento de IA mostrado na Visão Geral
--   (`CostBudgetService`/`ReportService::currentSpend()`/`monthly()`) soma só
--   `ai_executions` (sempre ligado a um artigo). Duas chamadas pagas ao Gemini
--   ficavam de fora dessa soma, então o gasto real do site era maior do que o
--   número exibido:
--     - Centro de Inteligência Editorial (Fase 8.3, `IntelligenceService`) —
--       já registrava custo em `editorial_insights.cost`, mas nada somava isso
--       no orçamento;
--     - Sugestão automática de identidade editorial na 1ª conexão WordPress
--       (`EditorialIdentityAnalysisService`, 2026-09-28) — não registrava custo
--       em lugar nenhum.
-- Em vez de forçar essas chamadas em `ai_executions` (article_id é NOT NULL lá,
--   e nenhuma das duas tem artigo), esta tabela é um livro-razão simples de
--   custo de IA por site NÃO ligado a artigo. `ReportService` soma as duas
--   fontes; `editorial_insights.cost` continua existindo (é o custo daquela
--   análise específica, pro histórico da aba Inteligência) — esta tabela é só
--   pra entrar na soma do orçamento.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `site_ai_costs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    BIGINT UNSIGNED NOT NULL,
  `source`     VARCHAR(50)     NOT NULL,  -- 'intelligence_insight' | 'editorial_identity_suggestion'
  `cost`       DECIMAL(12,6)   NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_ai_costs_site_created` (`site_id`, `created_at`),
  CONSTRAINT `fk_site_ai_costs_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
