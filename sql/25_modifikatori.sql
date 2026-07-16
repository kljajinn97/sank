-- ============================================================
--  WAITER — Migracija 25: modifikatori sa doplatom + brute-force zaštita
-- ============================================================

CREATE TABLE IF NOT EXISTS `modifikatori` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lokal_id`   INT UNSIGNED NOT NULL,
  `artikal_id` INT UNSIGNED NOT NULL,
  `naziv`      VARCHAR(100) NOT NULL,
  `cena`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `aktivan`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_mod_art` (`artikal_id`),
  KEY `ix_mod_lokal` (`lokal_id`),
  CONSTRAINT `fk_mod_lokal` FOREIGN KEY (`lokal_id`) REFERENCES `lokali`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mod_art` FOREIGN KEY (`artikal_id`) REFERENCES `artikli`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brute-force zaštita prijave
ALTER TABLE `korisnici`
  ADD COLUMN IF NOT EXISTS `fail_count` TINYINT NOT NULL DEFAULT 0 AFTER `pin`,
  ADD COLUMN IF NOT EXISTS `lock_until` DATETIME DEFAULT NULL AFTER `fail_count`;
