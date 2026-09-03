-- =============================================================================
-- Migration 0011 — índices para os filtros de período dos relatórios
-- =============================================================================
-- Contexto: Fase 9 (Escala/performance). Levantamento mostrou que os filtros
--   de período mais usados (ReportService::monthly(), CostBudgetService) caem
--   em colunas sem índice — `articles.created_at`/`reviewed_at`,
--   `ai_executions.cost`/`created_at`, `feedback.created_at` — forçando scan
--   de todas as linhas do site (ou, no caso do custo máximo global, de todas
--   as linhas de `ai_executions` de todos os sites) a cada carregamento da
--   Visão Geral / Relatórios.
-- Mudança: 100% aditiva — só índices, nenhuma coluna/tabela nova.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 9 (perf).
-- =============================================================================

ALTER TABLE `articles`
  ADD INDEX `idx_articles_site_created` (`site_id`, `created_at`),
  ADD INDEX `idx_articles_site_reviewed` (`site_id`, `reviewed_at`);

ALTER TABLE `ai_executions`
  ADD INDEX `idx_ai_executions_cost` (`article_id`, `cost`),
  ADD INDEX `idx_ai_executions_created` (`created_at`);

ALTER TABLE `feedback`
  ADD INDEX `idx_feedback_created` (`created_at`);
