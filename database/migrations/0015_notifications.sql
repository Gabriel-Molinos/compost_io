-- =============================================================================
-- Migration 0015 — notifications (central de notificações)
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-08) — avisar o usuário quando algo
--   acontece sem ele estar olhando: post publicado com sucesso, post que
--   falhou ao publicar, admin associou ele a um novo site, artigo que
--   precisa de atenção humana (BLOCKED/ERROR). Sem tabela nova até aqui pra
--   isso — o único jeito de saber era abrir cada site e conferir.
-- Mudança: tabela nova, 100% aditiva. `user_id` é o destinatário (uma
--   notificação por pessoa — quando vários precisam saber, ex. toda a
--   equipe de um site, entram várias linhas, uma por usuário, nunca uma
--   linha "compartilhada" — mais simples de consultar/marcar como lida por
--   pessoa). `site_id` opcional (nem toda notificação é de um site
--   específico). `link` é o caminho pra abrir ao clicar (ex. a tela do
--   artigo) — opcional, algumas notificações não levam a lugar nenhum.
--   `read_at` NULL = não lida.
-- =============================================================================

CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(40) NOT NULL,
  `title`      VARCHAR(191) NOT NULL,
  `message`    VARCHAR(500) NOT NULL,
  `site_id`    BIGINT UNSIGNED NULL,
  `link`       VARCHAR(255) NULL,
  `read_at`    TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_unread` (`user_id`, `read_at`),
  KEY `idx_notifications_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
