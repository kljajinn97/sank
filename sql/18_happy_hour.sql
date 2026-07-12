-- ============================================================
--  SANK — Migracija 18: Happy hour (vremenski popust)
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `hh_aktivan` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdv_obveznik`,
  ADD COLUMN IF NOT EXISTS `hh_popust`  DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `hh_aktivan`,
  ADD COLUMN IF NOT EXISTS `hh_od`      TIME DEFAULT NULL AFTER `hh_popust`,
  ADD COLUMN IF NOT EXISTS `hh_do`      TIME DEFAULT NULL AFTER `hh_od`,
  ADD COLUMN IF NOT EXISTS `hh_dani`    VARCHAR(20) DEFAULT NULL AFTER `hh_do`;
