-- ============================================================
--  WAITER — Migracija 26: pazar je ISKLJUČIVO ručni dnevni upis.
--  Brišu se svi redovi koje je POS ranije automatski upisivao.
--  (Računi ostaju netaknuti — oni su izvor istine za POS promet.)
-- ============================================================

DELETE FROM `pazar` WHERE `napomena` = 'POS promet';
DELETE FROM `pazar` WHERE `napomena` LIKE 'POS račun #%';
DELETE FROM `pazar` WHERE `napomena` LIKE 'Povrat POS #%';
