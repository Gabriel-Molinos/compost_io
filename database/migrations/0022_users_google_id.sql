-- =============================================================================
-- Migration 0022 — users.google_id (Login com Google)
-- =============================================================================
-- Contexto: "Login com Google" como segunda opção de entrada, além de
--   e-mail+senha (docs/technical/requisitos.md §64.2). Vincula a conta Google
--   (sub do ID token) a um usuário já existente e ativo — sem auto-cadastro.
-- Mudança: 100% aditiva. Coluna nullable (usuário que nunca usou "Entrar com
--   Google" continua com google_id = NULL). UNIQUE permite múltiplos NULL no
--   MySQL sem colidir; impede a mesma conta Google ser vinculada a dois
--   usuários diferentes.
-- =============================================================================

ALTER TABLE `users`
  ADD COLUMN `google_id` VARCHAR(255) NULL AFTER `avatar_path`,
  ADD UNIQUE KEY `uq_users_google_id` (`google_id`);
