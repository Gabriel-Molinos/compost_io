-- =============================================================================
-- Migration 0004 — sites.editorial_identity
-- =============================================================================
-- Contexto: fluxo-editorial §15 ("Identidade editorial") e §17 ("REGRAS DO SITE")
--   pedem uma descrição de voz/estilo por site, mais rica que a coluna `tone`
--   (VARCHAR 100). É a camada "Identidade do Site" do PromptBuilder (§22).
-- Mudança: aditiva, coluna nullable. Autorizada (regras-claude-code.md §55) — Fase 4 (fechamento).
-- =============================================================================

ALTER TABLE `sites`
  ADD COLUMN `editorial_identity` TEXT NULL AFTER `tone`
