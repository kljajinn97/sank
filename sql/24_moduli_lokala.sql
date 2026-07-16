-- ============================================================
--  WAITER — Migracija 24: moduli po lokalu (uključivanje na klik)
--  NULL = svi moduli uključeni (podrazumevano za postojeće lokale)
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `moduli` TEXT DEFAULT NULL AFTER `meni_aktivan`;
