-- ============================================================
--  SANK — Migracija 02: moduli (zalihe, dobavljači, fakture, troškovi)
--  Za POSTOJEĆE baze koje su već imale osnovnu šemu.
--  Nove instalacije dobijaju sve iz schema.sql.
-- ============================================================

ALTER TABLE `artikli`
  ADD COLUMN IF NOT EXISTS `zaliha`     DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER `prodajna_cena`,
  ADD COLUMN IF NOT EXISTS `min_zaliha` DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER `zaliha`;

CREATE TABLE IF NOT EXISTS `dobavljaci` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`  INT UNSIGNED NOT NULL,
  `naziv`     VARCHAR(150) NOT NULL,
  `pib`       VARCHAR(20)  DEFAULT NULL,
  `telefon`   VARCHAR(50)  DEFAULT NULL,
  `email`     VARCHAR(150) DEFAULT NULL,
  `adresa`    VARCHAR(200) DEFAULT NULL,
  `napomena`  VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dob_lokal` (`lokal_id`),
  CONSTRAINT `fk_dob_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fakture` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`     INT UNSIGNED NOT NULL,
  `dobavljac_id` INT UNSIGNED DEFAULT NULL,
  `broj`         VARCHAR(60)  NOT NULL,
  `datum`        DATE NOT NULL,
  `rok_placanja` DATE DEFAULT NULL,
  `iznos`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `placeno`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('neplacena','delimicno','placena') NOT NULL DEFAULT 'neplacena',
  `napomena`     VARCHAR(255) DEFAULT NULL,
  `korisnik_id`  INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_fakt_lokal` (`lokal_id`, `datum`),
  KEY `ix_fakt_dob` (`dobavljac_id`),
  CONSTRAINT `fk_fakt_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fakt_dob` FOREIGN KEY (`dobavljac_id`)
      REFERENCES `dobavljaci` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faktura_stavke` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `faktura_id`  INT UNSIGNED NOT NULL,
  `artikal_id`  INT UNSIGNED DEFAULT NULL,
  `naziv`       VARCHAR(150) NOT NULL,
  `jedinica_mere` VARCHAR(20) NOT NULL DEFAULT 'kom',
  `kolicina`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `cena`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `iznos`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_stavka_fakt` (`faktura_id`),
  KEY `ix_stavka_art` (`artikal_id`),
  CONSTRAINT `fk_stavka_fakt` FOREIGN KEY (`faktura_id`)
      REFERENCES `fakture` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_art` FOREIGN KEY (`artikal_id`)
      REFERENCES `artikli` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zalihe_promet` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED NOT NULL,
  `tip`        ENUM('ulaz','izlaz','korekcija') NOT NULL,
  `kolicina`   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `razlog`     VARCHAR(120) DEFAULT NULL,
  `faktura_id` INT UNSIGNED DEFAULT NULL,
  `korisnik_id` INT UNSIGNED DEFAULT NULL,
  `datum`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `napomena`   VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_zp_lokal` (`lokal_id`),
  KEY `ix_zp_art` (`artikal_id`),
  CONSTRAINT `fk_zp_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_zp_art` FOREIGN KEY (`artikal_id`)
      REFERENCES `artikli` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `troskovi` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`      INT UNSIGNED NOT NULL,
  `kategorija`    ENUM('struja','voda','internet','telefon','zakup','plate','doprinosi','namirnice','oprema','porez','marketing','ostalo') NOT NULL DEFAULT 'ostalo',
  `naziv`         VARCHAR(150) NOT NULL,
  `iznos`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `datum`         DATE NOT NULL,
  `rok_placanja`  DATE DEFAULT NULL,
  `status`        ENUM('neplacen','placen') NOT NULL DEFAULT 'neplacen',
  `datum_placanja` DATE DEFAULT NULL,
  `ponavljajuci`  TINYINT(1) NOT NULL DEFAULT 0,
  `napomena`      VARCHAR(255) DEFAULT NULL,
  `korisnik_id`   INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_trosak_lokal` (`lokal_id`, `datum`),
  CONSTRAINT `fk_trosak_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
