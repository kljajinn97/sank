-- ============================================================
--  WAITER — Migracija 23: servisni ekran POS-a (štampač po uređaju)
-- ============================================================

ALTER TABLE `pos_uredjaji`
  ADD COLUMN IF NOT EXISTS `papir`         ENUM('80','58') NOT NULL DEFAULT '80' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `stampa_kopije` TINYINT NOT NULL DEFAULT 1 AFTER `papir`,
  ADD COLUMN IF NOT EXISTS `font_vel`      ENUM('normal','veliki') NOT NULL DEFAULT 'normal' AFTER `stampa_kopije`,
  ADD COLUMN IF NOT EXISTS `auto_stampa`   TINYINT(1) DEFAULT NULL AFTER `font_vel`;
