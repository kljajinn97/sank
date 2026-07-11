<?php
/** Izveštaji i analitika */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

// Period
$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-', $mesec) + [1=>0,2=>0]);
if ($gy < 2000) { $gy = (int)date('Y'); $gm = (int)date('n'); }

// Prethodni mesec
$prev = mktime(0,0,0,$gm-1,1,$gy);
$py = (int)date('Y',$prev); $pm = (int)date('n',$prev);

/** Sumarni podaci za dati mesec */
function mesecSum($lid,$y,$m): array {
    $pazar = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$y,$m]);
    $kes   = (float)db_val('SELECT COALESCE(SUM(kes),0) FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$y,$m]);
    $kart  = (float)db_val('SELECT COALESCE(SUM(kartica),0) FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$y,$m]);
    $nabavka = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM fakture WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$y,$m]);
    $troskovi = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM troskovi WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$y,$m]);
    return ['pazar'=>$pazar,'kes'=>$kes,'kartica'=>$kart,'nabavka'=>$nabavka,'troskovi'=>$troskovi,'bilans'=>$pazar-$nabavka-$troskovi];
}

$cur = mesecSum($lid,$gy,$gm);
$pre = mesecSum($lid,$py,$pm);

// ---------- CSV izvoz ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="izvestaj-'.sprintf('%04d-%02d',$gy,$gm).'.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM za Excel
    $out = fopen('php://output','w');
    fputcsv($out, ['Izveštaj', sprintf('%02d/%04d',$gm,$gy)], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Stavka','Iznos (RSD)'], ';');
    fputcsv($out, ['Pazar ukupno', number_format($cur['pazar'],2,',','.')], ';');
    fputcsv($out, ['  - keš', number_format($cur['kes'],2,',','.')], ';');
    fputcsv($out, ['  - kartica', number_format($cur['kartica'],2,',','.')], ';');
    fputcsv($out, ['Nabavka (fakture)', number_format($cur['nabavka'],2,',','.')], ';');
    fputcsv($out, ['Troškovi', number_format($cur['troskovi'],2,',','.')], ';');
    fputcsv($out, ['BILANS', number_format($cur['bilans'],2,',','.')], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Troškovi po kategorijama'], ';');
    foreach (db_all('SELECT kategorija, SUM(iznos) s FROM troskovi WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=? GROUP BY kategorija ORDER BY s DESC',[$lid,$gy,$gm]) as $r)
        fputcsv($out, [$r['kategorija'], number_format($r['s'],2,',','.')], ';');
    fclose($out);
    exit;
}

// Dnevni pazar (za grafikon)
$dani = (int)date('t', mktime(0,0,0,$gm,1,$gy));
$dnevni = array_fill(1,$dani,0.0);
foreach (db_all('SELECT DAY(datum) d, SUM(iznos) s FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=? GROUP BY DAY(datum)',[$lid,$gy,$gm]) as $r)
    $dnevni[(int)$r['d']] = (float)$r['s'];
$maxDnevni = max(1, max($dnevni));

// Troškovi po kategorijama
$KAT = ['struja'=>'Struja','voda'=>'Voda','internet'=>'Internet/TV','telefon'=>'Telefon','zakup'=>'Zakup','plate'=>'Plate','doprinosi'=>'Porezi i doprinosi','namirnice'=>'Namirnice','oprema'=>'Oprema','porez'=>'Porez','marketing'=>'Marketing','ostalo'=>'Ostalo'];
$troskoviKat = db_all('SELECT kategorija, SUM(iznos) s FROM troskovi WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=? GROUP BY kategorija ORDER BY s DESC',[$lid,$gy,$gm]);

// Nabavka po dobavljačima
$nabavkaDob = db_all('SELECT COALESCE(d.naziv,"(bez dobavljača)") naziv, SUM(f.iznos) s FROM fakture f LEFT JOIN dobavljaci d ON d.id=f.dobavljac_id
                      WHERE f.lokal_id=? AND YEAR(f.datum)=? AND MONTH(f.datum)=? GROUP BY f.dobavljac_id ORDER BY s DESC',[$lid,$gy,$gm]);

// Prodaja po konobaru (POS, plaćeni računi)
$poKonobaru = db_all(
   "SELECT COALESCE(NULLIF(TRIM(CONCAT(k.ime,' ',COALESCE(k.prezime,''))),''),'—') AS ime,
           COUNT(*) br, SUM(r.ukupno) uk,
           SUM(CASE WHEN r.nacin_placanja='kes' THEN r.ukupno ELSE 0 END) kes,
           SUM(CASE WHEN r.nacin_placanja='kartica' THEN r.ukupno ELSE 0 END) kartica
    FROM racuni r LEFT JOIN korisnici k ON k.id=r.korisnik_id
    WHERE r.lokal_id=? AND r.status='placen' AND YEAR(r.closed_at)=? AND MONTH(r.closed_at)=?
    GROUP BY r.korisnik_id ORDER BY uk DESC", [$lid,$gy,$gm]);

// Stornirani računi u mesecu
$storna = db_all(
   "SELECT r.id, r.ukupno, r.storno_razlog, r.closed_at,
           TRIM(CONCAT(COALESCE(k.ime,''),' ',COALESCE(k.prezime,''))) AS ime
    FROM racuni r LEFT JOIN korisnici k ON k.id=r.korisnik_id
    WHERE r.lokal_id=? AND r.status='storniran' AND YEAR(r.closed_at)=? AND MONTH(r.closed_at)=?
    ORDER BY r.closed_at DESC", [$lid,$gy,$gm]);

// Godišnji pregled (12 meseci izabrane godine)
$godMeseci = [];
for ($m=1;$m<=12;$m++) $godMeseci[$m] = mesecSum($lid,$gy,$m);
$maxGod = 1; foreach ($godMeseci as $gmx) $maxGod = max($maxGod, $gmx['pazar']);

function promena(float $sad, float $pre): string {
    if ($pre == 0) return $sad>0 ? '<span class="in">nov</span>' : '—';
    $p = round(($sad-$pre)/$pre*100);
    $cls = $p>=0 ? 'in' : 'out';
    return '<span class="'.$cls.'">'.($p>=0?'▲':'▼').' '.abs($p).'%</span>';
}
$mesLbl = ['','Januar','Februar','Mart','April','Maj','Jun','Jul','Avgust','Septembar','Oktobar','Novembar','Decembar'];

$page_title = 'Izveštaji';
$active = 'izvestaji';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head no-print">
  <div><h1>Izveštaji i analitika</h1><p>Promet, troškovi i profit — pregled i izvoz.</p></div>
  <div class="flex gap-2">
    <a class="btn btn--ghost" href="<?= url('izvestaji') ?>?mesec=<?= sprintf('%04d-%02d',$gy,$gm) ?>&export=csv"><?= ico('download',16) ?> CSV (Excel)</a>
    <button class="btn btn--ghost" onclick="window.print()"><?= ico('print',16) ?> Štampaj / PDF</button>
  </div>
</div>

<form class="toolbar no-print" method="get" action="<?= url('izvestaji') ?>">
  <label class="label" style="margin:0">Mesec:</label>
  <input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()">
</form>

<div class="print-title" style="display:none"><h2>Izveštaj <?= e($mesLbl[$gm].' '.$gy) ?> — <?= e(current_user()['lokal_naziv']) ?></h2></div>

<!-- KPI -->
<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Pazar (promet)</div><div class="stat__value in"><?= novac($cur['pazar']) ?></div>
    <div class="stat__delta">vs prošli mesec: <?= promena($cur['pazar'],$pre['pazar']) ?></div></div>
  <div class="stat"><div class="stat__label">Nabavka + troškovi</div><div class="stat__value out"><?= novac($cur['nabavka']+$cur['troskovi']) ?></div>
    <div class="stat__delta muted">Nabavka <?= novac($cur['nabavka']) ?> · Troškovi <?= novac($cur['troskovi']) ?></div></div>
  <div class="stat"><div class="stat__label">Bilans (profit)</div><div class="stat__value <?= $cur['bilans']>=0?'in':'out' ?>"><?= novac($cur['bilans']) ?></div>
    <div class="stat__delta">vs prošli: <?= promena($cur['bilans'],$pre['bilans']) ?></div></div>
</div>

<div class="grid-2 mb-2">
  <div class="stat"><div class="stat__label">Keš</div><div class="stat__value"><?= novac($cur['kes']) ?></div>
    <div class="progress" style="margin-top:8px"><span style="width:<?= $cur['pazar']>0?round($cur['kes']/$cur['pazar']*100):0 ?>%"></span></div></div>
  <div class="stat"><div class="stat__label">Kartica</div><div class="stat__value"><?= novac($cur['kartica']) ?></div>
    <div class="progress" style="margin-top:8px"><span style="width:<?= $cur['pazar']>0?round($cur['kartica']/$cur['pazar']*100):0 ?>%;background:var(--info)"></span></div></div>
</div>

<!-- Grafikon dnevnog prometa -->
<div class="card mb-2">
  <div class="card__head"><div class="card__title">Dnevni promet — <?= e($mesLbl[$gm].' '.$gy) ?></div></div>
  <div class="card__body">
    <?php if (array_sum($dnevni) == 0): ?>
      <div class="empty">Nema unetog pazara za ovaj mesec.</div>
    <?php else: ?>
    <div style="display:flex;align-items:flex-end;gap:3px;height:180px;">
      <?php for ($d=1;$d<=$dani;$d++): $h = round($dnevni[$d]/$maxDnevni*100); ?>
        <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%;" title="<?= $d ?>. — <?= novac($dnevni[$d]) ?>">
          <div style="height:<?= $h ?>%;background:linear-gradient(180deg,var(--brand),var(--brand-700));border-radius:4px 4px 0 0;min-height:<?= $dnevni[$d]>0?2:0 ?>px;"></div>
        </div>
      <?php endfor; ?>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.72rem;color:var(--text-3)">
      <span>1.</span><span><?= ceil($dani/2) ?>.</span><span><?= $dani ?>.</span>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2 mb-2">
  <!-- Troškovi po kategorijama -->
  <div class="card">
    <div class="card__head"><div class="card__title">Troškovi po kategorijama</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Kategorija</th><th class="num">Iznos</th><th class="num">%</th></tr></thead>
      <tbody>
      <?php if (!$troskoviKat): ?><tr><td colspan="3"><div class="empty">Nema troškova.</div></td></tr>
      <?php else: foreach ($troskoviKat as $t): $pct = $cur['troskovi']>0?round($t['s']/$cur['troskovi']*100):0; ?>
        <tr>
          <td><?= e($KAT[$t['kategorija']] ?? $t['kategorija']) ?>
            <div class="progress" style="margin-top:5px;max-width:160px"><span style="width:<?= $pct ?>%;background:var(--accent)"></span></div></td>
          <td class="num"><?= novac($t['s']) ?></td>
          <td class="num muted"><?= $pct ?>%</td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>

  <!-- Nabavka po dobavljačima -->
  <div class="card">
    <div class="card__head"><div class="card__title">Nabavka po dobavljačima</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Dobavljač</th><th class="num">Iznos</th></tr></thead>
      <tbody>
      <?php if (!$nabavkaDob): ?><tr><td colspan="2"><div class="empty">Nema faktura.</div></td></tr>
      <?php else: foreach ($nabavkaDob as $n): ?>
        <tr><td><?= e($n['naziv']) ?></td><td class="num"><?= novac($n['s']) ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php if ($poKonobaru || $storna): ?>
<div class="grid-2 mb-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Prodaja po konobaru (POS)</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Konobar</th><th class="num">Računa</th><th class="num">Keš</th><th class="num">Kartica</th><th class="num">Ukupno</th></tr></thead>
      <tbody>
      <?php if (!$poKonobaru): ?><tr><td colspan="5"><div class="empty">Nema POS prodaje u mesecu.</div></td></tr>
      <?php else: foreach ($poKonobaru as $p): ?>
        <tr><td><strong><?= e($p['ime']) ?></strong></td>
          <td class="num"><?= (int)$p['br'] ?></td>
          <td class="num muted"><?= novac($p['kes']) ?></td>
          <td class="num muted"><?= novac($p['kartica']) ?></td>
          <td class="num in"><?= novac($p['uk']) ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card__head"><div class="card__title">Stornirani računi <?= $storna?'('.count($storna).')':'' ?></div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Račun</th><th>Konobar</th><th class="num">Iznos</th><th>Razlog</th></tr></thead>
      <tbody>
      <?php if (!$storna): ?><tr><td colspan="4"><div class="empty">Nema storniranih računa. 👍</div></td></tr>
      <?php else: foreach ($storna as $st): ?>
        <tr><td><strong>#<?= (int)$st['id'] ?></strong><div class="muted" style="font-size:.76rem"><?= datum($st['closed_at']) ?></div></td>
          <td><?= e($st['ime'] ?: '—') ?></td>
          <td class="num out"><?= novac($st['ukupno']) ?></td>
          <td class="muted" style="font-size:.85rem"><?= e($st['storno_razlog'] ?: '') ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<!-- Godišnji pregled -->
<div class="card">
  <div class="card__head"><div class="card__title">Godišnji pregled — <?= $gy ?></div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mesec</th><th class="num">Pazar</th><th class="num">Nabavka</th><th class="num">Troškovi</th><th class="num">Bilans</th></tr></thead>
    <tbody>
    <?php $sy=['pazar'=>0,'nabavka'=>0,'troskovi'=>0,'bilans'=>0];
      foreach ($godMeseci as $mi=>$g): if ($g['pazar']==0 && $g['nabavka']==0 && $g['troskovi']==0) continue;
        foreach ($sy as $k=>$v) $sy[$k]+=$g[$k]; ?>
      <tr <?= $mi==$gm?'style="background:var(--brand-soft)"':'' ?>>
        <td><strong><?= e($mesLbl[$mi]) ?></strong></td>
        <td class="num"><?= novac($g['pazar']) ?></td>
        <td class="num muted"><?= novac($g['nabavka']) ?></td>
        <td class="num muted"><?= novac($g['troskovi']) ?></td>
        <td class="num <?= $g['bilans']>=0?'in':'out' ?>"><?= novac($g['bilans']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td><strong>UKUPNO <?= $gy ?></strong></td>
      <td class="num"><strong><?= novac($sy['pazar']) ?></strong></td>
      <td class="num"><strong><?= novac($sy['nabavka']) ?></strong></td>
      <td class="num"><strong><?= novac($sy['troskovi']) ?></strong></td>
      <td class="num <?= $sy['bilans']>=0?'in':'out' ?>"><strong><?= novac($sy['bilans']) ?></strong></td></tr></tfoot>
  </table></div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
