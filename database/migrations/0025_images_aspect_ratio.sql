-- =============================================================================
-- Migration 0025 — images.aspect_ratio
-- =============================================================================
-- Contexto: pedido explícito do Redator-Chefe (2026-09-22) — imagens de corpo
--   devem ser geradas sempre na MESMA proporção da imagem destacada escolhida,
--   e as novas ações manuais "Gerar mais uma" / "Substituir" (ArticlePipeline)
--   precisam saber qual proporção a destacada usou pra repetir nas próximas
--   chamadas ao gerador. `ImageRequest::ASPECT_RATIOS` já valida os valores
--   aceitos (ex. '16:9', '4:3') — só faltava onde guardar qual foi usado.
-- Mudança: 100% aditiva, nullable (imagens já existentes ficam sem essa
--   informação; o código trata `NULL` como "proporção desconhecida", caindo
--   no padrão 16:9 já usado hoje).
-- =============================================================================

ALTER TABLE `images`
  ADD COLUMN `aspect_ratio` VARCHAR(8) NULL AFTER `format`;
