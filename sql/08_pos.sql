-- ============================================================
--  SANK — Migracija 08: POS (stolovi, računi, stavke)
-- ============================================================

CREATE TABLE IF NOT EXISTS `stolovi` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`  INT UNSIGNED NOT NULL,
  `naziv`     VARCHAR(60) NOT NULL,
  `zona`      VARCHAR(60) DEFAULT NULL,
  `redosled`  INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sto_lokal` (`lokal_id`),
  CONSTRAINT `fk_sto_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `racuni` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`       INT UNSIGNED NOT NULL,
  `sto_id`         INT UNSIGNED DEFAULT NULL,          -- NULL = šank / brza prodaja
  `status`         ENUM('otvoren','placen','storniran') NOT NULL DEFAULT 'otvoren',
  `popust_pct`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `ukupno`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `nacin_placanja` ENUM('kes','kartica') DEFAULT NULL,
  `korisnik_id`    INT UNSIGNED DEFAULT NULL,
  `napomena`       VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rac_lokal` (`lokal_id`, `status`),
  KEY `ix_rac_sto` (`sto_id`),
  CONSTRAINT `fk_rac_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rac_sto` FOREIGN KEY (`sto_id`) REFERENCES `stolovi`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `racun_stavke` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `racun_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED DEFAULT NULL,
  `naziv`      VARCHAR(150) NOT NULL,
  `cena`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `kolicina`   DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `iznos`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_rs_racun` (`racun_id`),
  CONSTRAINT `fk_rs_racun` FOREIGN KEY (`racun_id`) REFERENCES `racuni`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rs_art` FOREIGN KEY (`artikal_id`) REFERENCES `artikli`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
