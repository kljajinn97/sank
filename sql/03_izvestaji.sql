-- ============================================================
--  SANK — Migracija 03: podela pazara na keš/karticu (za izveštaj blagajne)
-- ============================================================

ALTER TABLE `pazar`
  ADD COLUMN IF NOT EXISTS `kes`     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `iznos`,
  ADD COLUMN IF NOT EXISTS `kartica` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `kes`;
