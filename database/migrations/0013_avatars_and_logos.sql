-- =============================================================================
-- Migration 0013 — foto de perfil (users) e logo (sites)
-- =============================================================================
-- Contexto: pedido do responsável na sessão de redesign visual (2026-09-04) —
--   os "chips com inicial" que substituíram usuários/sites na sidebar e na
--   tela de início ganham a opção de imagem real quando o usuário enviar uma.
-- Mudança: 100% aditiva, colunas NULL (nem todo usuário/site vai ter foto).
--   Guarda só o caminho público relativo (ex.: "assets/uploads/avatars/7.webp"),
--   nunca o binário no banco — o arquivo fica em public/ (App\Support\Uploads).
-- Autorizado pelo responsável (regras-claude-code.md §55).
-- =============================================================================

ALTER TABLE `users`
  ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `role`;

ALTER TABLE `sites`
  ADD COLUMN `logo_path` VARCHAR(255) NULL AFTER `name`;
