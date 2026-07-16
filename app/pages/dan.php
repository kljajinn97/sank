<?php
/** Zatvaranje dana — KPO dnevni izveštaj po artiklu */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$uid = current_user()['id'];
$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);

$datum = $_GET['dan'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) $datum = date('Y-m-d');
$jeDanas = ($datum === date('Y-m-d'));

// Zatvaranje dana (upsert dnevnog izveštaja)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'zatvori') {
    csrf_check();
    $d = $_POST['dan'] ?? $datum;
    // Promet dana = iz RAČUNA (izvor istine za POS)
    $pz = db_row("SELECT COALESCE(SUM(ukupno),0) iznos, COALESCE(SUM(placeno_kes),0) kes, COALESCE(SUM(placeno_kartica),0) kartica
                  FROM racuni WHERE lokal_id=? AND status='placen' AND DATE(closed_at)=?", [$lid,$d]);
    $br = (int)db_val('SELECT COUNT(*) FROM racuni WHERE lokal_id=? AND status="placen" AND DATE(closed_at)=?', [$lid,$d]);
    db_run('INSERT INTO dnevni_izvestaji (lokal_id,datum,promet,kes,kartica,br_racuna,korisnik_id)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE promet=VALUES(promet),kes=VALUES(kes),kartica=VALUES(kartica),br_racuna=VALUES(br_racuna)',
            [$lid,$d,$pz['iznos'],$pz['kes'],$pz['kartica'],$br,$uid]);
    audit('zatvaranje_dana','dan',null,'Dan '.$d.' zatvoren ('.novac($pz['iznos']).')');
    flash('success','Dan '.datum($d).' je zatvoren.');
    redirect(url('dan').'?dan='.$d);
}

