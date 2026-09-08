-- =============================================================================
-- Migration 0016 — schedules.created_by (quem agendou/publicou)
-- =============================================================================
-- Contexto: pedido do responsável (2026-09-08) — as notificações de
--   publicação (post deu certo/deu errado) estavam avisando TODA a equipe
--   do site (ADMINs + Redator-Chefe vinculado), e ele quer que avise só
--   quem de fato colocou aquele post pra rodar. Pro clique manual dava pra
--   usar o usuário da sessão HTTP na hora — mas a publicação AUTOMÁTICA
--   (bin/worker.php, varredura de agendamentos vencidos) roda sem nenhum
--   usuário logado, então precisa persistir quem agendou pra saber quem
--   avisar quando o worker publicar sozinho depois.
-- Mudança: 100% aditiva, coluna NULL (agendamentos já existentes não têm
--   essa informação — cai no fallback de notificar a equipe inteira,
--   ver ScheduleJobHandlers).
-- =============================================================================

ALTER TABLE `schedules`
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL AFTER `author_id`,
  ADD CONSTRAINT `fk_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
