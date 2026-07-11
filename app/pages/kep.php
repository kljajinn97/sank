<?php
/** KEP knjiga — Knjiga evidencije prometa */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);

$god = (int)($_GET['god'] ?? date('Y'));
if ($god < 2000) $god = (int)date('Y');

// Dnevni promet za godinu (zbir po danu)
$dani = db_all('SELECT datum, SUM(iznos) promet, SUM(kes) kes, SUM(kartica) kartica
                FROM pazar WHERE lokal_id=? AND YEAR(datum)=? GROUP BY datum ORDER BY datum', [$lid,$god]);
$ukupno = 0.0;

$page_title = 'KEP knjiga';
$active = 'kep';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head no-print">
  <div><h1>KEP knjiga</h1><p>Knjiga evidencije prometa — dnevni promet sa kumulativom.</p></div>
  <div class="flex gap-2">
    <form method="get" action="<?= url('kep') ?>" class="flex gap-2 items-center">
      <label class="label" style="margin:0">Godina:</label>
      <input class="input" type="number" name="god" value="<?= $god ?>" min="2000" max="2100" style="width:110px" onchange="this.form.submit()">
    </form>
    <button class="btn btn--ghost" onclick="window.print()">🖨 Štampaj / PDF</button>
  </div>
</div>

<div class="print-title" style="display:none">
  <h2>KEP knjiga — <?= e($lokal['naziv']) ?></h2>
  <p><?= e(trim(($lokal['adresa']??'').' '.($lokal['grad']??''))) ?> · PIB: <?= e($lokal['pib'] ?: '—') ?> · Godina: <?= $god ?></p>
</div>

<div class="card">
  <div class="card__head no-print"><div class="card__title">Promet za <?= $god ?>. godinu</div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th style="width:60px">R. br.</th><th>Datum</th><th class="num">Keš</th><th class="num">Kartica</th><th class="num">Dnevni promet</th><th class="num">Kumulativno</th></tr></thead>
    <tbody>
    <?php if (!$dani): ?>
      <tr><td colspan="6"><div class="empty">Nema evidentiranog prometa za <?= $god ?>. godinu.</div></td></tr>
    <?php else: $i=0; foreach ($dani as $d): $i++; $ukupno += (float)$d['promet']; ?>
      <tr>
        <td class="muted"><?= $i ?></td>
        <td><strong><?= datum($d['datum']) ?></strong></td>
        <td class="num muted"><?= novac($d['kes']) ?></td>
        <td class="num muted"><?= novac($d['kartica']) ?></td>
        <td class="num in"><?= novac($d['promet']) ?></td>
        <td class="num"><?= novac($ukupno) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if ($dani): ?>
    <tfoot><tr><td colspan="4" class="num"><strong>UKUPAN PROMET <?= $god ?>.</strong></td><td colspan="2" class="num"><strong><?= novac($ukupno) ?></strong></td></tr></tfoot>
    <?php endif; ?>
  </table></div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
