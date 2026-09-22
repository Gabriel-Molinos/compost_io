-- =============================================================================
-- Migration 0026 — ai_executions.step + 'research_gap_hints'
-- =============================================================================
-- Contexto: redesign da página Fontes (2026-09-22) — botão "Sugerir buscas"
--   por lacuna de pesquisa (`ResearchGapHintService`), mesmo padrão de ação
--   sob demanda já usado por 'external_link_suggestions'/'internal_link_
--   suggestions' (migrations 0019/0020): 1 chamada de IA rastreada em
--   ai_executions, fora do fluxo automático do pipeline.
-- Mudança: 100% aditiva — só acrescenta um valor ao ENUM, nenhuma linha
--   existente é afetada.
-- =============================================================================

ALTER TABLE `ai_executions`
  MODIFY COLUMN `step`
  ENUM('planning', 'research', 'writing', 'seo', 'compliance', 'image', 'review', 'backlink_suggestions', 'external_link_suggestions', 'internal_link_suggestions', 'research_gap_hints') NOT NULL;
