-- ============================================================
--  SANK — Migracija 14: mapa lokala (pozicije stolova na tlocrtu)
-- ============================================================

ALTER TABLE `stolovi`
  ADD COLUMN IF NOT EXISTS `pos_x` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `redosled`,
  ADD COLUMN IF NOT EXISTS `pos_y` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `pos_x`,
  ADD COLUMN IF NOT EXISTS `oblik` ENUM('krug','kvadrat') NOT NULL DEFAULT 'krug' AFTER `pos_y`;
