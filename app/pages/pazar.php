<?php
/** Dnevni pazar */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$uid = current_user()['id'];
$mozeBrisati = user_has_role(['vlasnik','menadzer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    if ($akcija === 'unos') {
        $datum = post('datum') ?: date('Y-m-d');
        $smena = in_array($_POST['smena'] ?? '', ['prva','druga','cela'], true) ? $_POST['smena'] : 'cela';
        $kes = to_num($_POST['kes'] ?? 0);
        $kartica = to_num($_POST['kartica'] ?? 0);
        $iznos = $kes + $kartica;
        $napomena = post('napomena') ?: null;
        if ($iznos <= 0) { flash('error','Unesi iznos pazara (keš i/ili kartica).'); redirect(url('pazar')); }
        db_run('INSERT INTO pazar (lokal_id,datum,smena,korisnik_id,iznos,kes,kartica,napomena) VALUES (?,?,?,?,?,?,?,?)',
               [$lid,$datum,$smena,$uid,$iznos,$kes,$kartica,$napomena]);
        flash('success','Pazar je evidentiran ('.novac($iznos).').');
        redirect(url('pazar'));
    }
    if ($akcija === 'obrisi' && $mozeBrisati) {
        db_run('DELETE FROM pazar WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Pazar je obrisan.');
        redirect(url('pazar'));
    }
}

// Filter po mesecu
$mesec = $_GET['mesec'] ?? date('Y-m');
[$gy,$gm] = array_map('intval', explode('-', $mesec) + [1=>0,2=>0]);
if ($gy < 2000) { $gy = (int)date('Y'); $gm = (int)date('n'); }

$stavke = db_all('SELECT p.*, k.ime, k.prezime FROM pazar p LEFT JOIN korisnici k ON k.id=p.korisnik_id
                  WHERE p.lokal_id=? AND YEAR(p.datum)=? AND MONTH(p.datum)=?
                  ORDER BY p.datum DESC, p.id DESC', [$lid,$gy,$gm]);

$mesecUk = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$gy,$gm]);
$danasUk = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM pazar WHERE lokal_id=? AND datum=CURDATE()', [$lid]);
$brDana  = (int)db_val('SELECT COUNT(DISTINCT datum) FROM pazar WHERE lokal_id=? AND YEAR(datum)=? AND MONTH(datum)=?', [$lid,$gy,$gm]);
$prosek  = $brDana > 0 ? $mesecUk / $brDana : 0;

$smeneLbl = ['prva'=>'Prva smena','druga'=>'Druga smena','cela'=>'Ceo dan'];

$page_title = 'Dnevni pazar';
$active = 'pazar';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Dnevni pazar</h1><p>Evidencija dnevnog prometa po smenama.</p></div>
  <button class="btn btn--primary" onclick="mPazar.showModal()">+ Unesi pazar</button>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Pazar danas</div><div class="stat__value in"><?= novac($danasUk) ?></div></div>
  <div class="stat"><div class="stat__label">Ukupno u mesecu</div><div class="stat__value"><?= novac($mesecUk) ?></div></div>
  <div class="stat"><div class="stat__label">Prosek po danu</div><div class="stat__value"><?= novac($prosek) ?></div>
    <div class="stat__delta"><?= $brDana ?> radnih dana</div></div>
</div>

<form class="toolbar" method="get" action="<?= url('pazar') ?>">
  <label class="label" style="margin:0">Mesec:</label>
  <input class="input" type="month" name="mesec" value="<?= e(sprintf('%04d-%02d',$gy,$gm)) ?>" onchange="this.form.submit()">
</form>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Datum</th><th>Smena</th><th>Uneo</th><th class="num">Keš</th><th class="num">Kartica</th><th class="num">Ukupno</th><?php if($mozeBrisati):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php if (!$stavke): ?>
      <tr><td colspan="7"><div class="empty">Nema unosa za ovaj mesec. Klikni „Unesi pazar“.</div></td></tr>
    <?php else: foreach ($stavke as $p): ?>
      <tr>
        <td><strong><?= datum($p['datum']) ?></strong><?php if($p['napomena']):?><div class="muted" style="font-size:.78rem"><?= e($p['napomena']) ?></div><?php endif;?></td>
        <td><span class="badge badge--muted"><?= e($smeneLbl[$p['smena']] ?? $p['smena']) ?></span></td>
        <td class="muted"><?= e(trim(($p['ime']??'').' '.($p['prezime']??''))) ?: '—' ?></td>
        <td class="num muted"><?= novac($p['kes']) ?></td>
        <td class="num muted"><?= novac($p['kartica']) ?></td>
        <td class="num in"><?= novac($p['iznos']) ?></td>
        <?php if ($mozeBrisati): ?>
        <td class="text-right">
          <form method="post" style="display:inline" onsubmit="return confirm('Obrisati ovaj unos?')"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if ($stavke): ?><tfoot><tr><td colspan="5" class="num"><strong>UKUPNO (<?= sprintf('%02d/%04d',$gm,$gy) ?>)</strong></td><td class="num"><strong><?= novac($mesecUk) ?></strong></td><?php if($mozeBrisati):?><td></td><?php endif;?></tr></tfoot><?php endif; ?>
  </table>
</div></div>

<dialog id="mPazar" class="modal">
  <form method="post" action="<?= url('pazar') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="unos">
    <div class="card__head"><div class="card__title">Unos pazara</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mPazar.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Datum</label><input class="input" type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label class="label">Smena</label>
          <select class="select" name="smena"><option value="cela">Ceo dan</option><option value="prva">Prva smena</option><option value="druga">Druga smena</option></select></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Keš (RSD)</label><input class="input" type="number" step="0.01" name="kes" id="p_kes" value="0" oninput="pTotal()"></div>
        <div class="field"><label class="label">Kartica (RSD)</label><input class="input" type="number" step="0.01" name="kartica" id="p_kartica" value="0" oninput="pTotal()"></div>
      </div>
      <div style="text-align:right;font-size:1.05rem;margin-bottom:6px">Ukupno: <strong id="p_total">0,00 RSD</strong></div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena" placeholder="opciono"></div>
      <script>
        function pTotal(){ const k=parseFloat(p_kes.value)||0, c=parseFloat(p_kartica.value)||0;
          p_total.textContent=(k+c).toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2})+' RSD'; }
      </script>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mPazar.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
