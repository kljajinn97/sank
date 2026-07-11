<?php
/** Fakture — prijem robe od dobavljača, plaćanja, veza sa zalihama */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$uid = current_user()['id'];
$mozeMenjati = user_has_role(['vlasnik','menadzer']);

/** Preračun statusa fakture po plaćenom iznosu */
function fakt_status(float $iznos, float $placeno): string {
    if ($placeno <= 0) return 'neplacena';
    if ($placeno + 0.001 >= $iznos) return 'placena';
    return 'delimicno';
}

// ---------------- Akcije ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mozeMenjati) {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'nova') {
        $dobId = (int)($_POST['dobavljac_id'] ?? 0) ?: null;
        $broj  = post('broj');
        $datum = post('datum') ?: date('Y-m-d');
        $rok   = post('rok_placanja') ?: null;
        $napomena = post('napomena') ?: null;

        $nazivi = $_POST['s_naziv'] ?? [];
        $artIds = $_POST['s_artikal'] ?? [];
        $jms    = $_POST['s_jm'] ?? [];
        $kolic  = $_POST['s_kolicina'] ?? [];
        $cene   = $_POST['s_cena'] ?? [];

        if ($broj === '') { flash('error','Unesi broj fakture.'); redirect(url('fakture')); }

        // Sastavi stavke
        $stavke = []; $ukupno = 0.0;
        foreach ($nazivi as $i => $nz) {
            $nz = trim((string)$nz);
            $kol = to_num($kolic[$i] ?? 0);
            $cen = to_num($cene[$i] ?? 0);
            if ($nz === '' && $kol == 0) continue;
            if ($nz === '') continue;
            $iznos = round($kol * $cen, 2);
            $ukupno += $iznos;
            $stavke[] = [
                'naziv' => $nz,
                'artikal_id' => (int)($artIds[$i] ?? 0) ?: null,
                'jm' => trim((string)($jms[$i] ?? 'kom')) ?: 'kom',
                'kolicina' => $kol, 'cena' => $cen, 'iznos' => $iznos,
            ];
        }
        if (!$stavke) { flash('error','Dodaj bar jednu stavku fakture.'); redirect(url('fakture')); }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_run('INSERT INTO fakture (lokal_id,dobavljac_id,broj,datum,rok_placanja,iznos,placeno,status,napomena,korisnik_id)
                    VALUES (?,?,?,?,?,?,0,"neplacena",?,?)',
                    [$lid,$dobId,$broj,$datum,$rok,$ukupno,$napomena,$uid]);
            $fid = (int)$pdo->lastInsertId();

            foreach ($stavke as $s) {
                db_run('INSERT INTO faktura_stavke (faktura_id,artikal_id,naziv,jedinica_mere,kolicina,cena,iznos)
                        VALUES (?,?,?,?,?,?,?)',
                        [$fid,$s['artikal_id'],$s['naziv'],$s['jm'],$s['kolicina'],$s['cena'],$s['iznos']]);
                // Ako je povezano sa artiklom → povećaj zalihu i zabeleži promet
                if ($s['artikal_id'] && $s['kolicina'] > 0) {
                    db_run('UPDATE artikli SET zaliha = zaliha + ?, nabavna_cena = IF(?>0,?,nabavna_cena)
                            WHERE id=? AND lokal_id=?',
                            [$s['kolicina'],$s['cena'],$s['cena'],$s['artikal_id'],$lid]);
                    db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,faktura_id,korisnik_id)
                            VALUES (?,?,"ulaz",?,?,?,?)',
                            [$lid,$s['artikal_id'],$s['kolicina'],'Faktura '.$broj,$fid,$uid]);
                }
            }
            $pdo->commit();
            flash('success','Faktura '.$broj.' je uneta ('.novac($ukupno).').');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('error','Greška: '.$ex->getMessage());
        }
        redirect(url('fakture'));
    }

    if ($akcija === 'placanje') {
        $fid = (int)($_POST['id'] ?? 0);
        $f = db_row('SELECT * FROM fakture WHERE id=? AND lokal_id=?', [$fid,$lid]);
        if ($f) {
            $puno = ($_POST['puno'] ?? '') === '1';
            $novoPlaceno = $puno ? (float)$f['iznos'] : min((float)$f['iznos'], (float)$f['placeno'] + to_num($_POST['iznos'] ?? 0));
            $status = fakt_status((float)$f['iznos'], $novoPlaceno);
            db_run('UPDATE fakture SET placeno=?, status=? WHERE id=? AND lokal_id=?', [$novoPlaceno,$status,$fid,$lid]);
            flash('success','Plaćanje je evidentirano.');
        }
        redirect(urlq('fakture', ['view'=>$fid]));
    }

    if ($akcija === 'obrisi') {
        $fid = (int)($_POST['id'] ?? 0);
        $fbroj = (string)db_val('SELECT broj FROM fakture WHERE id=? AND lokal_id=?', [$fid,$lid]);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Vrati zalihe za povezane stavke
            $st = db_all('SELECT artikal_id,kolicina FROM faktura_stavke WHERE faktura_id=? AND artikal_id IS NOT NULL', [$fid]);
            foreach ($st as $s) {
                db_run('UPDATE artikli SET zaliha = zaliha - ? WHERE id=? AND lokal_id=?', [$s['kolicina'],$s['artikal_id'],$lid]);
            }
            db_run('DELETE FROM zalihe_promet WHERE faktura_id=? AND lokal_id=?', [$fid,$lid]);
            db_run('DELETE FROM fakture WHERE id=? AND lokal_id=?', [$fid,$lid]);
            $pdo->commit();
            audit('brisanje','faktura',$fid,'Faktura '.$fbroj);
            flash('success','Faktura je obrisana i zalihe su vraćene.');
        } catch (Throwable $ex) { $pdo->rollBack(); flash('error','Greška: '.$ex->getMessage()); }
        redirect(url('fakture'));
    }
}

