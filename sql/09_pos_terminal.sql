-- ============================================================
--  SANK — Migracija 09: POS terminal (uređaji, aktivacioni kodovi, PIN radnika)
-- ============================================================

-- PIN radnika (heširan) za brzu prijavu na POS lock screen
ALTER TABLE `korisnici`
  ADD COLUMN IF NOT EXISTS `pin` VARCHAR(255) DEFAULT NULL AFTER `password_hash`;

-- POS uređaji (svaki vezan za jedan lokal preko tokena)
CREATE TABLE IF NOT EXISTS `pos_uredjaji` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`           INT UNSIGNED NOT NULL,
  `naziv`              VARCHAR(80) NOT NULL DEFAULT 'POS',
  `token`              CHAR(64) NOT NULL,
  `status`             ENUM('aktivan','blokiran') NOT NULL DEFAULT 'aktivan',
  `aktiviran_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `poslednja_aktivnost` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uredjaj_token` (`token`),
  KEY `ix_uredjaj_lokal` (`lokal_id`),
  CONSTRAINT `fk_uredjaj_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aktivacioni kodovi (jednokratni; admin ih generiše, POS ih unosi pri aktivaciji)
CREATE TABLE IF NOT EXISTS `aktivacioni_kodovi` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`    INT UNSIGNED NOT NULL,
  `kod`         VARCHAR(16) NOT NULL,
  `uredjaj_id`  INT UNSIGNED DEFAULT NULL,
  `iskoriscen`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kod` (`kod`),
  KEY `ix_kod_lokal` (`lokal_id`),
  CONSTRAINT `fk_kod_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
