-- ============================================================
--  SANK — Migracija 16: QR digitalni meni
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `javni_token`  CHAR(16) DEFAULT NULL AFTER `logo`,
  ADD COLUMN IF NOT EXISTS `meni_aktivan` TINYINT(1) NOT NULL DEFAULT 0 AFTER `javni_token`;

ALTER TABLE `artikli`
  ADD COLUMN IF NOT EXISTS `opis` VARCHAR(255) DEFAULT NULL AFTER `naziv`;
