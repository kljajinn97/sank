-- ============================================================
--  WAITER — Migracija 21: podrazumevana brend boja = bakar
--  (postojeći lokali zadržavaju svoju boju)
-- ============================================================

ALTER TABLE `lokali`
  MODIFY `boja` VARCHAR(9) NOT NULL DEFAULT '#b1662c';

ALTER TABLE `kategorije`
  MODIFY `boja` VARCHAR(9) NOT NULL DEFAULT '#b1662c';

-- Lokali koji su ostali na starom teal default-u prelaze na Waiter bakar
UPDATE `lokali` SET `boja`='#b1662c' WHERE `boja`='#0d9488';
UPDATE `kategorije` SET `boja`='#b1662c' WHERE `boja`='#0d9488';
