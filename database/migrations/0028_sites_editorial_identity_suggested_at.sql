-- =============================================================================
-- Migration 0028 — sites.editorial_identity_suggested_at
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-28) — ao conectar o WordPress pela
--   primeira vez, a dash analisa os posts publicados de verdade e sugere
--   nicho/público-alvo/tom/identidade editorial (só quando o site ainda está
--   com esses campos vazios, e só se o WordPress já tiver posts o bastante —
--   site novo/vazio não gera sugestão nenhuma). Continua 100% editável: é só
--   um pré-preenchimento, quem decide é sempre o Redator-Chefe/Admin.
-- Esta coluna marca "isto ainda é sugestão da IA, não confirmado por humano" —
--   a tela de configurações mostra um aviso enquanto ela não for NULL, e
--   qualquer salvamento manual do formulário (SiteController::update) limpa
--   a marca (`update()` já grava NULL nela — ver App\Services\SiteService).
-- Mudança: 100% aditiva, nullable.
-- =============================================================================

ALTER TABLE `sites`
  ADD COLUMN `editorial_identity_suggested_at` DATETIME NULL AFTER `editorial_identity`;
