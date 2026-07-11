<?php
/** Radno vreme / evidencija smena */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    if ($akcija === 'dodaj') {
        $kor = (int)($_POST['korisnik_id'] ?? 0);
        $datum = post('datum') ?: date('Y-m-d');
        $poc = post('pocetak') ?: null;
        $kraj = post('kraj') ?: null;
        $sati = to_num($_POST['sati'] ?? 0);
        if ($sati <= 0 && $poc && $kraj) {
            $t1 = strtotime($poc); $t2 = strtotime($kraj);
            if ($t2 <= $t1) $t2 += 86400; // preko ponoći
            $sati = round(($t2 - $t1) / 3600, 2);
        }
        $ok = db_row('SELECT id FROM korisnici WHERE id=? AND lokal_id=?', [$kor,$lid]);
        if ($ok && ($sati > 0 || $poc)) {
            db_run('INSERT INTO smene (lokal_id,korisnik_id,datum,pocetak,kraj,sati,napomena) VALUES (?,?,?,?,?,?,?)',
                   [$lid,$kor,$datum,$poc,$kraj,$sati,post('napomena') ?: null]);
            flash('success','Smena je evidentirana.');
        } else flash('error','Izaberi zaposlenog i unesi sate ili vreme.');
        redirect(url('smene'));
    }
    if ($akcija === 'obrisi') {
        db_run('DELETE FROM smene WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        redirect(url('smene'));
    }
}

$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-',$mesec)+[1=>0,2=>0]);
if ($gy<2000){ $gy=(int)date('Y'); $gm=(int)date('n'); }

$zaposleni = db_all("SELECT id,ime,prezime FROM korisnici WHERE lokal_id=? AND status='aktivan' ORDER BY ime", [$lid]);
$smene = db_all('SELECT s.*, k.ime, k.prezime FROM smene s JOIN korisnici k ON k.id=s.korisnik_id
                 WHERE s.lokal_id=? AND YEAR(s.datum)=? AND MONTH(s.datum)=? ORDER BY s.datum DESC, s.id DESC', [$lid,$gy,$gm]);
$zbir = db_all('SELECT k.ime, k.prezime, COUNT(*) dana, SUM(s.sati) sati FROM smene s JOIN korisnici k ON k.id=s.korisnik_id
                WHERE s.lokal_id=? AND YEAR(s.datum)=? AND MONTH(s.datum)=? GROUP BY s.korisnik_id ORDER BY sati DESC', [$lid,$gy,$gm]);

$page_title = 'Radno vreme';
$active = 'smene';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Radno vreme</h1><p>Evidencija smena i sati po zaposlenom.</p></div>
  <button class="btn btn--primary" onclick="mSmena.showModal()">+ Nova smena</button>
</div>

<form class="toolbar" method="get" action="<?= url('smene') ?>">
  <label class="label" style="margin:0">Mesec:</label>
  <input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()">
</form>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Smene</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Datum</th><th>Zaposleni</th><th>Vreme</th><th class="num">Sati</th><th></th></tr></thead>
      <tbody>
      <?php if(!$smene): ?><tr><td colspan="5"><div class="empty">Nema evidentiranih smena za ovaj mesec.</div></td></tr>
      <?php else: foreach($smene as $s): ?>
        <tr>
          <td><strong><?= datum($s['datum']) ?></strong></td>
          <td><?= e(trim($s['ime'].' '.$s['prezime'])) ?></td>
          <td class="muted"><?= $s['pocetak']&&$s['kraj'] ? e(substr($s['pocetak'],0,5).'–'.substr($s['kraj'],0,5)) : '—' ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float)$s['sati'],2,',','.'),'0'),',') ?: '0' ?> h</td>
          <td class="text-right"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card__head"><div class="card__title">Zbir po zaposlenom (<?= sprintf('%02d/%04d',$gm,$gy) ?>)</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Zaposleni</th><th class="num">Dana</th><th class="num">Ukupno sati</th></tr></thead>
      <tbody>
      <?php if(!$zbir): ?><tr><td colspan="3"><div class="empty">—</div></td></tr>
      <?php else: foreach($zbir as $z): ?>
        <tr><td><strong><?= e(trim($z['ime'].' '.$z['prezime'])) ?></strong></td>
          <td class="num"><?= (int)$z['dana'] ?></td>
          <td class="num in"><?= rtrim(rtrim(number_format((float)$z['sati'],2,',','.'),'0'),',') ?> h</td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<dialog id="mSmena" class="modal">
  <form method="post" action="<?= url('smene') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="dodaj">
    <div class="card__head"><div class="card__title">Nova smena</div><button type="button" class="btn btn--ghost btn--sm" onclick="mSmena.close()">✕</button></div>
    <div class="card__body">
      <div class="field"><label class="label">Zaposleni *</label>
        <select class="select" name="korisnik_id" required><option value="">— izaberi —</option>
          <?php foreach($zaposleni as $z): ?><option value="<?= $z['id'] ?>"><?= e(trim($z['ime'].' '.$z['prezime'])) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label">Datum</label><input class="input" type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-row">
        <div class="field"><label class="label">Početak</label><input class="input" type="time" name="pocetak" id="sm_p" oninput="smSati()"></div>
        <div class="field"><label class="label">Kraj</label><input class="input" type="time" name="kraj" id="sm_k" oninput="smSati()"></div>
      </div>
      <div class="field"><label class="label">Sati (ili se računa iz vremena)</label><input class="input" type="number" step="0.25" name="sati" id="sm_s" value="0"></div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena"></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mSmena.close()">Otkaži</button><button class="btn btn--primary">Sačuvaj</button></div>
  </form>
</dialog>
<script>
function smSati(){const p=document.getElementById('sm_p').value,k=document.getElementById('sm_k').value;if(p&&k){let[ph,pm]=p.split(':').map(Number),[kh,km]=k.split(':').map(Number);let d=(kh*60+km)-(ph*60+pm);if(d<=0)d+=1440;document.getElementById('sm_s').value=(d/60).toFixed(2);}}
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
