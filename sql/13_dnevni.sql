-- ============================================================
--  SANK — Migracija 13: zatvaranje dana (KPO dnevni izveštaj)
-- ============================================================

CREATE TABLE IF NOT EXISTS `dnevni_izvestaji` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `datum`       DATE NOT NULL,
  `promet`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `kes`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `kartica`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `br_racuna`   INT NOT NULL DEFAULT 0,
  `napomena`    VARCHAR(255) DEFAULT NULL,
  `korisnik_id` INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dan` (`lokal_id`,`datum`),
  CONSTRAINT `fk_dan_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
