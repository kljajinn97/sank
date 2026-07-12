<?php
/** Brzo podešavanje lokala (onboarding) */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

$PREDLOZI = ['Topli napici','Bezalkoholna pića','Pivo','Vino','Žestoka pića','Kokteli','Hrana','Dodaci'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'lokal') {
        $boja = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['boja'] ?? '') ? $_POST['boja'] : '#b1662c';
        db_run('UPDATE lokali SET naziv=?, tip=?, grad=?, boja=? WHERE id=?',
               [post('naziv') ?: 'Moj lokal', post('tip') ?: null, post('grad') ?: null, $boja, $lid]);
        flash('success','Podaci o lokalu su sačuvani.');
        redirect(url('onboarding'));
    }
    if ($akcija === 'kategorije') {
        $izabrane = $_POST['kat'] ?? [];
        $n = 0;
        foreach ($izabrane as $naziv) {
            $naziv = trim((string)$naziv);
            if ($naziv === '') continue;
            $ima = db_val('SELECT COUNT(*) FROM kategorije WHERE lokal_id=? AND naziv=?', [$lid,$naziv]);
            if (!$ima) { db_run('INSERT INTO kategorije (lokal_id,naziv) VALUES (?,?)', [$lid,$naziv]); $n++; }
        }
        flash('success', $n.' kategorija je kreirano.');
        redirect(url('onboarding'));
    }
}

$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);
$brKat = (int)db_val('SELECT COUNT(*) FROM kategorije WHERE lokal_id=?', [$lid]);
$brArt = (int)db_val('SELECT COUNT(*) FROM artikli WHERE lokal_id=?', [$lid]);
$brZap = (int)db_val("SELECT COUNT(*) FROM korisnici WHERE lokal_id=? AND uloga<>'vlasnik'", [$lid]);

$koraci = [
  ['Podaci o lokalu', !empty($lokal['grad']) || !empty($lokal['tip'])],
  ['Kategorije', $brKat > 0],
  ['Artikli', $brArt > 0],
  ['Zaposleni', $brZap > 0],
];
$gotovo = count(array_filter($koraci, fn($k)=>$k[1]));
$procenat = round($gotovo / count($koraci) * 100);

$page_title = 'Brzo podešavanje';
$active = '';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Dobrodošao u Waiter</h1><p>Podesi svoj lokal za par minuta i kreni sa radom.</p></div>
</div>

<div class="card mb-2"><div class="card__body">
  <div class="flex items-center justify-between mb-2">
    <strong>Napredak podešavanja</strong><span class="badge badge--<?= $procenat==100?'ok':'teal' ?>"><?= $gotovo ?>/<?= count($koraci) ?> gotovo</span>
  </div>
  <div class="progress" style="height:10px"><span style="width:<?= $procenat ?>%"></span></div>
  <div class="flex gap-3" style="flex-wrap:wrap;margin-top:14px">
    <?php foreach ($koraci as $k): ?>
      <span class="badge badge--<?= $k[1]?'ok':'muted' ?>"><?= $k[1]?ico('check',12):'' ?> <?= e($k[0]) ?></span>
    <?php endforeach; ?>
  </div>
</div></div>

<div class="grid-2 mb-2">
  <!-- Korak 1: lokal -->
  <div class="card"><div class="card__head"><div class="card__title">1 · Tvoj lokal</div></div>
    <div class="card__body">
      <form method="post" action="<?= url('onboarding') ?>">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="lokal">
        <div class="field"><label class="label">Naziv lokala</label><input class="input" name="naziv" value="<?= e($lokal['naziv']) ?>"></div>
        <div class="form-row">
          <div class="field"><label class="label">Tip</label><input class="input" name="tip" value="<?= e($lokal['tip']) ?>" placeholder="kafić, restoran, bar…"></div>
          <div class="field"><label class="label">Grad</label><input class="input" name="grad" value="<?= e($lokal['grad']) ?>"></div>
        </div>
        <div class="field"><label class="label">Boja brenda</label><br>
          <input type="color" name="boja" value="<?= e($lokal['boja'] ?: '#b1662c') ?>" style="width:56px;height:42px;border:1px solid var(--border);border-radius:10px;padding:2px;cursor:pointer">
          <span class="help">Menja izgled celog sistema.</span></div>
        <button class="btn btn--primary">Sačuvaj</button>
      </form>
    </div>
  </div>

  <!-- Korak 2: kategorije -->
  <div class="card"><div class="card__head"><div class="card__title">2 · Kategorije</div></div>
    <div class="card__body">
      <p class="muted" style="margin-top:0">Izaberi šta prodaješ — kreiraćemo kategorije odjednom.</p>
      <form method="post" action="<?= url('onboarding') ?>">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="kategorije">
        <div class="flex gap-2" style="flex-wrap:wrap;margin-bottom:16px">
          <?php foreach ($PREDLOZI as $p): ?>
            <label class="badge badge--muted" style="cursor:pointer;padding:8px 12px;font-size:.85rem">
              <input type="checkbox" name="kat[]" value="<?= e($p) ?>" style="margin-right:5px"><?= e($p) ?></label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn--primary">Kreiraj kategorije</button>
        <?php if ($brKat): ?><span class="muted" style="margin-left:10px"><?= $brKat ?> već postoji</span><?php endif; ?>
      </form>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card"><div class="card__body flex items-center justify-between">
    <div><strong>3 · Dodaj artikle</strong><div class="muted" style="font-size:.85rem"><?= $brArt ?> artikala</div></div>
    <a class="btn btn--primary" href="<?= url('artikli') ?>">Idi na artikle →</a>
  </div></div>
  <div class="card"><div class="card__body flex items-center justify-between">
    <div><strong>4 · Pozovi zaposlene</strong><div class="muted" style="font-size:.85rem"><?= $brZap ?> naloga</div></div>
    <a class="btn btn--ghost" href="<?= url('korisnici') ?>">Dodaj zaposlene →</a>
  </div></div>
</div>

<div style="text-align:center;margin-top:24px">
  <a class="btn btn--ghost" href="<?= url('dashboard') ?>">Preskoči na kontrolnu tablu →</a>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
