-- =============================================================================
-- Migration 0010 — articles.status ganha o valor 'ERROR'
-- =============================================================================
-- Contexto: Fase 9.1b (fila conectada ao pipeline editorial). Rodando via
--   worker (sem sessão HTTP pra flash de erro), uma falha técnica definitiva
--   (retries esgotados) precisa de um estado visível e persistente no artigo —
--   já previsto em testes-e-observabilidade.md §96 e schema.md §87.2.
-- Mudança: 100% aditiva no ENUM. O motivo do erro não ganha coluna nova —
--   vem de `ai_executions.error_message` do passo que falhou (já existe).
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 9.1b.
-- =============================================================================

ALTER TABLE `articles`
  MODIFY COLUMN `status` ENUM(
    'PLANNED','IN_PROGRESS','IN_REVIEW','REVISION_REQUESTED',
    'APPROVED','SCHEDULED','PUBLISHED','DISCARDED','BLOCKED','ERROR'
  ) NOT NULL DEFAULT 'PLANNED';
