-- =============================================================================
-- Migration 0023 — sites.logo_library_filename
-- =============================================================================
-- Contexto: seletor visual da biblioteca de logos (SiteLogoLibraryService,
--   migration 0022-adjacent) deixava escolher a MESMA logo pra dois sites
--   diferentes — achado real 2026-09-15: "gavsy e penazo já existem, mas
--   consegui criar outro site com a logo do gavsy". `logo_path` guarda o
--   arquivo já processado/salvo (WebP), sem ligação de volta pro arquivo de
--   origem da biblioteca — sem essa coluna, não dava pra saber "essa logo já
--   está ocupada" na hora de montar a lista pro seletor.
-- Mudança: 100% aditiva, nullable (logo enviado por upload manual, sem
--   passar pela biblioteca, continua com isto NULL).
-- =============================================================================

ALTER TABLE `sites`
  ADD COLUMN `logo_library_filename` VARCHAR(255) NULL AFTER `logo_path`;
