<?php
/** Plate i doprinosi */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$uid = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'sacuvaj') {
        $id = (int)($_POST['id'] ?? 0);
        $kor = (int)($_POST['korisnik_id'] ?? 0);
        $mes = post('mesec') ?: date('Y-m');
        $neto = to_num($_POST['neto'] ?? 0);
        $dopr = to_num($_POST['doprinosi'] ?? 0);
        $bruto = $neto + $dopr;
        $ok = db_row('SELECT id FROM korisnici WHERE id=? AND lokal_id=?', [$kor,$lid]);
        if (!$ok) { flash('error','Izaberi zaposlenog.'); redirect(url('plate')); }
        if ($id > 0) {
            db_run('UPDATE plate SET korisnik_id=?,mesec=?,neto=?,doprinosi=?,bruto=?,napomena=? WHERE id=? AND lokal_id=?',
                   [$kor,$mes,$neto,$dopr,$bruto,post('napomena')?:null,$id,$lid]);
        } else {
            db_run('INSERT INTO plate (lokal_id,korisnik_id,mesec,neto,doprinosi,bruto,napomena) VALUES (?,?,?,?,?,?,?)',
                   [$lid,$kor,$mes,$neto,$dopr,$bruto,post('napomena')?:null]);
        }
        flash('success','Obračun plate je sačuvan.');
        redirect(url('plate').'?mesec='.$mes);
    }

    if ($akcija === 'isplati') {
        $id = (int)($_POST['id'] ?? 0);
        $p = db_row('SELECT * FROM plate WHERE id=? AND lokal_id=? AND isplaceno=0', [$id,$lid]);
        if ($p) {
            $kor = db_row('SELECT ime,prezime FROM korisnici WHERE id=?', [$p['korisnik_id']]);
            $ime = trim(($kor['ime']??'').' '.($kor['prezime']??''));
            $pdo = db(); $pdo->beginTransaction();
            try {
                db_run('UPDATE plate SET isplaceno=1, datum_isplate=CURDATE() WHERE id=?', [$id]);
                // Kreiraj troškove (plata + doprinosi) — vezani preko napomene
                if ($p['neto'] > 0)
                    db_run('INSERT INTO troskovi (lokal_id,kategorija,naziv,iznos,datum,status,datum_placanja,korisnik_id,napomena)
                            VALUES (?,"plate",?,?,CURDATE(),"placen",CURDATE(),?,?)',
                            [$lid,'Plata '.$ime.' '.$p['mesec'],$p['neto'],$uid,'plata:#'.$id]);
                if ($p['doprinosi'] > 0)
                    db_run('INSERT INTO troskovi (lokal_id,kategorija,naziv,iznos,datum,status,datum_placanja,korisnik_id,napomena)
                            VALUES (?,"doprinosi",?,?,CURDATE(),"placen",CURDATE(),?,?)',
                            [$lid,'Doprinosi '.$ime.' '.$p['mesec'],$p['doprinosi'],$uid,'plata:#'.$id]);
                $pdo->commit();
                flash('success','Plata isplaćena — trošak je evidentiran.');
            } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        }
        redirect(url('plate').'?mesec='.($_POST['mesec']??date('Y-m')));
    }

    if ($akcija === 'ponisti') {
        $id = (int)($_POST['id'] ?? 0);
        db_run('UPDATE plate SET isplaceno=0, datum_isplate=NULL WHERE id=? AND lokal_id=?', [$id,$lid]);
        db_run('DELETE FROM troskovi WHERE lokal_id=? AND napomena=?', [$lid,'plata:#'.$id]);
        flash('success','Isplata je poništena (trošak uklonjen).');
        redirect(url('plate').'?mesec='.($_POST['mesec']??date('Y-m')));
    }

    if ($akcija === 'obrisi') {
        $id = (int)($_POST['id'] ?? 0);
        db_run('DELETE FROM troskovi WHERE lokal_id=? AND napomena=?', [$lid,'plata:#'.$id]);
        db_run('DELETE FROM plate WHERE id=? AND lokal_id=?', [$id,$lid]);
        flash('success','Obračun je obrisan.');
        redirect(url('plate'));
    }
}

