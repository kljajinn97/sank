-- ============================================================
--  SANK — Digitalna knjiga šanka
--  Šema baze podataka (MySQL / MariaDB)
--  Uvezi preko phpMyAdmin u bazu: kljajinc_sank
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  LOKALI (ugostiteljski objekti — tenant jedinica)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lokali` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `naziv`         VARCHAR(150) NOT NULL,
  `tip`           VARCHAR(60)  DEFAULT NULL,          -- kafić, restoran, bar...
  `adresa`        VARCHAR(200) DEFAULT NULL,
  `grad`          VARCHAR(100) DEFAULT NULL,
  `telefon`       VARCHAR(50)  DEFAULT NULL,
  `pib`           VARCHAR(20)  DEFAULT NULL,
  `valuta`        VARCHAR(10)  NOT NULL DEFAULT 'RSD',
  `status`        ENUM('aktivan','suspendovan') NOT NULL DEFAULT 'aktivan',
  `pretplata_do`  DATE         DEFAULT NULL,          -- do kada je plaćena pretplata
  `napomena`      TEXT         DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  KORISNICI (nalozi)
--  uloga: super_admin (ti) NEMA lokal_id (NULL) i vidi sve
--         vlasnik / menadzer / konobar pripadaju jednom lokalu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `korisnici` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`      INT UNSIGNED DEFAULT NULL,
  `ime`           VARCHAR(80)  NOT NULL,
  `prezime`       VARCHAR(80)  DEFAULT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `uloga`         ENUM('super_admin','vlasnik','menadzer','konobar') NOT NULL DEFAULT 'konobar',
  `status`        ENUM('aktivan','neaktivan') NOT NULL DEFAULT 'aktivan',
  `last_login`    DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `ix_korisnici_lokal` (`lokal_id`),
  CONSTRAINT `fk_korisnici_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  KATEGORIJE ARTIKALA (piće, hrana, topli napici...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategorije` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `naziv`      VARCHAR(100) NOT NULL,
  `redosled`   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_kat_lokal` (`lokal_id`),
  CONSTRAINT `fk_kat_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  ARTIKLI (šifarnik pića/hrane sa cenama)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `artikli` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`        INT UNSIGNED NOT NULL,
  `kategorija_id`   INT UNSIGNED DEFAULT NULL,
  `naziv`           VARCHAR(150) NOT NULL,
  `jedinica_mere`   VARCHAR(20)  NOT NULL DEFAULT 'kom',   -- kom, l, ml, kg, gajba...
  `nabavna_cena`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `prodajna_cena`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `zaliha`          DECIMAL(12,3) NOT NULL DEFAULT 0.000,   -- trenutno stanje zalihe
  `min_zaliha`      DECIMAL(12,3) NOT NULL DEFAULT 0.000,   -- alarm za minimalnu zalihu
  `aktivan`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_art_lokal` (`lokal_id`),
  KEY `ix_art_kat` (`kategorija_id`),
  CONSTRAINT `fk_art_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_art_kat` FOREIGN KEY (`kategorija_id`)
      REFERENCES `kategorije` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  PAZAR (dnevni promet po smenama)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pazar` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `datum`       DATE NOT NULL,
  `smena`       ENUM('prva','druga','cela') NOT NULL DEFAULT 'cela',
  `korisnik_id` INT UNSIGNED DEFAULT NULL,       -- ko je uneo/radio
  `iznos`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- ukupan pazar (keš + kartica)
  `kes`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `kartica`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `napomena`    VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pazar_lokal_datum` (`lokal_id`, `datum`),
  CONSTRAINT `fk_pazar_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pazar_korisnik` FOREIGN KEY (`korisnik_id`)
      REFERENCES `korisnici` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  DOBAVLJAČI
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
--  FAKTURE (prijem robe od dobavljača)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
--  STAVKE FAKTURE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faktura_stavke` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `faktura_id`  INT UNSIGNED NOT NULL,
  `artikal_id`  INT UNSIGNED DEFAULT NULL,
  `naziv`       VARCHAR(150) NOT NULL,
  `jedinica_mere` VARCHAR(20) NOT NULL DEFAULT 'kom',
  `kolicina`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `cena`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- nabavna cena po JM
  `iznos`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- kolicina * cena
  PRIMARY KEY (`id`),
  KEY `ix_stavka_fakt` (`faktura_id`),
  KEY `ix_stavka_art` (`artikal_id`),
  CONSTRAINT `fk_stavka_fakt` FOREIGN KEY (`faktura_id`)
      REFERENCES `fakture` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_art` FOREIGN KEY (`artikal_id`)
      REFERENCES `artikli` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  PROMET ZALIHA (istorija ulaza/izlaza)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zalihe_promet` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED NOT NULL,
  `tip`        ENUM('ulaz','izlaz','korekcija') NOT NULL,
  `kolicina`   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `razlog`     VARCHAR(120) DEFAULT NULL,     -- npr. "Faktura #12", "Otpis", "Popis"
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

-- ------------------------------------------------------------
--  TROŠKOVI / RAČUNI ZA OBJEKAT (struja, internet, plate, doprinosi...)
-- ------------------------------------------------------------
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
  `ponavljajuci`  TINYINT(1) NOT NULL DEFAULT 0,   -- mesečni trošak
  `napomena`      VARCHAR(255) DEFAULT NULL,
  `korisnik_id`   INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_trosak_lokal` (`lokal_id`, `datum`),
  CONSTRAINT `fk_trosak_lokal` FOREIGN KEY (`lokal_id`)
      REFERENCES `lokali` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  Super admin nalog NE pravimo ovde (zbog ispravnog hash-a lozinke).
--  Prvi nalog se kreira jednokratno preko /setup.php (instal čarobnjak),
--  koji koristi PHP password_hash() da bezbedno upiše lozinku.
-- ============================================================
