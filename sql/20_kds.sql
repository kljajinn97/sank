-- ============================================================
--  WAITER — Migracija 20: kuhinjski ekran (KDS)
-- ============================================================

ALTER TABLE `racun_stavke`
  ADD COLUMN IF NOT EXISTS `spremljeno` TINYINT(1) NOT NULL DEFAULT 0 AFTER `poslato`,
  ADD COLUMN IF NOT EXISTS `poslato_at` DATETIME DEFAULT NULL AFTER `spremljeno`;
