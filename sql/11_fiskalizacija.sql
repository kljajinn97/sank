-- ============================================================
--  SANK — Migracija 11: fiskalizacija (ESIR integracioni sloj)
--  Podrazumevano ISKLJUČENO. Simulacioni režim za test; L-PFR/V-PFR
--  drajver spreman ali zahteva pravi PFR + bezbednosni element + odobrenje ESIR.
-- ============================================================

ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `fisk_aktivna`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `logo`,
  ADD COLUMN IF NOT EXISTS `fisk_mode`     ENUM('simulacija','lpfr','vpfr') NOT NULL DEFAULT 'simulacija' AFTER `fisk_aktivna`,
  ADD COLUMN IF NOT EXISTS `pfr_url`       VARCHAR(255) DEFAULT NULL AFTER `fisk_mode`,
  ADD COLUMN IF NOT EXISTS `esir_broj`     VARCHAR(40)  DEFAULT NULL AFTER `pfr_url`,
  ADD COLUMN IF NOT EXISTS `pdv_obveznik`  TINYINT(1) NOT NULL DEFAULT 1 AFTER `esir_broj`;

-- Poreska oznaka artikla (Ђ=20%, Е=10%, А=0%/oslobođeno — mapiranje daje PFR)
ALTER TABLE `artikli`
  ADD COLUMN IF NOT EXISTS `poreska_oznaka` VARCHAR(4) NOT NULL DEFAULT 'Ђ' AFTER `boja`;

-- Fiskalni podaci po računu
ALTER TABLE `racuni`
  ADD COLUMN IF NOT EXISTS `fiskalizovan` TINYINT(1) NOT NULL DEFAULT 0 AFTER `nacin_placanja`,
  ADD COLUMN IF NOT EXISTS `pfr_broj`     VARCHAR(60)  DEFAULT NULL AFTER `fiskalizovan`,
  ADD COLUMN IF NOT EXISTS `pfr_brojac`   VARCHAR(40)  DEFAULT NULL AFTER `pfr_broj`,
  ADD COLUMN IF NOT EXISTS `pfr_vreme`    DATETIME     DEFAULT NULL AFTER `pfr_brojac`,
  ADD COLUMN IF NOT EXISTS `pfr_qr`       MEDIUMTEXT   DEFAULT NULL AFTER `pfr_vreme`,
  ADD COLUMN IF NOT EXISTS `pfr_url_ver`  VARCHAR(255) DEFAULT NULL AFTER `pfr_qr`;
