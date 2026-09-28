-- =============================================================================
-- Migration 0030 — images.sort_order
-- =============================================================================
-- Contexto: pedido do responsável 2026-09-28 — o Redator-Chefe poder escolher
--   e ver onde cada imagem de corpo vai ficar no artigo, em vez da distribuição
--   100% automática (ordem de geração) que existia até aqui (`BodyImageInjector`).
-- `sort_order` só é usado pra imagens de papel BODY (destacada não tem "posição
--   no corpo"). NULL = nunca reordenada manualmente, cai pra ordem de criação
--   (`id`) — 100% compatível com toda imagem já existente antes desta migration.
-- Mudança: 100% aditiva, nullable.
-- =============================================================================

ALTER TABLE `images`
  ADD COLUMN `sort_order` SMALLINT UNSIGNED NULL AFTER `role`;