// Kretanja artikala za dati dan
$mov = [];
foreach (db_all("SELECT artikal_id, SUM(IF(tip='ulaz',kolicina,0)) ul, SUM(IF(tip='izlaz',kolicina,0)) iz
                 FROM zalihe_promet WHERE lokal_id=? AND DATE(datum)=? GROUP BY artikal_id", [$lid,$datum]) as $m)
    $mov[(int)$m['artikal_id']] = ['ul'=>(float)$m['ul'], 'iz'=>(float)$m['iz']];

$artikli = db_all('SELECT id,naziv,jedinica_mere,prodajna_cena,zaliha FROM artikli WHERE lokal_id=? ORDER BY naziv', [$lid]);
$redovi = []; $sumPromet = 0.0;
foreach ($artikli as $a) {
    $ul = $mov[$a['id']]['ul'] ?? 0; $iz = $mov[$a['id']]['iz'] ?? 0;
    $stanje = (float)$a['zaliha'];
    if ($ul == 0 && $iz == 0 && $stanje == 0) continue;
    $pocetno = $stanje - $ul + $iz;             // tačno za današnji dan
    $cena = (float)$a['prodajna_cena'];
    $promet = $iz * $cena;
    $sumPromet += $promet;
    $redovi[] = compact('a') + ['ul'=>$ul,'iz'=>$iz,'stanje'=>$stanje,'pocetno'=>$pocetno,'cena'=>$cena,'promet'=>$promet];
}

// Dnevni novčani promet (pazar) + broj računa
// POS promet dana = iz RAČUNA; ručni pazar (konobarov upis) posebno
$pz = db_row("SELECT COALESCE(SUM(ukupno),0) iznos, COALESCE(SUM(placeno_kes),0) kes, COALESCE(SUM(placeno_kartica),0) kartica
              FROM racuni WHERE lokal_id=? AND status='placen' AND DATE(closed_at)=?", [$lid,$datum]);
$rucno = db_row('SELECT COALESCE(SUM(iznos),0) iznos FROM pazar WHERE lokal_id=? AND datum=?', [$lid,$datum]);
$brRacuna = (int)db_val('SELECT COUNT(*) FROM racuni WHERE lokal_id=? AND status="placen" AND DATE(closed_at)=?', [$lid,$datum]);
$zatvoren = db_row('SELECT * FROM dnevni_izvestaji WHERE lokal_id=? AND datum=?', [$lid,$datum]);

function kol3($v){ return rtrim(rtrim(number_format((float)$v,3,',','.'),'0'),',') ?: '0'; }

$page_title = 'Zatvaranje dana';
$active = 'dan';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head no-print">
  <div><h1>Zatvaranje dana <?= $zatvoren?'<span class="badge badge--ok" style="vertical-align:middle">zatvoren</span>':'' ?></h1>
    <p>KPO dnevni izveštaj po artiklu — ulaz, stanje, izlaz, cena, promet.</p></div>
  <div class="flex gap-2">
    <form method="get" action="<?= url('dan') ?>" class="flex gap-2 items-center">
      <input class="input" type="date" name="dan" value="<?= e($datum) ?>" onchange="this.form.submit()" style="width:auto">
    </form>
    <button class="btn btn--ghost" onclick="window.print()"><?= ico('print',16) ?> Štampaj</button>
    <?php if (!$zatvoren): ?>
      <form method="post" action="<?= url('dan') ?>" onsubmit="return ukConfirmSubmit(this,'Zatvoriti dan <?= datum($datum) ?>?',{title:'Zatvaranje dana',ok:'Zatvori dan',busy:'Zatvaram…'})">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="zatvori"><input type="hidden" name="dan" value="<?= e($datum) ?>">
        <button class="btn btn--primary"><?= ico('check',16) ?> Zatvori dan</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="print-title" style="display:none">
  <h2><?= e($lokal['naziv']) ?> — Dnevni izveštaj (KPO)</h2>
  <p><?= e(trim(($lokal['adresa']??'').' '.($lokal['grad']??''))) ?> · PIB: <?= e($lokal['pib'] ?: '—') ?> · Datum: <?= datum($datum) ?></p>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">POS promet (računi)</div><div class="stat__value in"><?= novac($pz['iznos']) ?></div>
    <div class="stat__delta muted">Keš <?= novac($pz['kes']) ?> · Kartica <?= novac($pz['kartica']) ?> · <?= $brRacuna ?> računa</div></div>
  <div class="stat"><div class="stat__label">Ručno upisan pazar</div>
    <div class="stat__value <?= abs((float)$rucno['iznos']-(float)$pz['iznos'])<0.01?'in':'' ?>"><?= novac($rucno['iznos']) ?></div>
    <div class="stat__delta <?= abs((float)$rucno['iznos']-(float)$pz['iznos'])<0.01?'up':'' ?>"><?= (float)$rucno['iznos']==0 ? 'nije upisan' : (abs((float)$rucno['iznos']-(float)$pz['iznos'])<0.01 ? 'poklapa se sa POS-om' : 'razlika '.novac((float)$rucno['iznos']-(float)$pz['iznos'])) ?></div></div>
  <div class="stat"><div class="stat__label">Vrednost izlaza (roba)</div><div class="stat__value"><?= novac($sumPromet) ?></div></div>
</div>

<?php if (!$jeDanas): ?><div class="flash flash--info no-print" style="margin-bottom:14px">Napomena: „Stanje" je trenutno stanje zalihe. Za tačno stanje na kraj tog dana zatvaraj dan na sam dan.</div><?php endif; ?>

<div class="card">
  <div class="card__head no-print"><div class="card__title">Roba — kretanje za <?= datum($datum) ?></div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Artikal</th><th>JM</th><th class="num">Početno</th><th class="num">Ulaz</th><th class="num">Izlaz</th><th class="num">Stanje</th><th class="num">Cena</th><th class="num">Promet</th></tr></thead>
    <tbody>
    <?php if (!$redovi): ?><tr><td colspan="8"><div class="empty">Nema prometa/zaliha za ovaj dan.</div></td></tr>
    <?php else: foreach ($redovi as $r): ?>
      <tr>
        <td><strong><?= e($r['a']['naziv']) ?></strong></td>
        <td class="muted"><?= e($r['a']['jedinica_mere']) ?></td>
        <td class="num muted"><?= kol3($r['pocetno']) ?></td>
        <td class="num <?= $r['ul']>0?'in':'muted' ?>"><?= $r['ul']>0?kol3($r['ul']):'—' ?></td>
        <td class="num <?= $r['iz']>0?'out':'muted' ?>"><?= $r['iz']>0?kol3($r['iz']):'—' ?></td>
        <td class="num"><strong><?= kol3($r['stanje']) ?></strong></td>
        <td class="num muted"><?= novac($r['cena']) ?></td>
        <td class="num"><?= $r['promet']>0?novac($r['promet']):'—' ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if ($redovi): ?><tfoot><tr><td colspan="7" class="num"><strong>UKUPAN PROMET (novac)</strong></td><td class="num"><strong><?= novac($pz['iznos']) ?></strong></td></tr></tfoot><?php endif; ?>
  </table></div>
</div>

<div class="no-print" style="text-align:right;margin-top:10px"><a class="btn btn--ghost btn--sm" href="<?= url('kep') ?>"><?= ico('receipt',15) ?> KEP knjiga (godišnje)</a></div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
