-- ============================================================
--  SANK — Migracija 05: brendiranje lokala (boja + logo)
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `boja` VARCHAR(9) NOT NULL DEFAULT '#0d9488' AFTER `pdv_stopa`,
  ADD COLUMN IF NOT EXISTS `logo` MEDIUMTEXT DEFAULT NULL AFTER `boja`;
