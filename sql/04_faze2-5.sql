-- ============================================================
--  SANK — Migracija 04: faze 2–5
--  Normativi, popis/inventura, nabavka (narudžbenice), ljudi i plate
-- ============================================================

-- PDV stopa lokala (za kalkulacije)
ALTER TABLE `lokali`
  ADD COLUMN IF NOT EXISTS `pdv_stopa` DECIMAL(5,2) NOT NULL DEFAULT 20.00 AFTER `valuta`;

-- ---------- FAZA 2: Normativi / recepture ----------
CREATE TABLE IF NOT EXISTS `normativi` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED NOT NULL,          -- gotov/prodajni artikal
  `napomena`   VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_norm_art` (`lokal_id`,`artikal_id`),
  CONSTRAINT `fk_norm_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_norm_art` FOREIGN KEY (`artikal_id`) REFERENCES `artikli`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `normativ_stavke` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `normativ_id` INT UNSIGNED NOT NULL,
  `sastojak_id` INT UNSIGNED NOT NULL,         -- sirovina iz artikli
  `kolicina`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `ix_ns_norm` (`normativ_id`),
  KEY `ix_ns_sas` (`sastojak_id`),
  CONSTRAINT `fk_ns_norm` FOREIGN KEY (`normativ_id`) REFERENCES `normativi`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ns_sas` FOREIGN KEY (`sastojak_id`) REFERENCES `artikli`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- FAZA 3: Popis / inventura ----------
CREATE TABLE IF NOT EXISTS `popis` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `datum`      DATE NOT NULL,
  `status`     ENUM('otvoren','zavrsen') NOT NULL DEFAULT 'otvoren',
  `napomena`   VARCHAR(255) DEFAULT NULL,
  `korisnik_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_popis_lokal` (`lokal_id`),
  CONSTRAINT `fk_popis_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `popis_stavke` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `popis_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED NOT NULL,
  `sistemska`  DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `izbrojano`  DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `nabavna`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_ps_popis` (`popis_id`),
  CONSTRAINT `fk_ps_popis` FOREIGN KEY (`popis_id`) REFERENCES `popis`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_art` FOREIGN KEY (`artikal_id`) REFERENCES `artikli`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- FAZA 4: Nabavka / narudžbenice ----------
CREATE TABLE IF NOT EXISTS `narudzbenice` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`     INT UNSIGNED NOT NULL,
  `dobavljac_id` INT UNSIGNED DEFAULT NULL,
  `datum`        DATE NOT NULL,
  `status`       ENUM('nacrt','poslata','primljena') NOT NULL DEFAULT 'nacrt',
  `iznos`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `napomena`     VARCHAR(255) DEFAULT NULL,
  `korisnik_id`  INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_nar_lokal` (`lokal_id`),
  CONSTRAINT `fk_nar_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nar_dob` FOREIGN KEY (`dobavljac_id`) REFERENCES `dobavljaci`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `narudzbenica_stavke` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `narudzbenica_id` INT UNSIGNED NOT NULL,
  `artikal_id`      INT UNSIGNED DEFAULT NULL,
  `naziv`           VARCHAR(150) NOT NULL,
  `jedinica_mere`   VARCHAR(20) NOT NULL DEFAULT 'kom',
  `kolicina`        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `cena`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `iznos`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_nars_nar` (`narudzbenica_id`),
  CONSTRAINT `fk_nars_nar` FOREIGN KEY (`narudzbenica_id`) REFERENCES `narudzbenice`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nars_art` FOREIGN KEY (`artikal_id`) REFERENCES `artikli`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- FAZA 5: Ljudi i plate ----------
CREATE TABLE IF NOT EXISTS `smene` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `korisnik_id` INT UNSIGNED NOT NULL,
  `datum`       DATE NOT NULL,
  `pocetak`     TIME DEFAULT NULL,
  `kraj`        TIME DEFAULT NULL,
  `sati`        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `napomena`    VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_smene_lokal` (`lokal_id`,`datum`),
  CONSTRAINT `fk_smene_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_smene_kor` FOREIGN KEY (`korisnik_id`) REFERENCES `korisnici`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plate` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `korisnik_id` INT UNSIGNED NOT NULL,
  `mesec`       CHAR(7) NOT NULL,               -- YYYY-MM
  `neto`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `doprinosi`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `bruto`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `isplaceno`   TINYINT(1) NOT NULL DEFAULT 0,
  `datum_isplate` DATE DEFAULT NULL,
  `napomena`    VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_plate_lokal` (`lokal_id`,`mesec`),
  CONSTRAINT `fk_plate_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plate_kor` FOREIGN KEY (`korisnik_id`) REFERENCES `korisnici`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `baksis` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `datum`       DATE NOT NULL,
  `korisnik_id` INT UNSIGNED DEFAULT NULL,
  `iznos`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `napomena`    VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_baksis_lokal` (`lokal_id`,`datum`),
  CONSTRAINT `fk_baksis_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_baksis_kor` FOREIGN KEY (`korisnik_id`) REFERENCES `korisnici`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
