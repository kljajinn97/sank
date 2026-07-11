<?php
/** PDV evidencija — promet po poreskim stopama (iz POS računa) */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);

// Poreske oznake → stopa (%)
$STOPE = ['Ђ' => 20, 'Е' => 10, 'А' => 0];
$LBL   = ['Ђ' => 'Ђ — opšta 20%', 'Е' => 'Е — posebna 10%', 'А' => 'А — 0% / oslobođeno'];

$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-', $mesec) + [1=>0,2=>0]);
if ($gy < 2000) { $gy = (int)date('Y'); $gm = (int)date('n'); }

// Promet (bruto) po oznaci iz plaćenih računa, uz bill-level popust
$rows = db_all(
   "SELECT COALESCE(a.poreska_oznaka,'Ђ') AS oz, SUM(rs.iznos * (1 - r.popust_pct/100)) AS bruto
    FROM racun_stavke rs
    JOIN racuni r ON r.id = rs.racun_id
    LEFT JOIN artikli a ON a.id = rs.artikal_id
    WHERE r.lokal_id = ? AND r.status = 'placen' AND YEAR(r.closed_at) = ? AND MONTH(r.closed_at) = ?
    GROUP BY oz", [$lid,$gy,$gm]);

$po = []; foreach ($rows as $r) $po[$r['oz']] = (float)$r['bruto'];

$totBruto=0; $totOsn=0; $totPdv=0; $izracun=[];
foreach ($STOPE as $oz => $stopa) {
    $bruto = $po[$oz] ?? 0;
    $osn = $stopa > 0 ? $bruto / (1 + $stopa/100) : $bruto;
    $pdv = $bruto - $osn;
    $izracun[$oz] = ['stopa'=>$stopa,'bruto'=>$bruto,'osn'=>$osn,'pdv'=>$pdv];
    $totBruto+=$bruto; $totOsn+=$osn; $totPdv+=$pdv;
}
$mesLbl = ['','Januar','Februar','Mart','April','Maj','Jun','Jul','Avgust','Septembar','Oktobar','Novembar','Decembar'];

$page_title = 'PDV evidencija';
$active = 'pdv';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head no-print">
  <div><h1>PDV evidencija</h1><p>Promet po poreskim stopama (iz POS računa) — za knjigovođu.</p></div>
  <div class="flex gap-2">
    <form method="get" action="<?= url('pdv') ?>"><input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()" style="width:auto"></form>
    <button class="btn btn--ghost" onclick="window.print()"><?= ico('print',16) ?> Štampaj</button>
  </div>
</div>

<div class="print-title" style="display:none">
  <h2><?= e($lokal['naziv']) ?> — PDV evidencija</h2>
  <p>PIB: <?= e($lokal['pib'] ?: '—') ?> · Period: <?= e($mesLbl[$gm].' '.$gy) ?></p>
</div>

<?php if (empty($lokal['pdv_obveznik'])): ?>
<div class="flash flash--info no-print" style="margin-bottom:14px">Lokal nije označen kao PDV obveznik (Podešavanja → Fiskalizacija). Prikaz je informativan.</div>
<?php endif; ?>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Ukupan promet (bruto)</div><div class="stat__value"><?= novac($totBruto) ?></div></div>
  <div class="stat"><div class="stat__label">Osnovica (bez PDV)</div><div class="stat__value"><?= novac($totOsn) ?></div></div>
  <div class="stat"><div class="stat__label">PDV ukupno</div><div class="stat__value out"><?= novac($totPdv) ?></div></div>
</div>

<div class="card">
  <div class="card__head no-print"><div class="card__title">Po stopama — <?= e($mesLbl[$gm].' '.$gy) ?></div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Poreska oznaka</th><th class="num">Stopa</th><th class="num">Promet (bruto)</th><th class="num">Osnovica</th><th class="num">PDV</th></tr></thead>
    <tbody>
    <?php foreach ($izracun as $oz => $c): ?>
      <tr>
        <td><strong><?= e($LBL[$oz]) ?></strong></td>
        <td class="num"><?= (int)$c['stopa'] ?>%</td>
        <td class="num"><?= novac($c['bruto']) ?></td>
        <td class="num muted"><?= novac($c['osn']) ?></td>
        <td class="num"><?= novac($c['pdv']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2" class="num"><strong>UKUPNO</strong></td>
      <td class="num"><strong><?= novac($totBruto) ?></strong></td>
      <td class="num"><strong><?= novac($totOsn) ?></strong></td>
      <td class="num"><strong><?= novac($totPdv) ?></strong></td></tr></tfoot>
  </table></div>
</div>

<p class="help no-print" style="margin-top:12px">Napomena: obračun je iz POS računa (stavke × poreska oznaka artikla), uz uračunat popust računa. Poreske oznake se podešavaju na artiklima.</p>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