$mesec = $_GET['mesec'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/',$mesec)) $mesec = date('Y-m');

$zaposleni = db_all("SELECT id,ime,prezime FROM korisnici WHERE lokal_id=? AND status='aktivan' ORDER BY ime", [$lid]);
$plate = db_all('SELECT p.*, k.ime, k.prezime FROM plate p JOIN korisnici k ON k.id=p.korisnik_id
                 WHERE p.lokal_id=? AND p.mesec=? ORDER BY k.ime', [$lid,$mesec]);
$sumNeto=0;$sumDopr=0;$sumBruto=0;
foreach($plate as $p){ $sumNeto+=$p['neto']; $sumDopr+=$p['doprinosi']; $sumBruto+=$p['bruto']; }

$page_title = 'Plate i doprinosi';
$active = 'plate';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Plate i doprinosi</h1><p>Mesečni obračun. Isplata automatski ide u troškove.</p></div>
  <button class="btn btn--primary" onclick="openPlata()">+ Novi obračun</button>
</div>

<form class="toolbar" method="get" action="<?= url('plate') ?>">
  <label class="label" style="margin:0">Mesec:</label>
  <input class="input" type="month" name="mesec" value="<?= e($mesec) ?>" onchange="this.form.submit()">
</form>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Neto plate</div><div class="stat__value"><?= novac($sumNeto) ?></div></div>
  <div class="stat"><div class="stat__label">Doprinosi</div><div class="stat__value"><?= novac($sumDopr) ?></div></div>
  <div class="stat"><div class="stat__label">Ukupno (bruto)</div><div class="stat__value out"><?= novac($sumBruto) ?></div></div>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Zaposleni</th><th class="num">Neto</th><th class="num">Doprinosi</th><th class="num">Bruto</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if(!$plate): ?><tr><td colspan="6"><div class="empty">Nema obračuna za ovaj mesec. Klikni „Novi obračun".</div></td></tr>
    <?php else: foreach($plate as $p): ?>
      <tr>
        <td><strong><?= e(trim($p['ime'].' '.$p['prezime'])) ?></strong><?php if($p['napomena']):?><div class="muted" style="font-size:.8rem"><?= e($p['napomena']) ?></div><?php endif;?></td>
        <td class="num"><?= novac($p['neto']) ?></td>
        <td class="num muted"><?= novac($p['doprinosi']) ?></td>
        <td class="num"><?= novac($p['bruto']) ?></td>
        <td><span class="badge badge--<?= $p['isplaceno']?'ok':'warn' ?>"><?= $p['isplaceno']?'Isplaćeno '.datum($p['datum_isplate']):'Za isplatu' ?></span></td>
        <td class="text-right" style="white-space:nowrap">
          <?php if(!$p['isplaceno']): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="isplati"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="mesec" value="<?= e($mesec) ?>">
              <button class="btn btn--primary btn--sm">Isplati</button></form>
            <button class="btn btn--ghost btn--sm" onclick='openPlata(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
          <?php else: ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="ponisti"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="mesec" value="<?= e($mesec) ?>">
              <button class="btn btn--ghost btn--sm">Poništi</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati obračun?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<dialog id="mPlata" class="modal">
  <form method="post" action="<?= url('plate') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj"><input type="hidden" name="id" id="pl_id" value="0">
    <div class="card__head"><div class="card__title" id="pl_title">Novi obračun plate</div><button type="button" class="btn btn--ghost btn--sm" onclick="mPlata.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Zaposleni *</label>
          <select class="select" name="korisnik_id" id="pl_kor" required><option value="">— izaberi —</option>
            <?php foreach($zaposleni as $z): ?><option value="<?= $z['id'] ?>"><?= e(trim($z['ime'].' '.$z['prezime'])) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="label">Mesec</label><input class="input" type="month" name="mesec" id="pl_mes" value="<?= e($mesec) ?>"></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Neto plata</label><input class="input" type="number" step="0.01" name="neto" id="pl_neto" value="0" oninput="plBruto()"></div>
        <div class="field"><label class="label">Doprinosi</label><input class="input" type="number" step="0.01" name="doprinosi" id="pl_dopr" value="0" oninput="plBruto()"></div>
      </div>
      <div style="text-align:right;font-size:1.05rem;margin-bottom:6px">Bruto: <strong id="pl_bruto">0,00 RSD</strong></div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena" id="pl_nap"></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mPlata.close()">Otkaži</button><button class="btn btn--primary">Sačuvaj</button></div>
  </form>
</dialog>
<script>
function plBruto(){const n=parseFloat(document.getElementById('pl_neto').value)||0,d=parseFloat(document.getElementById('pl_dopr').value)||0;
  document.getElementById('pl_bruto').textContent=(n+d).toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2})+' RSD';}
function openPlata(p){p=p||{};
  document.getElementById('pl_id').value=p.id||0;document.getElementById('pl_title').textContent=p.id?'Izmena obračuna':'Novi obračun plate';
  document.getElementById('pl_kor').value=p.korisnik_id||'';document.getElementById('pl_mes').value=p.mesec||'<?= e($mesec) ?>';
  document.getElementById('pl_neto').value=p.neto||0;document.getElementById('pl_dopr').value=p.doprinosi||0;document.getElementById('pl_nap').value=p.napomena||'';
  plBruto();mPlata.showModal();}
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
