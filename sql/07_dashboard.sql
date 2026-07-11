-- ============================================================
--  SANK — Migracija 07: prilagodljiva kontrolna tabla (po korisniku)
-- ============================================================

ALTER TABLE `korisnici`
  ADD COLUMN IF NOT EXISTS `dashboard_config` TEXT DEFAULT NULL AFTER `last_login`;
