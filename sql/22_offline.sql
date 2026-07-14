-- ============================================================
--  WAITER — Migracija 22: offline režim (sync bez duplikata)
-- ============================================================

ALTER TABLE `racuni`
  ADD COLUMN IF NOT EXISTS `uuid` CHAR(36) DEFAULT NULL AFTER `id`,
  ADD UNIQUE KEY `uq_racun_uuid` (`uuid`);
