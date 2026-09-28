-- =============================================================================
-- Migration 0032 — índice em wordpress_posts_mirror (site_id, wordpress_published_at)
-- =============================================================================
-- Contexto: achado real de performance 2026-09-28, ao investigar "o COMPOST tá
--   pesado" — testado na Gavsy (171 posts): a listagem de "Todos os posts"
--   ordena por `wordpress_published_at`, mas o único índice da tabela
--   (`uq_wp_posts_mirror_site_wpid`, migration 0031) é (site_id, wordpress_post_id)
--   — não cobre essa ordenação. `EXPLAIN` confirmou `Using filesort`. Combinado
--   com trazer a coluna `content` à toa (corrigido em `WordPressPostMirrorService`,
--   sem migration — só a query mudou), a consulta da lista chegava a ~2s.
-- Mudança: 100% aditiva, só índice.
-- =============================================================================

ALTER TABLE `wordpress_posts_mirror`
  ADD KEY `idx_wp_posts_mirror_site_published` (`site_id`, `wordpress_published_at`);