// url() helper sa query
function urlq(string $path, array $q): string { return url($path) . (($qs = http_build_query($q)) ? '?'.$qs : ''); }

$dobavljaci = db_all('SELECT id,naziv FROM dobavljaci WHERE lokal_id=? ORDER BY naziv', [$lid]);
$artikli    = db_all('SELECT id,naziv,jedinica_mere,nabavna_cena FROM artikli WHERE lokal_id=? AND aktivan=1 ORDER BY naziv', [$lid]);

$page_title = 'Fakture';
$active = 'fakture';

// ======================= DETALJ FAKTURE =======================
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId) {
    $f = db_row('SELECT f.*, d.naziv AS dob_naziv FROM fakture f LEFT JOIN dobavljaci d ON d.id=f.dobavljac_id
                 WHERE f.id=? AND f.lokal_id=?', [$viewId,$lid]);
    if (!$f) { flash('error','Faktura ne postoji.'); redirect(url('fakture')); }
    $stavke = db_all('SELECT * FROM faktura_stavke WHERE faktura_id=? ORDER BY id', [$viewId]);
    $dug = (float)$f['iznos'] - (float)$f['placeno'];

    require __DIR__ . '/../partials/layout_top.php';
    ?>
    <div class="page-head">
      <div>
        <a href="<?= url('fakture') ?>" class="muted" style="font-size:.85rem">← Sve fakture</a>
        <h1>Faktura <?= e($f['broj']) ?></h1>
        <p><?= e($f['dob_naziv'] ?: 'Bez dobavljača') ?> · <?= datum($f['datum']) ?></p>
      </div>
      <div class="flex gap-2 items-center">
        <?php
          $b = ['neplacena'=>'danger','delimicno'=>'warn','placena'=>'ok'][$f['status']];
          $lbl = ['neplacena'=>'Neplaćena','delimicno'=>'Delimično','placena'=>'Plaćena'][$f['status']];
        ?>
        <span class="badge badge--<?= $b ?>"><?= $lbl ?></span>
      </div>
    </div>

    <div class="grid-2 mb-2">
      <div class="card"><div class="card__body">
        <div class="stat__label">Iznos fakture</div>
        <div class="stat__value"><?= novac($f['iznos']) ?></div>
        <div style="margin-top:14px" class="muted">
          Plaćeno: <strong class="in"><?= novac($f['placeno']) ?></strong> ·
          Preostalo: <strong class="<?= $dug>0?'out':'in' ?>"><?= novac(max(0,$dug)) ?></strong>
        </div>
        <div class="progress" style="margin-top:10px"><span style="width:<?= $f['iznos']>0?min(100,round($f['placeno']/$f['iznos']*100)):100 ?>%"></span></div>
      </div></div>
      <div class="card"><div class="card__body">
        <div class="grid-2">
          <div><div class="stat__label">Datum fakture</div><div style="font-weight:600"><?= datum($f['datum']) ?></div></div>
          <div><div class="stat__label">Rok plaćanja</div><div style="font-weight:600"><?= $f['rok_placanja']?datum($f['rok_placanja']):'—' ?></div></div>
        </div>
        <?php if ($mozeMenjati && $f['status']!=='placena'): ?>
        <form method="post" style="margin-top:16px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?><input type="hidden" name="akcija" value="placanje"><input type="hidden" name="id" value="<?= $f['id'] ?>">
          <div class="field" style="margin:0;flex:1;min-width:120px"><label class="label">Uplata (RSD)</label>
            <input class="input" type="number" step="0.01" name="iznos" placeholder="npr. <?= (int)$dug ?>"></div>
          <button class="btn btn--ghost">Evidentiraj</button>
          <button class="btn btn--primary" name="puno" value="1">Plaćeno u celosti</button>
        </form>
        <?php elseif ($f['status']==='placena'): ?>
          <div class="flash flash--success" style="margin-top:16px"><?= ico('check',15) ?> Faktura je u potpunosti plaćena.</div>
        <?php endif; ?>
      </div></div>
    </div>

    <div class="card">
      <div class="card__head"><div class="card__title">Stavke fakture</div>
        <?php if ($mozeMenjati): ?>
        <form method="post" onsubmit="return ukConfirmSubmit(this,'Obrisati celu fakturu? Zalihe će biti vraćene.',{danger:true,ok:'Obriši'})">
          <?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $f['id'] ?>">
          <button class="btn btn--ghost btn--sm" style="color:var(--danger)">Obriši fakturu</button>
        </form>
        <?php endif; ?>
      </div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Artikal</th><th class="num">Količina</th><th>JM</th><th class="num">Cena</th><th class="num">Iznos</th></tr></thead>
        <tbody>
        <?php foreach ($stavke as $s): ?>
          <tr>
            <td><strong><?= e($s['naziv']) ?></strong> <?= $s['artikal_id']?'<span class="badge badge--teal">zaliha</span>':'' ?></td>
            <td class="num"><?= rtrim(rtrim(number_format((float)$s['kolicina'],3,',','.'),'0'),',') ?></td>
            <td class="muted"><?= e($s['jedinica_mere']) ?></td>
            <td class="num"><?= novac($s['cena']) ?></td>
            <td class="num"><?= novac($s['iznos']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="num"><strong>UKUPNO</strong></td><td class="num"><strong><?= novac($f['iznos']) ?></strong></td></tr></tfoot>
      </table></div>
    </div>
    <?php
    require __DIR__ . '/../partials/layout_bottom.php';
    return;
}

// ======================= LISTA FAKTURA =======================
$tab = $_GET['tab'] ?? 'sve';
$where = 'f.lokal_id=?'; $par = [$lid];
if ($tab === 'neplacene') { $where .= ' AND f.status<>"placena"'; }
elseif ($tab === 'placene') { $where .= ' AND f.status="placena"'; }

$fakture = db_all("SELECT f.*, d.naziv AS dob_naziv FROM fakture f
                   LEFT JOIN dobavljaci d ON d.id=f.dobavljac_id
                   WHERE $where ORDER BY f.datum DESC, f.id DESC", $par);

$ukupnoNeplaceno = (float)db_val('SELECT COALESCE(SUM(iznos-placeno),0) FROM fakture WHERE lokal_id=? AND status<>"placena"', [$lid]);
$brNeplacenih = (int)db_val('SELECT COUNT(*) FROM fakture WHERE lokal_id=? AND status<>"placena"', [$lid]);
$mesecNabavka = (float)db_val('SELECT COALESCE(SUM(iznos),0) FROM fakture WHERE lokal_id=? AND YEAR(datum)=YEAR(CURDATE()) AND MONTH(datum)=MONTH(CURDATE())', [$lid]);

require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Fakture</h1><p>Prijem robe od dobavljača i evidencija plaćanja.</p></div>
  <?php if ($mozeMenjati): ?><button class="btn btn--primary" onclick="mFaktura.showModal()">+ Nova faktura</button><?php endif; ?>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Neplaćeno ukupno</div><div class="stat__value out"><?= novac($ukupnoNeplaceno) ?></div>
    <div class="stat__delta"><?= $brNeplacenih ?> neplaćenih faktura</div></div>
  <div class="stat"><div class="stat__label">Nabavka ovog meseca</div><div class="stat__value"><?= novac($mesecNabavka) ?></div></div>
</div>

<div class="toolbar">
  <div class="tabs">
    <a href="<?= urlq('fakture',['tab'=>'sve']) ?>" class="<?= $tab==='sve'?'is-active':'' ?>">Sve</a>
    <a href="<?= urlq('fakture',['tab'=>'neplacene']) ?>" class="<?= $tab==='neplacene'?'is-active':'' ?>">Neplaćene</a>
    <a href="<?= urlq('fakture',['tab'=>'placene']) ?>" class="<?= $tab==='placene'?'is-active':'' ?>">Plaćene</a>
  </div>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Broj</th><th>Dobavljač</th><th>Datum</th><th>Rok</th><th class="num">Iznos</th><th class="num">Plaćeno</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$fakture): ?>
      <tr><td colspan="8"><div class="empty">Nema faktura u ovoj kategoriji.</div></td></tr>
    <?php else: foreach ($fakture as $f):
      $b = ['neplacena'=>'danger','delimicno'=>'warn','placena'=>'ok'][$f['status']];
      $lbl = ['neplacena'=>'Neplaćena','delimicno'=>'Delimično','placena'=>'Plaćena'][$f['status']];
      $kasni = $f['rok_placanja'] && $f['status']!=='placena' && strtotime($f['rok_placanja'])<strtotime('today');
    ?>
      <tr>
        <td><a href="<?= urlq('fakture',['view'=>$f['id']]) ?>"><strong><?= e($f['broj']) ?></strong></a></td>
        <td><?= e($f['dob_naziv'] ?: '—') ?></td>
        <td class="muted"><?= datum($f['datum']) ?></td>
        <td><?= $f['rok_placanja'] ? (($kasni?'<span class="out">':'<span class="muted">').datum($f['rok_placanja']).'</span>') : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= novac($f['iznos']) ?></td>
        <td class="num"><?= novac($f['placeno']) ?></td>
        <td><span class="badge badge--<?= $b ?>"><?= $lbl ?></span></td>
        <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= urlq('fakture',['view'=>$f['id']]) ?>">Detalji →</a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<?php if ($mozeMenjati): ?>
<dialog id="mFaktura" class="modal modal--wide">
  <form method="post" action="<?= url('fakture') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="nova">
    <div class="card__head"><div class="card__title">Nova faktura (prijem robe)</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mFaktura.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Dobavljač</label>
          <select class="select" name="dobavljac_id">
            <option value="0">— izaberi —</option>
            <?php foreach ($dobavljaci as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['naziv']) ?></option><?php endforeach; ?>
          </select>
          <?php if(!$dobavljaci):?><div class="help">Nemaš dobavljače — možeš ih dodati u sekciji Dobavljači.</div><?php endif;?>
        </div>
        <div class="field"><label class="label">Broj fakture *</label><input class="input" name="broj" required placeholder="npr. 2024-1234"></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Datum fakture</label><input class="input" type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label class="label">Rok plaćanja</label><input class="input" type="date" name="rok_placanja"></div>
      </div>

      <div class="modal__section-title">Stavke</div>
      <div class="table-wrap">
        <table class="stavke" id="stavkeTbl">
          <thead><tr><th style="width:34%">Artikal</th><th style="width:14%">Količina</th><th style="width:12%">JM</th><th style="width:18%">Cena</th><th style="width:18%">Iznos</th><th></th></tr></thead>
          <tbody id="stavkeBody"></tbody>
        </table>
      </div>
      <button type="button" class="btn btn--ghost btn--sm" style="margin-top:10px" onclick="dodajStavku()">+ Dodaj stavku</button>

      <div style="text-align:right;margin-top:16px;font-size:1.1rem">Ukupno: <strong id="fUkupno">0,00 RSD</strong></div>

      <div class="field" style="margin-top:12px"><label class="label">Napomena</label><input class="input" name="napomena"></div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mFaktura.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj fakturu</button>
    </div>
  </form>
</dialog>
<script>
const ARTIKLI = <?= json_encode($artikli, JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function artikliOptions(){
  let o = '<option value="0">— slobodan unos —</option>';
  for (const a of ARTIKLI) o += `<option value="${a.id}" data-jm="${a.jedinica_mere}" data-cena="${a.nabavna_cena}">${a.naziv.replace(/"/g,'&quot;')}</option>`;
  return o;
}
function redRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="input" name="s_artikal[]" onchange="izaberiArtikal(this)">${artikliOptions()}</select>
      <input class="input" name="s_naziv[]" placeholder="Naziv" style="margin-top:4px">
    </td>
    <td><input class="input num-in" type="number" step="0.001" name="s_kolicina[]" value="1" oninput="preracun()"></td>
    <td><input class="input" name="s_jm[]" value="kom" style="width:70px"></td>
    <td><input class="input num-in" type="number" step="0.01" name="s_cena[]" value="0" oninput="preracun()"></td>
    <td class="iznos num" style="text-align:right;padding-right:8px">0,00</td>
    <td><button type="button" class="btn-del" onclick="this.closest('tr').remove();preracun()">×</button></td>`;
  return tr;
}
function dodajStavku(){ document.getElementById('stavkeBody').appendChild(redRow()); }
function izaberiArtikal(sel){
  const tr = sel.closest('tr'); const opt = sel.selectedOptions[0];
  if (sel.value !== '0'){
    tr.querySelector('[name="s_naziv[]"]').value = opt.textContent;
    tr.querySelector('[name="s_jm[]"]').value = opt.dataset.jm || 'kom';
    tr.querySelector('[name="s_cena[]"]').value = opt.dataset.cena || 0;
  }
  preracun();
}
function fmt(n){ return n.toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function preracun(){
  let uk=0;
  document.querySelectorAll('#stavkeBody tr').forEach(tr=>{
    const k=parseFloat(tr.querySelector('[name="s_kolicina[]"]').value)||0;
    const c=parseFloat(tr.querySelector('[name="s_cena[]"]').value)||0;
    const iz=k*c; uk+=iz;
    tr.querySelector('.iznos').textContent=fmt(iz);
  });
  document.getElementById('fUkupno').textContent=fmt(uk)+' RSD';
}
dodajStavku();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
