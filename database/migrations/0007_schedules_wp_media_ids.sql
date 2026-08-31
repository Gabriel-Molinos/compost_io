-- =============================================================================
-- Migration 0007 — schedules.wp_media_ids
-- =============================================================================
-- Contexto: Fase 7.5 (publicação). Ao enviar o post, a aplicação faz upload da
--   imagem destacada e das imagens de corpo para a media library do WordPress.
--   Para poder "Atualizar no WordPress" ou "Retirar do WordPress" sem deixar
--   mídia órfã, guardamos os IDs de mídia que este agendamento criou lá.
-- Mudança: 100% aditiva. Coluna nullable, JSON com uma lista de inteiros
--   (ex.: [123, 124, 125]); nula = nada enviado ainda.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 7.5.
-- =============================================================================

ALTER TABLE `schedules`
  ADD COLUMN `wp_media_ids` VARCHAR(500) NULL AFTER `wordpress_post_id`;
