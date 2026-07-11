<?php
/** Dobavljači */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$mozeMenjati = user_has_role(['vlasnik','menadzer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mozeMenjati) {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';
    if ($akcija === 'sacuvaj') {
        $id = (int)($_POST['id'] ?? 0);
        $naziv = post('naziv');
        if ($naziv === '') { flash('error','Naziv dobavljača je obavezan.'); redirect(url('dobavljaci')); }
        $par = [$naziv, post('pib') ?: null, post('telefon') ?: null, post('email') ?: null, post('adresa') ?: null, post('napomena') ?: null];
        if ($id > 0) {
            db_run('UPDATE dobavljaci SET naziv=?,pib=?,telefon=?,email=?,adresa=?,napomena=? WHERE id=? AND lokal_id=?', array_merge($par,[$id,$lid]));
            flash('success','Dobavljač je izmenjen.');
        } else {
            db_run('INSERT INTO dobavljaci (naziv,pib,telefon,email,adresa,napomena,lokal_id) VALUES (?,?,?,?,?,?,?)', array_merge($par,[$lid]));
            flash('success','Dobavljač je dodat.');
        }
        redirect(url('dobavljaci'));
    }
    if ($akcija === 'obrisi') {
        db_run('DELETE FROM dobavljaci WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Dobavljač je obrisan.');
        redirect(url('dobavljaci'));
    }
}

$dobavljaci = db_all(
    'SELECT d.*, (SELECT COUNT(*) FROM fakture f WHERE f.dobavljac_id=d.id) AS br_faktura,
            (SELECT COALESCE(SUM(f.iznos-f.placeno),0) FROM fakture f WHERE f.dobavljac_id=d.id AND f.status<>"placena") AS dug
     FROM dobavljaci d WHERE d.lokal_id=? ORDER BY d.naziv', [$lid]);

$page_title = 'Dobavljači';
$active = 'dobavljaci';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Dobavljači</h1><p>Firme od kojih nabavljaš robu.</p></div>
  <?php if ($mozeMenjati): ?><button class="btn btn--primary" onclick="openDob()">+ Novi dobavljač</button><?php endif; ?>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Dobavljač</th><th>PIB</th><th>Kontakt</th><th class="num">Faktura</th><th class="num">Dugovanje</th><?php if($mozeMenjati):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php if (!$dobavljaci): ?>
      <tr><td colspan="6"><div class="empty">Nema dobavljača. <?= $mozeMenjati?'Dodaj prvog klikom na dugme.':'' ?></div></td></tr>
    <?php else: foreach ($dobavljaci as $d): ?>
      <tr>
        <td><strong><?= e($d['naziv']) ?></strong><?php if($d['adresa']):?><div class="muted" style="font-size:.8rem"><?= e($d['adresa']) ?></div><?php endif;?></td>
        <td class="muted"><?= e($d['pib'] ?: '—') ?></td>
        <td class="muted"><?= e($d['telefon'] ?: $d['email'] ?: '—') ?></td>
        <td class="num"><?= (int)$d['br_faktura'] ?></td>
        <td class="num"><?= $d['dug']>0 ? '<span class="out">'.novac($d['dug']).'</span>' : '<span class="badge badge--ok">nema</span>' ?></td>
        <?php if ($mozeMenjati): ?>
        <td class="text-right" style="white-space:nowrap">
          <button class="btn btn--ghost btn--sm" onclick='openDob(<?= json_encode($d, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
          <form method="post" style="display:inline" onsubmit="return confirm('Obrisati dobavljača?')">
            <?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">Obriši</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<?php if ($mozeMenjati): ?>
<dialog id="mDob" class="modal">
  <form method="post" action="<?= url('dobavljaci') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj"><input type="hidden" name="id" id="d_id" value="0">
    <div class="card__head"><div class="card__title" id="d_title">Novi dobavljač</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mDob.close()">✕</button></div>
    <div class="card__body">
      <div class="field"><label class="label">Naziv *</label><input class="input" name="naziv" id="d_naziv" required></div>
      <div class="form-row">
        <div class="field"><label class="label">PIB</label><input class="input" name="pib" id="d_pib"></div>
        <div class="field"><label class="label">Telefon</label><input class="input" name="telefon" id="d_telefon"></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Email</label><input class="input" type="email" name="email" id="d_email"></div>
        <div class="field"><label class="label">Adresa</label><input class="input" name="adresa" id="d_adresa"></div>
      </div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena" id="d_napomena"></div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mDob.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>
<script>
function openDob(d){
  d=d||{};
  d_id.value=d.id||0; d_title.textContent=d.id?'Izmena dobavljača':'Novi dobavljač';
  d_naziv.value=d.naziv||''; d_pib.value=d.pib||''; d_telefon.value=d.telefon||'';
  d_email.value=d.email||''; d_adresa.value=d.adresa||''; d_napomena.value=d.napomena||'';
  mDob.showModal();
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
