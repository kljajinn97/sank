<?php
/** Troškovi i računi za objekat */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$uid = current_user()['id'];
$mozeMenjati = user_has_role(['vlasnik','menadzer']);

$KATEGORIJE = [
  'struja'    => ['Struja', '#f59e0b'],
  'voda'      => ['Voda', '#38bdf8'],
  'internet'  => ['Internet / TV', '#6366f1'],
  'telefon'   => ['Telefon', '#8b5cf6'],
  'zakup'     => ['Zakup / kirija', '#0ea5e9'],
  'plate'     => ['Plate', '#16a34a'],
  'doprinosi' => ['Porezi i doprinosi', '#dc2626'],
  'namirnice' => ['Namirnice', '#84cc16'],
  'oprema'    => ['Oprema / održavanje', '#64748b'],
  'porez'     => ['Porez', '#ef4444'],
  'marketing' => ['Marketing', '#ec4899'],
  'ostalo'    => ['Ostalo', '#94a3b8'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mozeMenjati) {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'sacuvaj') {
        $id = (int)($_POST['id'] ?? 0);
        $kat = $_POST['kategorija'] ?? 'ostalo';
        if (!isset($KATEGORIJE[$kat])) $kat = 'ostalo';
        $naziv = post('naziv');
        $iznos = to_num($_POST['iznos'] ?? 0);
        $datum = post('datum') ?: date('Y-m-d');
        $rok = post('rok_placanja') ?: null;
        $pon = isset($_POST['ponavljajuci']) ? 1 : 0;
        $napomena = post('napomena') ?: null;
        if ($naziv === '') { flash('error','Unesi naziv troška.'); redirect(url('troskovi')); }

        if ($id > 0) {
            db_run('UPDATE troskovi SET kategorija=?,naziv=?,iznos=?,datum=?,rok_placanja=?,ponavljajuci=?,napomena=? WHERE id=? AND lokal_id=?',
                   [$kat,$naziv,$iznos,$datum,$rok,$pon,$napomena,$id,$lid]);
            flash('success','Trošak je izmenjen.');
        } else {
            db_run('INSERT INTO troskovi (lokal_id,kategorija,naziv,iznos,datum,rok_placanja,ponavljajuci,napomena,korisnik_id)
                    VALUES (?,?,?,?,?,?,?,?,?)', [$lid,$kat,$naziv,$iznos,$datum,$rok,$pon,$napomena,$uid]);
            flash('success','Trošak je dodat.');
        }
        redirect(url('troskovi'));
    }

    if ($akcija === 'placeno') {
        $id = (int)($_POST['id'] ?? 0);
        db_run('UPDATE troskovi SET status="placen", datum_placanja=CURDATE() WHERE id=? AND lokal_id=?', [$id,$lid]);
        flash('success','Trošak označen kao plaćen.');
        redirect(url('troskovi'));
    }
    if ($akcija === 'vrati') {
        $id = (int)($_POST['id'] ?? 0);
        db_run('UPDATE troskovi SET status="neplacen", datum_placanja=NULL WHERE id=? AND lokal_id=?', [$id,$lid]);
        redirect(url('troskovi'));
    }
    if ($akcija === 'obrisi') {
        $tid = (int)$_POST['id'];
        $tnaz = (string)db_val('SELECT naziv FROM troskovi WHERE id=? AND lokal_id=?', [$tid,$lid]);
        db_run('DELETE FROM troskovi WHERE id=? AND lokal_id=?', [$tid,$lid]);
        audit('brisanje','trosak',$tid,$tnaz);
        flash('success','Trošak je obrisan.');
        redirect(url('troskovi'));
    }
}

function urlq(string $path, array $q): string { return url($path) . (($qs = http_build_query($q)) ? '?'.$qs : ''); }

$tab = $_GET['tab'] ?? 'sve';
$where = 't.lokal_id=?'; $par = [$lid];
if ($tab === 'neplaceni') $where .= ' AND t.status="neplacen"';
elseif ($tab === 'placeni') $where .= ' AND t.status="placen"';

$troskovi = db_all("SELECT t.* FROM troskovi t WHERE $where ORDER BY t.status='placen', COALESCE(t.rok_placanja,t.datum), t.datum DESC", $par);

$neplacenoUk = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM troskovi WHERE lokal_id=? AND status="neplacen"', [$lid]);
$brNeplaceni = (int)db_val('SELECT COUNT(*) FROM troskovi WHERE lokal_id=? AND status="neplacen"', [$lid]);
$mesecUk = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM troskovi WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())', [$lid]);

