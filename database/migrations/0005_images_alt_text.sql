-- =============================================================================
-- Migration 0005 — images.alt_text + passo 'image' em article_ai_notes
-- =============================================================================
-- Contexto: Fase 5 (Imagens). O passo `image` (docs/ai/image.md) produz o brief
--   visual e o alt text de cada imagem (SEO on-page, docs/editorial/seo.md#imagens).
--   A tabela `images` (migration 0001) não tinha onde guardar o alt text, e o ENUM
--   de `article_ai_notes.step` (migration 0003) não cobria `image` — o pipeline
--   grava o parecer de todos os passos ali.
-- Mudança: 100% aditiva. `images.alt_text` nullable; +1 valor no ENUM. Nenhuma
--   linha afetada, nenhum valor removido.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 5.3.
-- =============================================================================

ALTER TABLE `images`
  ADD COLUMN `alt_text` VARCHAR(500) NULL AFTER `role`;

ALTER TABLE `article_ai_notes`
  MODIFY COLUMN `step`
  ENUM('planning','research','writing','seo','compliance','image','review') NOT NULL;
