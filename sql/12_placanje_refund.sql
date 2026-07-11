-- ============================================================
--  SANK — Migracija 12: podeljeno plaćanje + povrat (refund)
-- ============================================================

ALTER TABLE `racuni`
  MODIFY `status` ENUM('otvoren','placen','storniran','refundiran') NOT NULL DEFAULT 'otvoren';

ALTER TABLE `racuni`
  MODIFY `nacin_placanja` ENUM('kes','kartica','mesovito') DEFAULT NULL;

ALTER TABLE `racuni`
  ADD COLUMN IF NOT EXISTS `placeno_kes`     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `nacin_placanja`,
  ADD COLUMN IF NOT EXISTS `placeno_kartica` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `placeno_kes`,
  ADD COLUMN IF NOT EXISTS `refund_razlog`   VARCHAR(255) DEFAULT NULL AFTER `storno_razlog`;
