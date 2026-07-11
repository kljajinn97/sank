-- ============================================================
--  SANK — Migracija 15: napomena po stavci računa
-- ============================================================

ALTER TABLE `racun_stavke`
  ADD COLUMN IF NOT EXISTS `napomena` VARCHAR(255) DEFAULT NULL AFTER `iznos`;
