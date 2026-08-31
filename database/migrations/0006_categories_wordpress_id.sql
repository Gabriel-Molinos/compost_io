-- =============================================================================
-- Migration 0006 — categories.wordpress_category_id
-- =============================================================================
-- Contexto: Fase 7.3 (sincronizar autores + categorias). Ao publicar um artigo
--   no WordPress (§31, integracoes.md §37) o post precisa referenciar o ID
--   numérico da categoria no WP. A categoria local (migration 0001) só tinha
--   nome/diretrizes. A sincronização casa por nome e grava aqui o ID do WP.
-- Mudança: 100% aditiva. Coluna nullable (categoria ainda não sincronizada = NULL).
--   UNIQUE (site_id, wordpress_category_id) impede dois vínculos para a mesma
--   categoria do WP no mesmo site; NULLs não colidem no MySQL.
-- Autorizado pelo responsável (regras-claude-code.md §55) — Fase 7.3.
-- =============================================================================

ALTER TABLE `categories`
  ADD COLUMN `wordpress_category_id` BIGINT UNSIGNED NULL AFTER `guidelines`,
  ADD UNIQUE KEY `uq_categories_wp` (`site_id`, `wordpress_category_id`);
