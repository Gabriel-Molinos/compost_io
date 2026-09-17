-- =============================================================================
-- Migration 0024 — platform_feedback (feedback geral sobre o COMPOST)
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-17) — uma seção pro Redator-Chefe
--   avaliar como o COMPOST em si está indo (não o conteúdo gerado, não pra
--   "ensinar a IA") — o que percebeu, o que está bom, o que trava — pra
--   admin e Claude Code melhorarem a plataforma. Sem tabela nova até aqui
--   pra isso; virava mensagem solta em outro canal, sem histórico.
-- Mudança: tabela nova, 100% aditiva. Qualquer usuário logado pode enviar
--   (ADMIN também, embora o caso de uso principal seja o Redator-Chefe
--   avaliando pro ADMIN ver). `rating` é opcional (1-5, avaliação geral
--   rápida) — nem todo feedback é "nota", às vezes é só um relato solto.
--   `site_id` opcional — o feedback pode ser sobre a plataforma inteira ou
--   sobre a experiência num site específico. `reviewed_at`/`reviewed_by`
--   marcam que um ADMIN já leu (mesma ideia de `read_at` de notifications,
--   mas aqui é o ADMIN "dando baixa", nunca quem escreveu).
-- =============================================================================

CREATE TABLE `platform_feedback` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `site_id`     BIGINT UNSIGNED NULL,
  `rating`      TINYINT UNSIGNED NULL,
  `message`     TEXT NOT NULL,
  `reviewed_at` TIMESTAMP NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform_feedback_created` (`created_at`),
  KEY `idx_platform_feedback_reviewed` (`reviewed_at`),
  CONSTRAINT `fk_platform_feedback_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_platform_feedback_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_platform_feedback_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_platform_feedback_rating` CHECK (`rating` IS NULL OR (`rating` BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
