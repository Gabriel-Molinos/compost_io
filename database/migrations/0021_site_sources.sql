-- Fontes confiáveis cadastradas pelo redator, por site — pool prioritário que
-- o passo "research" consulta antes de recorrer só à memória de treinamento
-- da IA (docs/ai/research.md, seção "REGRA CRÍTICA — NUNCA INVENTAR URL").
CREATE TABLE IF NOT EXISTS `site_sources` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    BIGINT UNSIGNED NOT NULL,
  `url`        VARCHAR(2048)   NOT NULL,
  `note`       VARCHAR(500)    NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_sources_site` (`site_id`),
  CONSTRAINT `fk_site_sources_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
