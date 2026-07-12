<?php
/** Artikli i cenovnik */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$mozeMenjati = user_has_role(['vlasnik','menadzer']);

// ---- Akcije ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mozeMenjati) {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'sacuvaj') {
        $id      = (int)($_POST['id'] ?? 0);
        $naziv   = post('naziv');
        $opis    = post('opis') ?: null;
        $jm      = post('jedinica_mere') ?: 'kom';
        $nab     = to_num($_POST['nabavna_cena'] ?? 0);
        $pro     = to_num($_POST['prodajna_cena'] ?? 0);
        $min     = to_num($_POST['min_zaliha'] ?? 0);
        $katId   = (int)($_POST['kategorija_id'] ?? 0) ?: null;
        $novaKat = post('nova_kategorija');
        $boja    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['boja'] ?? '') ? $_POST['boja'] : null;
        $oznaka  = in_array($_POST['poreska_oznaka'] ?? '', ['Ђ','Е','А'], true) ? $_POST['poreska_oznaka'] : 'Ђ';

        if ($naziv === '') { flash('error','Naziv artikla je obavezan.'); redirect(url('artikli')); }

        if ($novaKat !== '') {
            db_run('INSERT INTO kategorije (lokal_id,naziv) VALUES (?,?)', [$lid,$novaKat]);
            $katId = (int)db()->lastInsertId();
        }

        if ($id > 0) {
            $staraCena = (float)db_val('SELECT prodajna_cena FROM artikli WHERE id=? AND lokal_id=?', [$id,$lid]);
            db_run('UPDATE artikli SET naziv=?, opis=?, jedinica_mere=?, nabavna_cena=?, prodajna_cena=?, min_zaliha=?, kategorija_id=?, boja=?, poreska_oznaka=?
                    WHERE id=? AND lokal_id=?', [$naziv,$opis,$jm,$nab,$pro,$min,$katId,$boja,$oznaka,$id,$lid]);
            if ($staraCena != $pro) audit('izmena_cene','artikal',$id, novac($staraCena).' → '.novac($pro).' · '.$naziv);
        } else {
            db_run('INSERT INTO artikli (lokal_id,kategorija_id,naziv,opis,jedinica_mere,nabavna_cena,prodajna_cena,min_zaliha,boja,poreska_oznaka)
                    VALUES (?,?,?,?,?,?,?,?,?,?)', [$lid,$katId,$naziv,$opis,$jm,$nab,$pro,$min,$boja,$oznaka]);
            $id = (int)db()->lastInsertId();
        }

        // Slika: upload ili uklanjanje
        if (isset($_POST['ukloni_sliku'])) {
            db_run('UPDATE artikli SET slika=NULL WHERE id=? AND lokal_id=?', [$id,$lid]);
        } elseif (!empty($_FILES['slika']['tmp_name']) && is_uploaded_file($_FILES['slika']['tmp_name'])) {
            $info = @getimagesize($_FILES['slika']['tmp_name']);
            if ($info && $_FILES['slika']['size'] <= 400*1024) {
                $uri = 'data:'.$info['mime'].';base64,'.base64_encode(file_get_contents($_FILES['slika']['tmp_name']));
                db_run('UPDATE artikli SET slika=? WHERE id=? AND lokal_id=?', [$uri,$id,$lid]);
            } else {
                flash('error','Slika mora biti do 400 KB.');
            }
        }
        flash('success','Artikal je sačuvan.');
        redirect(url('artikli').'?prikaz='.($_POST['prikaz'] ?? 'mreza'));
    }

    if ($akcija === 'obrisi') {
        $aid = (int)$_POST['id'];
        $naz = (string)db_val('SELECT naziv FROM artikli WHERE id=? AND lokal_id=?', [$aid,$lid]);
        db_run('DELETE FROM artikli WHERE id=? AND lokal_id=?', [$aid,$lid]);
        audit('brisanje','artikal',$aid,$naz);
        flash('success','Artikal je obrisan.');
        redirect(url('artikli'));
    }
}

// ---- Podaci ----
$kategorije = db_all('SELECT * FROM kategorije WHERE lokal_id=? ORDER BY naziv', [$lid]);
$fkat = (int)($_GET['kat'] ?? 0);
$pretraga = trim($_GET['q'] ?? '');
$prikaz = ($_GET['prikaz'] ?? 'mreza') === 'tabela' ? 'tabela' : 'mreza';

$sql = 'SELECT a.*, k.naziv AS kat_naziv, k.boja AS kat_boja FROM artikli a
        LEFT JOIN kategorije k ON k.id=a.kategorija_id WHERE a.lokal_id=?';
$par = [$lid];
if ($fkat) { $sql .= ' AND a.kategorija_id=?'; $par[] = $fkat; }
if ($pretraga !== '') { $sql .= ' AND a.naziv LIKE ?'; $par[] = '%'.$pretraga.'%'; }
$sql .= ' ORDER BY a.aktivan DESC, a.naziv';
$artikli = db_all($sql, $par);