$page_title = 'Troškovi i računi';
$active = 'troskovi';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Troškovi i računi</h1><p>Režije, plate, porezi i svi ostali troškovi objekta.</p></div>
  <?php if ($mozeMenjati): ?><button class="btn btn--primary" onclick="openTrosak()">+ Novi trošak</button><?php endif; ?>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Neplaćeno ukupno</div><div class="stat__value out"><?= novac($neplacenoUk) ?></div>
    <div class="stat__delta"><?= $brNeplaceni ?> neplaćenih</div></div>
  <div class="stat"><div class="stat__label">Troškovi ovog meseca</div><div class="stat__value"><?= novac($mesecUk) ?></div></div>
</div>

<div class="toolbar">
  <div class="tabs">
    <a href="<?= urlq('troskovi',['tab'=>'sve']) ?>" class="<?= $tab==='sve'?'is-active':'' ?>">Svi</a>
    <a href="<?= urlq('troskovi',['tab'=>'neplaceni']) ?>" class="<?= $tab==='neplaceni'?'is-active':'' ?>">Neplaćeni</a>
    <a href="<?= urlq('troskovi',['tab'=>'placeni']) ?>" class="<?= $tab==='placeni'?'is-active':'' ?>">Plaćeni</a>
  </div>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Trošak</th><th>Kategorija</th><th>Datum</th><th>Rok</th><th class="num">Iznos</th><th>Status</th><?php if($mozeMenjati):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php if (!$troskovi): ?>
      <tr><td colspan="7"><div class="empty">Nema troškova u ovoj kategoriji.</div></td></tr>
    <?php else: foreach ($troskovi as $t):
      [$kLabel,$kColor] = $KATEGORIJE[$t['kategorija']] ?? $KATEGORIJE['ostalo'];
      $kasni = $t['rok_placanja'] && $t['status']==='neplacen' && strtotime($t['rok_placanja'])<strtotime('today');
    ?>
      <tr>
        <td><strong><?= e($t['naziv']) ?></strong>
            <?php if($t['ponavljajuci']):?><span class="badge badge--info">mesečno</span><?php endif;?>
            <?php if($t['napomena']):?><div class="muted" style="font-size:.8rem"><?= e($t['napomena']) ?></div><?php endif;?></td>
        <td><span class="dot-cat" style="background:<?= $kColor ?>"></span><?= e($kLabel) ?></td>
        <td class="muted"><?= datum($t['datum']) ?></td>
        <td><?= $t['rok_placanja'] ? (($kasni?'<span class="out">':'<span class="muted">').datum($t['rok_placanja']).'</span>') : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= novac($t['iznos']) ?></td>
        <td><span class="badge badge--<?= $t['status']==='placen'?'ok':'danger' ?>"><?= $t['status']==='placen'?'Plaćen':'Neplaćen' ?></span></td>
        <?php if ($mozeMenjati): ?>
        <td class="text-right" style="white-space:nowrap">
          <?php if ($t['status']==='neplacen'): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="placeno"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn--primary btn--sm">Plati</button></form>
          <?php else: ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="akcija" value="vrati"><input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn--ghost btn--sm">Poništi</button></form>
          <?php endif; ?>
          <button class="btn btn--ghost btn--sm" onclick='openTrosak(<?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
          <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati trošak?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<?php if ($mozeMenjati): ?>
<dialog id="mTrosak" class="modal">
  <form method="post" action="<?= url('troskovi') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj"><input type="hidden" name="id" id="t_id" value="0">
    <div class="card__head"><div class="card__title" id="t_title">Novi trošak</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mTrosak.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Kategorija</label>
          <select class="select" name="kategorija" id="t_kat">
            <?php foreach ($KATEGORIJE as $k=>$v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label class="label">Iznos (RSD)</label><input class="input" type="number" step="0.01" name="iznos" id="t_iznos" value="0"></div>
      </div>
      <div class="field"><label class="label">Naziv / opis *</label><input class="input" name="naziv" id="t_naziv" required placeholder="npr. EPS struja - jun"></div>
      <div class="form-row">
        <div class="field"><label class="label">Datum</label><input class="input" type="date" name="datum" id="t_datum" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label class="label">Rok plaćanja</label><input class="input" type="date" name="rok_placanja" id="t_rok"></div>
      </div>
      <div class="field"><label class="label">Napomena</label><input class="input" name="napomena" id="t_napomena"></div>
      <label class="flex items-center gap-2" style="cursor:pointer"><input type="checkbox" name="ponavljajuci" id="t_pon" value="1"> <span>Mesečni (ponavljajući) trošak</span></label>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mTrosak.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>
<script>
function openTrosak(t){
  t=t||{};
  t_id.value=t.id||0; t_title.textContent=t.id?'Izmena troška':'Novi trošak';
  t_kat.value=t.kategorija||'struja'; t_iznos.value=t.iznos||0; t_naziv.value=t.naziv||'';
  t_datum.value=t.datum||'<?= date('Y-m-d') ?>'; t_rok.value=t.rok_placanja||''; t_napomena.value=t.napomena||'';
  t_pon.checked=t.ponavljajuci==1;
  mTrosak.showModal();
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
