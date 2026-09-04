-- =============================================================================
-- Migration 0014 — articles.source (geração manual × automática)
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-04) — a IA passa a gerar 1
--   rascunho por dia sozinha, por site ativo, além do botão manual "Gerar
--   rascunho" que continua existindo. Sem essa coluna não dá pra saber
--   "esse site já gerou hoje?" (o botão manual não conta pra essa checagem).
-- Mudança: 100% aditiva. Default 'MANUAL' cobre todo artigo já existente e
--   toda geração manual futura sem precisar tocar em nenhuma chamada atual.
-- Autorizado pelo responsável (regras-claude-code.md §55).
-- =============================================================================

ALTER TABLE `articles`
  ADD COLUMN `source` ENUM('MANUAL','AUTO') NOT NULL DEFAULT 'MANUAL' AFTER `attempt_number`;