function tile_boja($a){ return $a['boja'] ?: ($a['kat_boja'] ?: '#b1662c'); }
function kolq($v){ return rtrim(rtrim(number_format((float)$v,3,',','.'),'0'),',') ?: '0'; }

$page_title = 'Artikli i cenovnik';
$active = 'artikli';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Artikli i cenovnik</h1><p>Šifarnik pića i hrane — sa slikama, cenama i maržom.</p></div>
  <?php if ($mozeMenjati): ?><button class="btn btn--primary" onclick="openArtikal()">+ Novi artikal</button><?php endif; ?>
</div>

<form class="toolbar" method="get" action="<?= url('artikli') ?>">
  <div class="toolbar__search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input class="input" name="q" value="<?= e($pretraga) ?>" placeholder="Pretraži artikle…">
  </div>
  <select class="select" name="kat" onchange="this.form.submit()">
    <option value="0">Sve kategorije</option>
    <?php foreach ($kategorije as $k): ?><option value="<?= $k['id'] ?>" <?= $fkat==$k['id']?'selected':'' ?>><?= e($k['naziv']) ?></option><?php endforeach; ?>
  </select>
  <input type="hidden" name="prikaz" value="<?= $prikaz ?>">
  <div class="spacer"></div>
  <div class="tabs">
    <a href="<?= url('artikli') ?>?prikaz=mreza&kat=<?= $fkat ?>&q=<?= urlencode($pretraga) ?>" class="<?= $prikaz==='mreza'?'is-active':'' ?>">▦ Mreža</a>
    <a href="<?= url('artikli') ?>?prikaz=tabela&kat=<?= $fkat ?>&q=<?= urlencode($pretraga) ?>" class="<?= $prikaz==='tabela'?'is-active':'' ?>">☰ Tabela</a>
  </div>
</form>

<?php if (!$artikli): ?>
  <div class="card"><div class="card__body"><div class="empty">Nema artikala. <?= $mozeMenjati?'Klikni „Novi artikal" da dodaš prvi.':'' ?></div></div></div>

