<?php
/** Analitika prodaje — top artikli, po satima, po danima */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-', $mesec) + [1=>0,2=>0]);
if ($gy < 2000) { $gy = (int)date('Y'); $gm = (int)date('n'); }
$W = 'r.lokal_id=? AND r.status="placen" AND YEAR(r.closed_at)=? AND MONTH(r.closed_at)=?';
$P = [$lid,$gy,$gm];

$promet = (float)db_val("SELECT COALESCE(SUM(ukupno),0) FROM racuni r WHERE $W", $P);
$broj   = (int)db_val("SELECT COUNT(*) FROM racuni r WHERE $W", $P);
$prosek = $broj > 0 ? $promet/$broj : 0;

// Top artikli (količina + prihod, uz popust računa)
$top = db_all("SELECT rs.naziv, SUM(rs.kolicina) kol, SUM(rs.iznos*(1-r.popust_pct/100)) prihod
               FROM racun_stavke rs JOIN racuni r ON r.id=rs.racun_id
               WHERE $W GROUP BY rs.naziv ORDER BY prihod DESC LIMIT 20", $P);
$maxPrihod = 0; foreach ($top as $t) $maxPrihod = max($maxPrihod, (float)$t['prihod']);

// Po satima (0-23)
$sati = array_fill(0,24,0.0);
foreach (db_all("SELECT HOUR(closed_at) h, SUM(ukupno) s FROM racuni r WHERE $W GROUP BY HOUR(closed_at)", $P) as $x) $sati[(int)$x['h']] = (float)$x['s'];
$maxSat = max(1, max($sati));

// Po danu u nedelji (MySQL DAYOFWEEK: 1=Ned ... 7=Sub) → prikaz Pon..Ned
$dani = array_fill(1,7,0.0);
foreach (db_all("SELECT DAYOFWEEK(closed_at) d, SUM(ukupno) s FROM racuni r WHERE $W GROUP BY DAYOFWEEK(closed_at)", $P) as $x) $dani[(int)$x['d']] = (float)$x['s'];
$danRed = [2=>'Pon',3=>'Uto',4=>'Sre',5=>'Čet',6=>'Pet',7=>'Sub',1=>'Ned'];
$maxDan = max(1, max($dani));
$mesLbl = ['','Januar','Februar','Mart','April','Maj','Jun','Jul','Avgust','Septembar','Oktobar','Novembar','Decembar'];

$page_title = 'Analitika prodaje';
$active = 'prodaja';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Analitika prodaje</h1><p>Top artikli i obrasci prodaje — <?= e($mesLbl[$gm].' '.$gy) ?>.</p></div>
  <form method="get" action="<?= url('prodaja') ?>"><input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()" style="width:auto"></form>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Promet (POS)</div><div class="stat__value in"><?= novac($promet) ?></div></div>
  <div class="stat"><div class="stat__label">Broj računa</div><div class="stat__value"><?= $broj ?></div></div>
  <div class="stat"><div class="stat__label">Prosečan račun</div><div class="stat__value"><?= novac($prosek) ?></div></div>
</div>

<div class="grid-2 mb-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Prodaja po satima</div></div>
    <div class="card__body">
      <?php if (array_sum($sati)==0): ?><div class="empty">Nema podataka.</div>
      <?php else: ?>
      <div style="display:flex;align-items:flex-end;gap:2px;height:150px">
        <?php for($h=0;$h<24;$h++): $bh=round($sati[$h]/$maxSat*100); ?>
          <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%" title="<?= sprintf('%02d',$h) ?>h — <?= novac($sati[$h]) ?>">
            <div style="height:<?= $bh ?>%;background:linear-gradient(180deg,var(--brand),var(--brand-700));border-radius:3px 3px 0 0;min-height:<?= $sati[$h]>0?2:0 ?>px"></div></div>
        <?php endfor; ?>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:.68rem;color:var(--text-3)"><span>00h</span><span>08h</span><span>16h</span><span>23h</span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><div class="card__title">Prodaja po danu u nedelji</div></div>
    <div class="card__body">
      <?php if (array_sum($dani)==0): ?><div class="empty">Nema podataka.</div>
      <?php else: ?>
      <div style="display:flex;align-items:flex-end;gap:8px;height:150px">
        <?php foreach($danRed as $d=>$lbl): $bh=round($dani[$d]/$maxDan*100); ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%" title="<?= novac($dani[$d]) ?>">
            <div style="width:100%;height:<?= $bh ?>%;background:linear-gradient(180deg,var(--accent),#d97706);border-radius:5px 5px 0 0;min-height:<?= $dani[$d]>0?2:0 ?>px"></div></div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:8px;margin-top:6px;font-size:.72rem;color:var(--text-3)">
        <?php foreach($danRed as $lbl): ?><span style="flex:1;text-align:center"><?= e($lbl) ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head"><div class="card__title">Top artikli (najprodavaniji)</div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Artikal</th><th class="num">Količina</th><th class="num">Prihod</th><th style="width:30%">Udeo</th></tr></thead>
    <tbody>
    <?php if (!$top): ?><tr><td colspan="5"><div class="empty">Nema prodaje u ovom mesecu.</div></td></tr>
    <?php else: $i=0; foreach ($top as $t): $i++; $pct=$maxPrihod>0?round($t['prihod']/$maxPrihod*100):0; ?>
      <tr>
        <td class="muted"><?= $i ?></td>
        <td><strong><?= e($t['naziv']) ?></strong></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$t['kol'],3,',','.'),'0'),',') ?></td>
        <td class="num in"><?= novac($t['prihod']) ?></td>
        <td><div class="progress" style="height:8px"><span style="width:<?= $pct ?>%"></span></div></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
