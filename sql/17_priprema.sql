-- ============================================================
--  SANK — Migracija 17: nalog za pripremu (kuhinja/šank)
-- ============================================================

ALTER TABLE `racun_stavke`
  ADD COLUMN IF NOT EXISTS `poslato` TINYINT(1) NOT NULL DEFAULT 0 AFTER `napomena`;
