-- ============================================================
--  SANK — Migracija 19: auto-štampa računa posle naplate
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `auto_stampa` TINYINT(1) NOT NULL DEFAULT 0 AFTER `hh_dani`;
