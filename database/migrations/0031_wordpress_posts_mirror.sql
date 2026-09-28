-- =============================================================================
-- Migration 0031 — nova tabela wordpress_posts_mirror
-- =============================================================================
-- Contexto: pedido do responsável 2026-09-28 — o chefe quer TUDO pelo COMPOST,
--   pro redator nunca precisar logar no wp-admin (menos exposição — já houve
--   invasão em site antes) e pra ter uma cópia local de todo o conteúdo do
--   WordPress (inclusive posts feitos direto lá, sem passar pelo COMPOST) —
--   se o site for comprometido de novo, o conteúdo não se perde.
-- Espelho de TODOS os posts do WordPress conectado (`WordPressSyncService::
--   syncPostsMirror()`), COMPOST ou não — `article_id` liga com o artigo local
--   quando existe (via `schedules.wordpress_post_id`), fica NULL quando o post
--   foi criado direto no WordPress. `content` guarda uma cópia de verdade do
--   HTML publicado (`content.rendered`) — é o backup em si, não só metadado.
-- Mudança: tabela nova, sem impacto em nada existente.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `wordpress_posts_mirror` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`                  BIGINT UNSIGNED NOT NULL,
  `wordpress_post_id`        BIGINT UNSIGNED NOT NULL,
  `article_id`               BIGINT UNSIGNED NULL,       -- NULL = criado direto no WordPress, não pelo COMPOST
  `title`                    VARCHAR(500)    NOT NULL,
  `slug`                     VARCHAR(255)    NULL,
  `link`                     VARCHAR(1024)   NOT NULL,
  `status`                   VARCHAR(20)     NOT NULL,    -- publish | future | draft | pending | private
  `excerpt`                  TEXT            NULL,
  `content`                  LONGTEXT        NULL,        -- cópia local do HTML publicado — o backup em si
  `featured_image_url`       VARCHAR(1024)   NULL,
  `wordpress_author_name`    VARCHAR(191)    NULL,
  `wordpress_category_names` VARCHAR(500)    NULL,        -- só pra exibir na lista; lista separada por vírgula
  `wordpress_published_at`   DATETIME        NULL,
  `wordpress_modified_at`    DATETIME        NULL,
  `last_synced_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp_posts_mirror_site_wpid` (`site_id`, `wordpress_post_id`),
  KEY `idx_wp_posts_mirror_article` (`article_id`),
  CONSTRAINT `fk_wp_posts_mirror_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wp_posts_mirror_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
