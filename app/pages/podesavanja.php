<?php
/** Podešavanja lokala */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'lokal') {
        $boja = post('boja');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $boja)) $boja = '#0d9488';
        db_run('UPDATE lokali SET naziv=?, tip=?, adresa=?, grad=?, telefon=?, pib=?, valuta=?, pdv_stopa=?, boja=? WHERE id=?',
               [post('naziv'), post('tip') ?: null, post('adresa') ?: null, post('grad') ?: null,
                post('telefon') ?: null, post('pib') ?: null, post('valuta') ?: 'RSD', to_num($_POST['pdv_stopa'] ?? 20), $boja, $lid]);

        // Logo: upload ili uklanjanje
        if (isset($_POST['ukloni_logo'])) {
            db_run('UPDATE lokali SET logo=NULL WHERE id=?', [$lid]);
        } elseif (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $info = @getimagesize($_FILES['logo']['tmp_name']);
            if (!$info) {
                flash('error','Logo mora biti slika (PNG, JPG, SVG…).');
            } elseif ($_FILES['logo']['size'] > 300*1024) {
                flash('error','Logo je prevelik (max 300 KB).');
            } else {
                $data = base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
                $uri = 'data:' . $info['mime'] . ';base64,' . $data;
                db_run('UPDATE lokali SET logo=? WHERE id=?', [$uri, $lid]);
            }
        }
        flash('success','Podaci o lokalu su sačuvani.');
        redirect(url('podesavanja'));
    }
    if ($akcija === 'kat_dodaj') {
        $naziv = post('naziv');
        $boja = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['boja'] ?? '') ? $_POST['boja'] : '#0d9488';
        if ($naziv !== '') { db_run('INSERT INTO kategorije (lokal_id,naziv,boja) VALUES (?,?,?)', [$lid,$naziv,$boja]); flash('success','Kategorija je dodata.'); }
        redirect(url('podesavanja'));
    }
    if ($akcija === 'kat_boja') {
        $boja = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['boja'] ?? '') ? $_POST['boja'] : '#0d9488';
        db_run('UPDATE kategorije SET boja=? WHERE id=? AND lokal_id=?', [$boja,(int)$_POST['id'],$lid]);
        redirect(url('podesavanja'));
    }
    if ($akcija === 'kat_obrisi') {
        db_run('DELETE FROM kategorije WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Kategorija je obrisana.');
        redirect(url('podesavanja'));
    }
}

$lokal = db_row('SELECT * FROM lokali WHERE id=?', [$lid]);
$kategorije = db_all('SELECT k.*, (SELECT COUNT(*) FROM artikli a WHERE a.kategorija_id=k.id) AS br
                      FROM kategorije k WHERE k.lokal_id=? ORDER BY k.naziv', [$lid]);

$page_title = 'Podešavanja lokala';
$active = 'podesavanja';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head"><div><h1>Podešavanja lokala</h1><p>Osnovni podaci i kategorije artikala.</p></div></div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Podaci o lokalu</div></div>
    <div class="card__body">
      <form method="post" action="<?= url('podesavanja') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="lokal">
        <div class="field"><label class="label">Naziv lokala</label><input class="input" name="naziv" value="<?= e($lokal['naziv']) ?>" required></div>
        <div class="form-row">
          <div class="field"><label class="label">Tip</label><input class="input" name="tip" value="<?= e($lokal['tip']) ?>" placeholder="kafić, restoran…"></div>
          <div class="field"><label class="label">Grad</label><input class="input" name="grad" value="<?= e($lokal['grad']) ?>"></div>
        </div>
        <div class="field"><label class="label">Adresa</label><input class="input" name="adresa" value="<?= e($lokal['adresa']) ?>"></div>
        <div class="form-row">
          <div class="field"><label class="label">Telefon</label><input class="input" name="telefon" value="<?= e($lokal['telefon']) ?>"></div>
          <div class="field"><label class="label">PIB</label><input class="input" name="pib" value="<?= e($lokal['pib']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="field"><label class="label">Valuta</label><input class="input" name="valuta" value="<?= e($lokal['valuta'] ?: 'RSD') ?>"></div>
          <div class="field"><label class="label">PDV stopa (%)</label><input class="input" type="number" step="0.01" name="pdv_stopa" value="<?= e(rtrim(rtrim(number_format((float)($lokal['pdv_stopa'] ?? 20),2,'.',''),'0'),'.')) ?>"></div>
        </div>

        <div class="modal__section-title" style="margin-top:8px">Izgled i brendiranje</div>
        <div class="form-row">
          <div class="field"><label class="label">Boja brenda</label>
            <div class="flex gap-2 items-center">
              <input type="color" name="boja" id="bojaInput" value="<?= e($lokal['boja'] ?: '#0d9488') ?>" style="width:52px;height:42px;border:1px solid var(--border);border-radius:10px;background:none;cursor:pointer;padding:2px;">
              <span class="help" style="margin:0">Menja boju celog interfejsa tvog lokala.</span>
            </div>
          </div>
          <div class="field"><label class="label">Logo (max 300 KB)</label>
            <input class="input" type="file" name="logo" accept="image/*">
            <?php if (!empty($lokal['logo'])): ?>
              <label class="flex items-center gap-2" style="margin-top:8px;cursor:pointer">
                <img src="<?= e($lokal['logo']) ?>" alt="logo" style="width:34px;height:34px;border-radius:8px;object-fit:cover;background:#fff;border:1px solid var(--border)">
                <input type="checkbox" name="ukloni_logo" value="1"> <span class="help" style="margin:0">Ukloni logo</span>
              </label>
            <?php endif; ?>
          </div>
        </div>
        <button class="btn btn--primary">Sačuvaj podatke</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><div class="card__title">Kategorije artikala</div></div>
    <div class="card__body">
      <form method="post" action="<?= url('podesavanja') ?>" class="flex gap-2" style="margin-bottom:16px">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="kat_dodaj">
        <input type="color" name="boja" value="#0d9488" style="width:44px;height:42px;border:1px solid var(--border);border-radius:10px;padding:2px;cursor:pointer" title="Boja kategorije">
        <input class="input" name="naziv" placeholder="Nova kategorija (npr. Topli napici)" required>
        <button class="btn btn--primary">Dodaj</button>
      </form>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Boja</th><th>Kategorija</th><th class="num">Artikala</th><th></th></tr></thead>
        <tbody>
        <?php if (!$kategorije): ?>
          <tr><td colspan="4"><div class="empty">Nema kategorija. Dodaj prvu iznad.</div></td></tr>
        <?php else: foreach ($kategorije as $k): ?>
          <tr>
            <td><form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="akcija" value="kat_boja"><input type="hidden" name="id" value="<?= $k['id'] ?>">
              <input type="color" name="boja" value="<?= e($k['boja'] ?: '#0d9488') ?>" onchange="this.form.submit()" style="width:36px;height:30px;border:1px solid var(--border);border-radius:7px;padding:1px;cursor:pointer" title="Promeni boju"></form></td>
            <td><strong><?= e($k['naziv']) ?></strong></td>
            <td class="num"><?= (int)$k['br'] ?></td>
            <td class="text-right">
              <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati kategoriju? Artikli ostaju bez kategorije.',{danger:true,ok:'Obriši'})">
                <?= csrf_field() ?><input type="hidden" name="akcija" value="kat_obrisi"><input type="hidden" name="id" value="<?= $k['id'] ?>">
                <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
