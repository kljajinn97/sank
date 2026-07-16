<?php
/** Narudžbenice (nabavka) + predlog nabavke */
require_role(['vlasnik','menadzer']);
require_modul('narudzbenice');
$lid = current_lokal_id();
$uid = current_user()['id'];

function urlq(string $path, array $q): string { return url($path) . (($qs=http_build_query($q))?'?'.$qs:''); }
$STAT = ['nacrt'=>['Nacrt','muted'],'poslata'=>['Poslata','info'],'primljena'=>['Primljena','ok']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'nova' || $akcija === 'predlog') {
        $dobId = (int)($_POST['dobavljac_id'] ?? 0) ?: null;
        $datum = post('datum') ?: date('Y-m-d');
        $napomena = post('napomena') ?: null;
        $nazivi=$_POST['s_naziv']??[]; $artIds=$_POST['s_artikal']??[]; $jms=$_POST['s_jm']??[]; $kolic=$_POST['s_kolicina']??[]; $cene=$_POST['s_cena']??[];
        $stavke=[]; $ukupno=0.0;
        foreach ($nazivi as $i=>$nz){ $nz=trim((string)$nz); $k=to_num($kolic[$i]??0);
            if($nz===''||$k<=0) continue;
            $c=to_num($cene[$i]??0); $iz=round($k*$c,2); $ukupno+=$iz;
            $stavke[]=['naziv'=>$nz,'artikal_id'=>(int)($artIds[$i]??0)?:null,'jm'=>trim((string)($jms[$i]??'kom'))?:'kom','kolicina'=>$k,'cena'=>$c,'iznos'=>$iz]; }
        if(!$stavke){ flash('error','Dodaj bar jednu stavku.'); redirect(url('narudzbenice')); }
        $pdo=db(); $pdo->beginTransaction();
        try {
            db_run('INSERT INTO narudzbenice (lokal_id,dobavljac_id,datum,status,iznos,napomena,korisnik_id) VALUES (?,?,?,"nacrt",?,?,?)',
                   [$lid,$dobId,$datum,$ukupno,$napomena,$uid]);
            $nid=(int)$pdo->lastInsertId();
            foreach($stavke as $s) db_run('INSERT INTO narudzbenica_stavke (narudzbenica_id,artikal_id,naziv,jedinica_mere,kolicina,cena,iznos) VALUES (?,?,?,?,?,?,?)',
                   [$nid,$s['artikal_id'],$s['naziv'],$s['jm'],$s['kolicina'],$s['cena'],$s['iznos']]);
            $pdo->commit();
            flash('success','Narudžbenica je kreirana.');
            redirect(urlq('narudzbenice',['view'=>$nid]));
        } catch(Throwable $e){ $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); redirect(url('narudzbenice')); }
    }

    if ($akcija === 'status') {
        $nid=(int)($_POST['id']??0); $novi=$_POST['novi']??'nacrt';
        if(isset($STAT[$novi])) db_run('UPDATE narudzbenice SET status=? WHERE id=? AND lokal_id=?', [$novi,$nid,$lid]);
        redirect(urlq('narudzbenice',['view'=>$nid]));
    }

    if ($akcija === 'primi') {
        // Napravi fakturu iz narudžbenice (poveća zalihe) i označi primljenom
        $nid=(int)($_POST['id']??0);
        $n=db_row('SELECT * FROM narudzbenice WHERE id=? AND lokal_id=?', [$nid,$lid]);
        if($n){
            $stavke=db_all('SELECT * FROM narudzbenica_stavke WHERE narudzbenica_id=?', [$nid]);
            $broj=post('broj') ?: ('NAR-'.$nid);
            $pdo=db(); $pdo->beginTransaction();
            try {
                db_run('INSERT INTO fakture (lokal_id,dobavljac_id,broj,datum,iznos,placeno,status,napomena,korisnik_id) VALUES (?,?,?,CURDATE(),?,0,"neplacena",?,?)',
                       [$lid,$n['dobavljac_id'],$broj,$n['iznos'],'Iz narudžbenice #'.$nid,$uid]);
                $fid=(int)$pdo->lastInsertId();
                foreach($stavke as $s){
                    db_run('INSERT INTO faktura_stavke (faktura_id,artikal_id,naziv,jedinica_mere,kolicina,cena,iznos) VALUES (?,?,?,?,?,?,?)',
                           [$fid,$s['artikal_id'],$s['naziv'],$s['jedinica_mere'],$s['kolicina'],$s['cena'],$s['iznos']]);
                    if($s['artikal_id'] && $s['kolicina']>0){
                        db_run('UPDATE artikli SET zaliha=zaliha+?, nabavna_cena=IF(?>0,?,nabavna_cena) WHERE id=? AND lokal_id=?',
                               [$s['kolicina'],$s['cena'],$s['cena'],$s['artikal_id'],$lid]);
                        db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,faktura_id,korisnik_id) VALUES (?,?,"ulaz",?,?,?,?)',
                               [$lid,$s['artikal_id'],$s['kolicina'],'Faktura '.$broj,$fid,$uid]);
                    }
                }
                db_run('UPDATE narudzbenice SET status="primljena" WHERE id=?', [$nid]);
                $pdo->commit();
                flash('success','Roba primljena — kreirana faktura '.$broj.' i zalihe su ažurirane.');
                redirect(urlq('fakture',['view'=>$fid]));
            } catch(Throwable $e){ $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        }
        redirect(urlq('narudzbenice',['view'=>$nid]));
    }

    if ($akcija === 'obrisi') {
        db_run('DELETE FROM narudzbenice WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Narudžbenica je obrisana.');
        redirect(url('narudzbenice'));
    }
}

