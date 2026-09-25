-- =============================================================================
-- Migration 0027 — articles.writer_request
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-24) — botão "Rascunho específico" na
--   Produção: o redator escolhe a categoria e descreve, com as próprias
--   palavras, o post que quer. Esse pedido precisa ficar guardado NO artigo
--   (não só no job): a regeneração após rejeição (nova tentativa da mesma
--   linhagem) tem que continuar seguindo o mesmo pedido, e quem revisa precisa
--   ver o que foi pedido pra conferir se o texto atendeu.
-- Mudança: 100% aditiva, nullable. NULL = rascunho comum (a IA escolhe o tema).
-- =============================================================================

ALTER TABLE `articles`
  ADD COLUMN `writer_request` TEXT NULL AFTER `source`;
