-- ============================================================
--  SANK — Migracija 06: vizuelni artikli (slika + boja) i boje kategorija
-- ============================================================

ALTER TABLE `artikli`
  ADD COLUMN IF NOT EXISTS `slika` MEDIUMTEXT DEFAULT NULL AFTER `min_zaliha`,
  ADD COLUMN IF NOT EXISTS `boja`  VARCHAR(9) DEFAULT NULL AFTER `slika`;

ALTER TABLE `kategorije`
  ADD COLUMN IF NOT EXISTS `boja` VARCHAR(9) NOT NULL DEFAULT '#0d9488' AFTER `naziv`;
