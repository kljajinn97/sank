<?php
/** Podešavanje fiskalizacije (ESIR integracija) */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aktivna = isset($_POST['fisk_aktivna']) ? 1 : 0;
    $mode = in_array($_POST['fisk_mode'] ?? '', ['simulacija','lpfr','vpfr'], true) ? $_POST['fisk_mode'] : 'simulacija';
    db_run('UPDATE lokali SET fisk_aktivna=?, fisk_mode=?, pfr_url=?, esir_broj=?, pdv_obveznik=? WHERE id=?',
           [$aktivna, $mode, post('pfr_url') ?: null, post('esir_broj') ?: null, isset($_POST['pdv_obveznik'])?1:0, $lid]);
    flash('success','Podešavanja fiskalizacije su sačuvana.');
    redirect(url('fiskalizacija'));
}

$l = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);
$brFisk = (int)db_val('SELECT COUNT(*) FROM racuni WHERE lokal_id=? AND fiskalizovan=1', [$lid]);

$page_title = 'Fiskalizacija';
$active = 'fiskalizacija';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1>Fiskalizacija</h1><p>Povezivanje POS-a sa fiskalnim sistemom (ESIR → PFR).</p></div></div>

<div class="card mb-2" style="border-color:var(--warn);background:linear-gradient(120deg,var(--warn-soft),var(--surface))">
  <div class="card__body">
    <strong><?= ico('warn',16) ?> Važno — pravni okvir</strong>
    <p class="muted" style="margin:8px 0 0;font-size:.9rem;line-height:1.6">
      Za legalno izdavanje fiskalnih računa u Srbiji potrebni su: <strong>odobren ESIR</strong> (Poreska uprava),
      <strong>PFR</strong> (L-PFR uređaj ili V-PFR servis) i <strong>bezbednosni element</strong>.
      <strong>Simulacioni režim</strong> služi samo za test toka i <u>ne izdaje važeći fiskalni račun</u>.
      Realni režim (L-PFR/V-PFR) je pripremljen, ali ga treba uskladiti sa aktuelnim SUF uputstvom i tvojim PFR-om,
      pa proći odobrenje pre produkcije.
    </p>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Podešavanja</div></div>
    <div class="card__body">
      <form method="post" action="<?= url('fiskalizacija') ?>">
        <?= csrf_field() ?>
        <label class="flex items-center gap-2" style="cursor:pointer;margin-bottom:16px">
          <input type="checkbox" name="fisk_aktivna" value="1" <?= $l['fisk_aktivna']?'checked':'' ?>>
          <span><strong>Uključi fiskalizaciju</strong> (POS naplata će fiskalizovati račun)</span>
        </label>
        <div class="field"><label class="label">Režim</label>
          <select class="select" name="fisk_mode">
            <option value="simulacija" <?= $l['fisk_mode']==='simulacija'?'selected':'' ?>>Simulacija (test, bez pravog fiskala)</option>
            <option value="lpfr" <?= $l['fisk_mode']==='lpfr'?'selected':'' ?>>L-PFR (lokalni procesor)</option>
            <option value="vpfr" <?= $l['fisk_mode']==='vpfr'?'selected':'' ?>>V-PFR (virtuelni / cloud)</option>
          </select></div>
        <div class="field"><label class="label">PFR adresa (URL)</label>
          <input class="input" name="pfr_url" value="<?= e($l['pfr_url']) ?>" placeholder="npr. http://192.168.1.50:8888 (L-PFR)">
          <div class="help">Adresa L-PFR uređaja u lokalu ili V-PFR endpoint. Ostavi prazno za simulaciju.</div></div>
        <div class="field"><label class="label">ESIR broj / oznaka</label>
          <input class="input" name="esir_broj" value="<?= e($l['esir_broj']) ?>" placeholder="dodeljuje Poreska uprava"></div>
        <label class="flex items-center gap-2" style="cursor:pointer;margin-bottom:16px">
          <input type="checkbox" name="pdv_obveznik" value="1" <?= $l['pdv_obveznik']?'checked':'' ?>>
          <span>U sistemu sam PDV-a (PDV obveznik)</span>
        </label>
        <button class="btn btn--primary">Sačuvaj</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><div class="card__title">Status</div></div>
    <div class="card__body">
      <div class="stat" style="border:none;box-shadow:none;padding:0;margin-bottom:16px">
        <div class="stat__label">Fiskalizacija</div>
        <div class="stat__value"><span class="badge badge--<?= $l['fisk_aktivna']?'ok':'muted' ?>" style="font-size:1rem;padding:6px 14px"><?= $l['fisk_aktivna']?'Uključena':'Isključena' ?></span></div>
      </div>
      <p class="muted" style="font-size:.9rem">Režim: <strong><?= e(strtoupper($l['fisk_mode'])) ?></strong></p>
      <p class="muted" style="font-size:.9rem">Fiskalizovanih računa: <strong><?= $brFisk ?></strong></p>
      <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
      <p class="help">Poreske oznake artikala (Ђ 20% / Е 10% / А 0%) podešavaš u <a href="<?= url('artikli') ?>">Artiklima</a>.</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