<?php elseif ($prikaz === 'mreza'): ?>
  <div class="pgrid">
    <?php foreach ($artikli as $a):
      $nisko = $a['min_zaliha']>0 && $a['zaliha']<=$a['min_zaliha'];
      $style = $a['slika'] ? "background-image:url('".e($a['slika'])."')" : "background:linear-gradient(135deg,".tile_boja($a).",".tile_boja($a)."cc)";
    ?>
      <div class="ptile" <?= $mozeMenjati ? 'onclick=\'openArtikal('.json_encode([
          "id"=>$a["id"],"naziv"=>$a["naziv"],"opis"=>$a["opis"],"jedinica_mere"=>$a["jedinica_mere"],
          "nabavna_cena"=>$a["nabavna_cena"],"prodajna_cena"=>$a["prodajna_cena"],
          "min_zaliha"=>$a["min_zaliha"],"kategorija_id"=>$a["kategorija_id"],"boja"=>$a["boja"],"poreska_oznaka"=>$a["poreska_oznaka"]
        ], JSON_HEX_APOS|JSON_HEX_QUOT).')\'' : '' ?>>
        <div class="ptile__top" style="<?= $style ?>">
          <?php if(!$a['slika']): ?><span class="ptile__initial"><?= e(mb_strtoupper(mb_substr($a['naziv'],0,1))) ?></span><?php endif; ?>
          <?php if($a['kat_naziv']): ?><span class="ptile__cat"><?= e($a['kat_naziv']) ?></span><?php endif; ?>
        </div>
        <div class="ptile__body">
          <div class="ptile__name"><?= e($a['naziv']) ?></div>
          <div class="ptile__sub"><?= e($a['jedinica_mere']) ?> · zaliha <?= kolq($a['zaliha']) ?><?= $nisko?' <span style="color:var(--warn);font-weight:700">nisko</span>':'' ?></div>
          <div class="ptile__price"><?= novac($a['prodajna_cena']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Artikal</th><th>Kategorija</th><th>JM</th><th class="num">Nabavna</th><th class="num">Prodajna</th><th class="num">Marža</th><th class="num">Zaliha</th><?php if($mozeMenjati):?><th></th><?php endif;?></tr></thead>
    <tbody>
    <?php foreach ($artikli as $a):
      $marza = $a['nabavna_cena']>0 ? round(($a['prodajna_cena']-$a['nabavna_cena'])/$a['nabavna_cena']*100) : null;
      $nisko = $a['min_zaliha']>0 && $a['zaliha']<=$a['min_zaliha'];
    ?>
      <tr>
        <td><div class="flex items-center gap-2">
          <span style="width:26px;height:26px;border-radius:7px;flex-shrink:0;<?= $a['slika']?"background:url('".e($a['slika'])."') center/cover":'background:'.tile_boja($a) ?>"></span>
          <strong><?= e($a['naziv']) ?></strong></div></td>
        <td><?= $a['kat_naziv'] ? '<span class="badge badge--teal">'.e($a['kat_naziv']).'</span>' : '<span class="muted">—</span>' ?></td>
        <td class="muted"><?= e($a['jedinica_mere']) ?></td>
        <td class="num"><?= novac($a['nabavna_cena']) ?></td>
        <td class="num"><?= novac($a['prodajna_cena']) ?></td>
        <td class="num"><?= $marza!==null?$marza.'%':'—' ?></td>
        <td class="num"><?= kolq($a['zaliha']) ?> <?php if($nisko):?><span class="badge badge--warn">nisko</span><?php endif;?></td>
        <?php if ($mozeMenjati): ?>
        <td class="text-right" style="white-space:nowrap">
          <button class="btn btn--ghost btn--sm" onclick='openArtikal(<?= json_encode([
              "id"=>$a["id"],"naziv"=>$a["naziv"],"opis"=>$a["opis"],"jedinica_mere"=>$a["jedinica_mere"],
              "nabavna_cena"=>$a["nabavna_cena"],"prodajna_cena"=>$a["prodajna_cena"],
              "min_zaliha"=>$a["min_zaliha"],"kategorija_id"=>$a["kategorija_id"],"boja"=>$a["boja"],"poreska_oznaka"=>$a["poreska_oznaka"]
          ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
          <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati artikal?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
<?php endif; ?>

<?php if ($mozeMenjati): ?>
<dialog id="mArtikal" class="modal">
  <form method="post" action="<?= url('artikli') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj"><input type="hidden" name="id" id="a_id" value="0"><input type="hidden" name="prikaz" value="<?= $prikaz ?>">
    <div class="card__head"><div class="card__title" id="a_title">Novi artikal</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mArtikal.close()">✕</button></div>
    <div class="card__body">
      <div class="field"><label class="label">Naziv artikla *</label><input class="input" name="naziv" id="a_naziv" required placeholder="npr. Coca-Cola 0.33"></div>
      <div class="field"><label class="label">Opis (za QR meni)</label><input class="input" name="opis" id="a_opis" placeholder="npr. gazirani sok 0.33l"></div>
      <div class="form-row">
        <div class="field"><label class="label">Kategorija</label>
          <select class="select" name="kategorija_id" id="a_kat"><option value="0">— bez kategorije —</option>
            <?php foreach ($kategorije as $k): ?><option value="<?= $k['id'] ?>"><?= e($k['naziv']) ?></option><?php endforeach; ?></select>
          <input class="input" name="nova_kategorija" placeholder="ili nova kategorija" style="margin-top:6px"></div>
        <div class="field"><label class="label">Jedinica mere</label>
          <input class="input" name="jedinica_mere" id="a_jm" list="jmList" value="kom">
          <datalist id="jmList"><option>kom</option><option>l</option><option>ml</option><option>kg</option><option>gajba</option><option>flaša</option></datalist></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Nabavna cena</label><input class="input" type="number" step="0.01" name="nabavna_cena" id="a_nab" value="0"></div>
        <div class="field"><label class="label">Prodajna cena</label><input class="input" type="number" step="0.01" name="prodajna_cena" id="a_pro" value="0"></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Minimalna zaliha (alarm)</label><input class="input" type="number" step="0.001" name="min_zaliha" id="a_min" value="0"></div>
        <div class="field"><label class="label">Poreska oznaka (PDV)</label>
          <select class="select" name="poreska_oznaka" id="a_oznaka">
            <option value="Ђ">Ђ — 20% (opšta)</option>
            <option value="Е">Е — 10% (posebna)</option>
            <option value="А">А — 0% / oslobođeno</option>
          </select></div>
      </div>

      <div class="modal__section-title">Izgled</div>
      <div class="form-row">
        <div class="field"><label class="label">Boja pločice</label>
          <input type="color" name="boja" id="a_boja" value="#b1662c" style="width:52px;height:42px;border:1px solid var(--border);border-radius:10px;padding:2px;cursor:pointer">
          <span class="help">Koristi se ako nema slike.</span></div>
        <div class="field"><label class="label">Slika (do 400 KB)</label>
          <input class="input" type="file" name="slika" accept="image/*">
          <label class="flex items-center gap-2" id="a_ukloni_wrap" style="margin-top:6px;display:none;cursor:pointer">
            <input type="checkbox" name="ukloni_sliku" value="1"><span class="help" style="margin:0">Ukloni postojeću sliku</span></label></div>
      </div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mArtikal.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>
<script>
function openArtikal(a){
  a=a||{};
  a_id.value=a.id||0; a_title.textContent=a.id?'Izmena artikla':'Novi artikal';
  a_naziv.value=a.naziv||''; a_opis.value=a.opis||''; a_jm.value=a.jedinica_mere||'kom';
  a_nab.value=a.nabavna_cena||0; a_pro.value=a.prodajna_cena||0; a_min.value=a.min_zaliha||0;
  a_kat.value=a.kategorija_id||0; a_boja.value=a.boja||'#b1662c';
  document.getElementById('a_oznaka').value=a.poreska_oznaka||'Ђ';
  document.getElementById('a_ukloni_wrap').style.display=a.id?'flex':'none';
  mArtikal.showModal();
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
