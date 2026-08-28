-- =============================================================================
-- Migration 0002 — ai_executions.step: adiciona 'planning' e 'compliance'
-- =============================================================================
-- Contexto: o fluxo de produção da IA (docs/editorial/fluxo-editorial.md §21) e o
--   PromptBuilder (src/Services/PromptBuilder.php) têm os passos planning, research,
--   writing, seo, compliance, review. O ENUM original da coluna `step` (migration
--   0001) só cobria research/writing/seo/image/review.
-- Mudança: aditiva. Nenhum valor removido, nenhuma linha afetada (tabela vazia).
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 4.3.
-- =============================================================================

ALTER TABLE `ai_executions`
  MODIFY COLUMN `step`
  ENUM('planning','research','writing','seo','compliance','image','review') NOT NULL;