$dobavljaci = db_all('SELECT id,naziv FROM dobavljaci WHERE lokal_id=? ORDER BY naziv', [$lid]);
$artikli    = db_all('SELECT id,naziv,jedinica_mere,nabavna_cena,zaliha,min_zaliha FROM artikli WHERE lokal_id=? AND aktivan=1 ORDER BY naziv', [$lid]);
$niski      = array_values(array_filter($artikli, fn($a)=> $a['min_zaliha']>0 && $a['zaliha']<=$a['min_zaliha']));

$page_title = 'Narudžbenice';
$active = 'narudzbenice';

// -------- DETALJ --------
$viewId=(int)($_GET['view']??0);
if ($viewId) {
    $n=db_row('SELECT n.*, d.naziv dob FROM narudzbenice n LEFT JOIN dobavljaci d ON d.id=n.dobavljac_id WHERE n.id=? AND n.lokal_id=?', [$viewId,$lid]);
    if(!$n){ flash('error','Narudžbenica ne postoji.'); redirect(url('narudzbenice')); }
    $stavke=db_all('SELECT * FROM narudzbenica_stavke WHERE narudzbenica_id=? ORDER BY id', [$viewId]);
    [$slabel,$scls]=$STAT[$n['status']];
    require __DIR__ . '/../partials/layout_top.php';
    ?>
    <div class="page-head">
      <div><a href="<?= url('narudzbenice') ?>" class="muted" style="font-size:.85rem">← Sve narudžbenice</a>
        <h1>Narudžbenica #<?= $viewId ?></h1>
        <p><?= e($n['dob'] ?: 'Bez dobavljača') ?> · <?= datum($n['datum']) ?> · <span class="badge badge--<?= $scls ?>"><?= $slabel ?></span></p></div>
      <div class="flex gap-2">
        <?php if($n['status']==='nacrt'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="akcija" value="status"><input type="hidden" name="id" value="<?= $viewId ?>"><input type="hidden" name="novi" value="poslata">
            <button class="btn btn--ghost">Označi poslatom</button></form>
        <?php endif; ?>
        <?php if($n['status']!=='primljena'): ?>
          <button class="btn btn--primary" onclick="mPrimi.showModal()"><?= ico('check',16) ?> Primi robu (faktura)</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><div class="card__title">Stavke</div>
        <form method="post" onsubmit="return ukConfirmSubmit(this,'Obrisati narudžbenicu?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $viewId ?>">
          <button class="btn btn--ghost btn--sm" style="color:var(--danger)">Obriši</button></form></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Artikal</th><th class="num">Količina</th><th>JM</th><th class="num">Cena</th><th class="num">Iznos</th></tr></thead>
        <tbody>
        <?php foreach($stavke as $s): ?>
          <tr><td><strong><?= e($s['naziv']) ?></strong></td>
            <td class="num"><?= rtrim(rtrim(number_format((float)$s['kolicina'],3,',','.'),'0'),',') ?></td>
            <td class="muted"><?= e($s['jedinica_mere']) ?></td>
            <td class="num"><?= novac($s['cena']) ?></td><td class="num"><?= novac($s['iznos']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="num"><strong>UKUPNO</strong></td><td class="num"><strong><?= novac($n['iznos']) ?></strong></td></tr></tfoot>
      </table></div>
    </div>

    <dialog id="mPrimi" class="modal">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="akcija" value="primi"><input type="hidden" name="id" value="<?= $viewId ?>">
        <div class="card__head"><div class="card__title">Prijem robe</div><button type="button" class="btn btn--ghost btn--sm" onclick="mPrimi.close()">✕</button></div>
        <div class="card__body">
          <p class="muted">Kreira se faktura, zalihe se povećavaju za naručene količine.</p>
          <div class="field"><label class="label">Broj fakture</label><input class="input" name="broj" value="NAR-<?= $viewId ?>"></div>
        </div>
        <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mPrimi.close()">Otkaži</button><button class="btn btn--primary">Primi i napravi fakturu</button></div>
      </form>
    </dialog>
    <?php
    require __DIR__ . '/../partials/layout_bottom.php';
    return;
}

// -------- LISTA --------
$narudzbenice=db_all('SELECT n.*, d.naziv dob FROM narudzbenice n LEFT JOIN dobavljaci d ON d.id=n.dobavljac_id WHERE n.lokal_id=? ORDER BY n.datum DESC, n.id DESC', [$lid]);
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Narudžbenice</h1><p>Porudžbine dobavljačima. Kad roba stigne → „Primi robu" pravi fakturu i diže zalihe.</p></div>
  <button class="btn btn--primary" onclick="mNar.showModal()">+ Nova narudžbenica</button>
</div>

<?php if ($niski): ?>
<div class="card mb-2" style="border-color:var(--warn)">
  <div class="card__head"><div class="card__title"><?= ico('warn',16) ?> Predlog nabavke — <?= count($niski) ?> artikala ima nisku zalihu</div>
    <button class="btn btn--primary btn--sm" onclick="predlog()">Napravi narudžbenicu od predloga</button></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Artikal</th><th class="num">Zaliha</th><th class="num">Min.</th><th class="num">Predlog</th></tr></thead>
    <tbody>
    <?php foreach($niski as $a): $pred=max(1, round($a['min_zaliha']*2-$a['zaliha'])); ?>
      <tr><td><?= e($a['naziv']) ?></td>
        <td class="num out"><?= rtrim(rtrim(number_format((float)$a['zaliha'],3,',','.'),'0'),',') ?></td>
        <td class="num muted"><?= rtrim(rtrim(number_format((float)$a['min_zaliha'],3,',','.'),'0'),',') ?></td>
        <td class="num in"><?= $pred ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Dobavljač</th><th>Datum</th><th class="num">Iznos</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if(!$narudzbenice): ?>
      <tr><td colspan="6"><div class="empty">Nema narudžbenica.</div></td></tr>
    <?php else: foreach($narudzbenice as $n): [$sl,$sc]=$STAT[$n['status']]; ?>
      <tr>
        <td><a href="<?= urlq('narudzbenice',['view'=>$n['id']]) ?>"><strong>#<?= $n['id'] ?></strong></a></td>
        <td><?= e($n['dob'] ?: '—') ?></td>
        <td class="muted"><?= datum($n['datum']) ?></td>
        <td class="num"><?= novac($n['iznos']) ?></td>
        <td><span class="badge badge--<?= $sc ?>"><?= $sl ?></span></td>
        <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= urlq('narudzbenice',['view'=>$n['id']]) ?>">Detalji →</a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<dialog id="mNar" class="modal modal--wide">
  <form method="post" action="<?= url('narudzbenice') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="nova">
    <div class="card__head"><div class="card__title">Nova narudžbenica</div><button type="button" class="btn btn--ghost btn--sm" onclick="mNar.close()">✕</button></div>
    <div class="card__body">
      <div class="form-row">
        <div class="field"><label class="label">Dobavljač</label>
          <select class="select" name="dobavljac_id"><option value="0">— izaberi —</option>
            <?php foreach($dobavljaci as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="label">Datum</label><input class="input" type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="modal__section-title">Stavke</div>
      <table class="stavke"><thead><tr><th style="width:36%">Artikal</th><th style="width:16%">Količina</th><th style="width:12%">JM</th><th style="width:18%">Cena</th><th style="width:18%">Iznos</th><th></th></tr></thead>
        <tbody id="narBody"></tbody></table>
      <button type="button" class="btn btn--ghost btn--sm" style="margin-top:8px" onclick="narRow()">+ Dodaj stavku</button>
      <div style="text-align:right;margin-top:14px;font-size:1.05rem">Ukupno: <strong id="narUk">0,00 RSD</strong></div>
      <div class="field" style="margin-top:12px"><label class="label">Napomena</label><input class="input" name="napomena"></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mNar.close()">Otkaži</button><button class="btn btn--primary">Sačuvaj</button></div>
  </form>
</dialog>

<script>
const NARART=<?= json_encode($artikli, JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const PREDLOG=<?= json_encode(array_map(fn($a)=>['id'=>$a['id'],'naziv'=>$a['naziv'],'jm'=>$a['jedinica_mere'],'cena'=>$a['nabavna_cena'],'kol'=>max(1,round($a['min_zaliha']*2-$a['zaliha']))], $niski), JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function opts(sel){let o='<option value="0">— slobodan unos —</option>';for(const a of NARART)o+=`<option value="${a.id}" data-jm="${a.jedinica_mere}" data-cena="${a.nabavna_cena}" ${a.id==sel?'selected':''}>${a.naziv.replace(/"/g,'&quot;')}</option>`;return o;}
function narRow(sel,naziv,jm,kol,cena){
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><select class="input" name="s_artikal[]" onchange="pick(this)">${opts(sel||0)}</select>
      <input class="input" name="s_naziv[]" placeholder="Naziv" value="${naziv||''}" style="margin-top:4px"></td>
    <td><input class="input" type="number" step="0.001" name="s_kolicina[]" value="${kol||1}" oninput="narCalc()"></td>
    <td><input class="input" name="s_jm[]" value="${jm||'kom'}" style="width:66px"></td>
    <td><input class="input" type="number" step="0.01" name="s_cena[]" value="${cena||0}" oninput="narCalc()"></td>
    <td class="iznos num" style="text-align:right;padding-right:8px">0,00</td>
    <td><button type="button" class="btn-del" onclick="this.closest('tr').remove();narCalc()">×</button></td>`;
  document.getElementById('narBody').appendChild(tr); narCalc();
}
function pick(sel){const tr=sel.closest('tr');const o=sel.selectedOptions[0];if(sel.value!=='0'){tr.querySelector('[name="s_naziv[]"]').value=o.textContent;tr.querySelector('[name="s_jm[]"]').value=o.dataset.jm||'kom';tr.querySelector('[name="s_cena[]"]').value=o.dataset.cena||0;}narCalc();}
function fmt(n){return n.toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2});}
function narCalc(){let uk=0;document.querySelectorAll('#narBody tr').forEach(tr=>{const k=parseFloat(tr.querySelector('[name="s_kolicina[]"]').value)||0;const c=parseFloat(tr.querySelector('[name="s_cena[]"]').value)||0;tr.querySelector('.iznos').textContent=fmt(k*c);uk+=k*c;});document.getElementById('narUk').textContent=fmt(uk)+' RSD';}
function predlog(){document.getElementById('narBody').innerHTML='';PREDLOG.forEach(p=>narRow(p.id,p.naziv,p.jm,p.kol,p.cena));mNar.showModal();}
narRow();
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
