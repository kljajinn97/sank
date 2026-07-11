-- ============================================================
--  SANK — Migracija 10: sigurnost (audit log, razlog storna)
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED DEFAULT NULL,
  `korisnik_id` INT UNSIGNED DEFAULT NULL,
  `korisnik_ime` VARCHAR(160) DEFAULT NULL,   -- keš imena (ostaje i ako se nalog obriše)
  `radnja`      VARCHAR(40) NOT NULL,          -- naplata, storno, brisanje, izmena_cene, popust, uklonjena_stavka, status...
  `entitet`     VARCHAR(40) DEFAULT NULL,      -- racun, faktura, trosak, artikal, korisnik...
  `entitet_id`  INT UNSIGNED DEFAULT NULL,
  `detalji`     VARCHAR(255) DEFAULT NULL,
  `ip`          VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_lokal` (`lokal_id`, `created_at`),
  KEY `ix_audit_radnja` (`radnja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `racuni`
  ADD COLUMN IF NOT EXISTS `storno_razlog` VARCHAR(255) DEFAULT NULL AFTER `napomena`;
