<?php
/** Bakšiš */
require_role(['vlasnik','menadzer','konobar']);
require_modul('baksis');
$lid = current_lokal_id();
$mozeBrisati = user_has_role(['vlasnik','menadzer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    if ($akcija === 'dodaj') {
        $datum = post('datum') ?: date('Y-m-d');
        $kor = (int)($_POST['korisnik_id'] ?? 0) ?: null;
        $iznos = to_num($_POST['iznos'] ?? 0);
        if ($iznos <= 0) { flash('error','Unesi iznos.'); redirect(url('baksis')); }
        if ($kor) { $ok = db_row('SELECT id FROM korisnici WHERE id=? AND lokal_id=?', [$kor,$lid]); if(!$ok) $kor=null; }
        db_run('INSERT INTO baksis (lokal_id,datum,korisnik_id,iznos,napomena) VALUES (?,?,?,?,?)',
               [$lid,$datum,$kor,$iznos,post('napomena')?:null]);
        flash('success','Bakšiš je evidentiran.');
        redirect(url('baksis'));
    }
    if ($akcija === 'obrisi' && $mozeBrisati) {
        db_run('DELETE FROM baksis WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        redirect(url('baksis'));
    }
}

$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-',$mesec)+[1=>0,2=>0]);
if ($gy<2000){ $gy=(int)date('Y'); $gm=(int)date('n'); }

$zaposleni = db_all("SELECT id,ime,prezime FROM korisnici WHERE lokal_id=? AND status='aktivan' ORDER BY ime", [$lid]);
$stavke = db_all('SELECT b.*, k.ime, k.prezime FROM baksis b LEFT JOIN korisnici k ON k.id=b.korisnik_id
                  WHERE b.lokal_id=? AND YEAR(b.datum)=? AND MONTH(b.datum)=? ORDER BY b.datum DESC, b.id DESC', [$lid,$gy,$gm]);
$ukupno = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM baksis WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$gy,$gm]);

$page_title = 'Bakšiš';
$active = 'baksis';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Bakšiš</h1><p>Evidencija napojnica po danu i zaposlenom.</p></div>
  <button class="btn btn--primary" onclick="mBaksis.showModal()">+ Unesi bakšiš</button>
</div>

<form class="toolbar" method="get" action="<?= url('baksis') ?>">
  <label class="label" style="margin:0">Mesec:</label>
  <input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()">
</form>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Ukupno bakšiša (<?= sprintf('%02d/%04d',$gm,$gy) ?>)</div><div class="stat__value in"><?= novac($ukupno) ?></div></div>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Datum</th><th>Zaposleni</th><th>Napomena</th><th class="num">Iznos</th><?php if($mozeBrisati):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php if(!$stavke): ?><tr><td colspan="5"><div class="empty">Nema unosa za ovaj mesec.</div></td></tr>
    <?php else: foreach($stavke as $b): ?>
      <tr>
        <td><strong><?= datum($b['datum']) ?></strong></td>
        <td class="muted"><?= e(trim(($b['ime']??'').' '.($b['prezime']??''))) ?: 'zajednički' ?></td>
        <td class="muted"><?= e($b['napomena'] ?: '') ?></td>
        <td class="num in"><?= novac($b['iznos']) ?></td>
        <?php if($mozeBrisati): ?><td class="text-right"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form></td><?php endif; ?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<dialog id="mBaksis" class="modal">
  <form method="post" action="<?= url('baksis') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="dodaj">
    <div class="card__head"><div class="card__title">Unos bakšiša</div><button type="button" class="btn btn--ghost btn--sm" onclick="mBaksis.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Datum</label><input class="input" type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label class="label">Zaposleni</label>
          <select class="select" name="korisnik_id"><option value="0">Zajednički / nepodeljen</option>
            <?php foreach($zaposleni as $z): ?><option value="<?= $z['id'] ?>"><?= e(trim($z['ime'].' '.$z['prezime'])) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label class="label">Iznos (RSD) *</label><input class="input" type="number" step="0.01" name="iznos" required></div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena"></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mBaksis.close()">Otkaži</button><button class="btn btn--primary">Sačuvaj</button></div>
  </form>
</dialog>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
