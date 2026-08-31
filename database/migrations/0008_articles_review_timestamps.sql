-- =============================================================================
-- Migration 0008 — articles.review_started_at / reviewed_at
-- =============================================================================
-- Contexto: Fase 8 (Inteligência Editorial). O relatório mensal (fluxo-editorial
--   §32) mostra "tempo médio de revisão" — quanto o Redator-Chefe levou entre o
--   artigo entrar em revisão e ser aprovado/rejeitado. A tabela `articles` só
--   tinha `created_at`/`updated_at` (este muda em qualquer alteração).
-- Mudança: 100% aditiva. Duas colunas nullable, preenchidas daqui pra frente
--   por `ArticleService::setStatus` (IN_REVIEW -> review_started_at; APPROVED /
--   REVISION_REQUESTED -> reviewed_at). Artigos anteriores ficam com NULL e não
--   entram na média.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 8.1.
-- =============================================================================

ALTER TABLE `articles`
  ADD COLUMN `review_started_at` TIMESTAMP NULL AFTER `status`,
  ADD COLUMN `reviewed_at`       TIMESTAMP NULL AFTER `review_started_at`;
