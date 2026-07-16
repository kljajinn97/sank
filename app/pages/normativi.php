<?php
/** Normativi (recepture) i kalkulacije cena */
require_role(['vlasnik','menadzer']);
require_modul('normativi');
$lid = current_lokal_id();
$uid = current_user()['id'];
$pdv = (float)(db_val('SELECT pdv_stopa FROM lokali WHERE id=?', [$lid]) ?: 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'sacuvaj') {
        $artId = (int)($_POST['artikal_id'] ?? 0);
        $napomena = post('napomena') ?: null;
        $sas = $_POST['sastojak_id'] ?? [];
        $kol = $_POST['kolicina'] ?? [];
        $a = db_row('SELECT id FROM artikli WHERE id=? AND lokal_id=?', [$artId,$lid]);
        if (!$a) { flash('error','Izaberi proizvod.'); redirect(url('normativi')); }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Jedan normativ po artiklu — nađi ili napravi
            $nid = (int)(db_val('SELECT id FROM normativi WHERE lokal_id=? AND artikal_id=?', [$lid,$artId]) ?: 0);
            if ($nid) {
                db_run('UPDATE normativi SET napomena=? WHERE id=?', [$napomena,$nid]);
                db_run('DELETE FROM normativ_stavke WHERE normativ_id=?', [$nid]);
            } else {
                db_run('INSERT INTO normativi (lokal_id,artikal_id,napomena) VALUES (?,?,?)', [$lid,$artId,$napomena]);
                $nid = (int)$pdo->lastInsertId();
            }
            foreach ($sas as $i => $sid) {
                $sid = (int)$sid; $k = to_num($kol[$i] ?? 0);
                if ($sid > 0 && $k > 0) db_run('INSERT INTO normativ_stavke (normativ_id,sastojak_id,kolicina) VALUES (?,?,?)', [$nid,$sid,$k]);
            }
            $pdo->commit();
            flash('success','Normativ je sačuvan.');
        } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        redirect(url('normativi'));
    }

    if ($akcija === 'obrisi') {
        db_run('DELETE FROM normativi WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Normativ je obrisan.');
        redirect(url('normativi'));
    }

    if ($akcija === 'razduzi') {
        $nid = (int)($_POST['id'] ?? 0);
        $broj = to_num($_POST['broj'] ?? 0);
        $n = db_row('SELECT n.*, a.naziv FROM normativi n JOIN artikli a ON a.id=n.artikal_id WHERE n.id=? AND n.lokal_id=?', [$nid,$lid]);
        if ($n && $broj > 0) {
            $stavke = db_all('SELECT * FROM normativ_stavke WHERE normativ_id=?', [$nid]);
            $pdo = db(); $pdo->beginTransaction();
            try {
                foreach ($stavke as $s) {
                    $ukup = $broj * (float)$s['kolicina'];
                    db_run('UPDATE artikli SET zaliha = zaliha - ? WHERE id=? AND lokal_id=?', [$ukup,$s['sastojak_id'],$lid]);
                    db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"izlaz",?,?,?)',
                           [$lid,$s['sastojak_id'],$ukup,'Utrošak: '.$n['naziv'].' ×'.rtrim(rtrim(number_format($broj,3,'.',''),'0'),'.'),$uid]);
                }
                $pdo->commit();
                flash('success','Zalihe su razdužene po recepturi ('.$n['naziv'].' ×'.$broj.').');
            } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        }
        redirect(url('normativi'));
    }
}

$artikli = db_all('SELECT id,naziv,jedinica_mere,nabavna_cena,prodajna_cena FROM artikli WHERE lokal_id=? AND aktivan=1 ORDER BY naziv', [$lid]);
$normativi = db_all(
   'SELECT n.*, a.naziv, a.jedinica_mere, a.prodajna_cena,
     (SELECT COALESCE(SUM(ns.kolicina*sa.nabavna_cena),0) FROM normativ_stavke ns JOIN artikli sa ON sa.id=ns.sastojak_id WHERE ns.normativ_id=n.id) AS kostanje,
     (SELECT COUNT(*) FROM normativ_stavke ns WHERE ns.normativ_id=n.id) AS br
    FROM normativi n JOIN artikli a ON a.id=n.artikal_id WHERE n.lokal_id=? ORDER BY a.naziv', [$lid]);

// Stavke po normativu (za modal)
$stavkeMap = [];
foreach (db_all('SELECT ns.normativ_id, ns.sastojak_id, ns.kolicina FROM normativ_stavke ns JOIN normativi n ON n.id=ns.normativ_id WHERE n.lokal_id=?', [$lid]) as $r)
    $stavkeMap[$r['normativ_id']][] = ['sastojak_id'=>(int)$r['sastojak_id'],'kolicina'=>(float)$r['kolicina']];

$page_title = 'Normativi i kalkulacije';
$active = 'normativi';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Normativi i kalkulacije</h1><p>Recepture proizvoda — cena koštanja, marža i automatsko razduženje zaliha.</p></div>
  <button class="btn btn--primary" onclick="openNorm()">+ Novi normativ</button>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Proizvod</th><th class="num">Sastojaka</th><th class="num">Koštanje</th><th class="num">Prodajna</th><th class="num">Marža</th><th class="num">Sa PDV (<?= (int)$pdv ?>%)</th><th></th></tr></thead>
    <tbody>
    <?php if (!$normativi): ?>
      <tr><td colspan="7"><div class="empty">Nema normativa. Napravi recepturu za koktel, kafu ili jelo.</div></td></tr>
    <?php else: foreach ($normativi as $n):
      $kost=(float)$n['kostanje']; $pro=(float)$n['prodajna_cena'];
      $marza = $kost>0 ? round(($pro-$kost)/$kost*100) : null;
      $saPdv = $pro*(1+$pdv/100);
    ?>
      <tr>
        <td><strong><?= e($n['naziv']) ?></strong><?php if($n['napomena']):?><div class="muted" style="font-size:.8rem"><?= e($n['napomena']) ?></div><?php endif;?></td>
        <td class="num"><?= (int)$n['br'] ?></td>
        <td class="num"><?= novac($kost) ?></td>
        <td class="num"><?= novac($pro) ?></td>
        <td class="num <?= ($marza!==null&&$marza<0)?'out':'in' ?>"><?= $marza!==null?$marza.'%':'—' ?></td>
        <td class="num muted"><?= novac($saPdv) ?></td>
        <td class="text-right" style="white-space:nowrap">
          <button class="btn btn--ghost btn--sm" onclick='razduzi(<?= (int)$n['id'] ?>,<?= json_encode($n['naziv']) ?>)'>Razduži</button>
          <button class="btn btn--ghost btn--sm" onclick='openNorm(<?= json_encode([
            "id"=>$n["id"],"artikal_id"=>$n["artikal_id"],"napomena"=>$n["napomena"],
            "stavke"=>$stavkeMap[$n["id"]] ?? []
          ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Izmeni</button>
          <form method="post" style="display:inline" onsubmit="return ukConfirmSubmit(this,'Obrisati normativ?',{danger:true,ok:'Obriši'})"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $n['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<!-- Modal: normativ -->
<dialog id="mNorm" class="modal modal--wide">
  <form method="post" action="<?= url('normativi') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj">
    <div class="card__head"><div class="card__title" id="n_title">Novi normativ</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mNorm.close()">✕</button></div>
    <div class="card__body">
      <div class="field"><label class="label">Proizvod (gotov artikal) *</label>
        <select class="select" name="artikal_id" id="n_art" required onchange="calcCost()">
          <option value="">— izaberi proizvod —</option>
          <?php foreach ($artikli as $a): ?><option value="<?= $a['id'] ?>" data-pro="<?= $a['prodajna_cena'] ?>"><?= e($a['naziv']) ?></option><?php endforeach; ?>
        </select>
        <div class="help">Npr. „Espresso", „Mojito", „Pljeskavica" — proizvod koji prodaješ.</div></div>

      <div class="modal__section-title">Sastojci (sirovine iz zaliha)</div>
      <table class="stavke"><thead><tr><th style="width:55%">Sirovina</th><th style="width:30%">Količina</th><th></th></tr></thead>
        <tbody id="n_body"></tbody></table>
      <button type="button" class="btn btn--ghost btn--sm" style="margin-top:8px" onclick="dodajSas()">+ Dodaj sastojak</button>

      <div style="text-align:right;margin-top:16px">
        Cena koštanja: <strong id="n_kost">0,00 RSD</strong> ·
        Prodajna: <strong id="n_pro">0,00 RSD</strong> ·
        Marža: <strong id="n_marza">—</strong>
      </div>
      <div class="field" style="margin-top:12px"><label class="label">Napomena</label><input class="input" name="napomena" id="n_napomena"></div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mNorm.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>

<!-- Modal: razduženje -->
<dialog id="mRazd" class="modal">
  <form method="post" action="<?= url('normativi') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="razduzi"><input type="hidden" name="id" id="r_id">
    <div class="card__head"><div class="card__title">Razduži zalihe po recepturi</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mRazd.close()">✕</button></div>
    <div class="card__body">
      <p class="muted">Proizvod: <strong id="r_naziv"></strong></p>
      <div class="field"><label class="label">Prodata / proizvedena količina</label>
        <input class="input" type="number" step="1" name="broj" min="1" value="1" required>
        <div class="help">Sistem će skinuti sastojke sa zaliha prema recepturi.</div></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn btn--ghost" onclick="mRazd.close()">Otkaži</button>
      <button class="btn btn--primary">Razduži</button></div>
  </form>
</dialog>

<script>
const NART = <?= json_encode($artikli, JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
function sasOptions(sel){
  let o='<option value="0">— sirovina —</option>';
  for(const a of NART) o+=`<option value="${a.id}" data-nab="${a.nabavna_cena}" ${a.id==sel?'selected':''}>${a.naziv.replace(/"/g,'&quot;')} (${a.jedinica_mere})</option>`;
  return o;
}
function sasRow(sid,kol){
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><select class="input" name="sastojak_id[]" onchange="calcCost()">${sasOptions(sid||0)}</select></td>
    <td><input class="input" type="number" step="0.001" name="kolicina[]" value="${kol||1}" oninput="calcCost()"></td>
    <td><button type="button" class="btn-del" onclick="this.closest('tr').remove();calcCost()">×</button></td>`;
  return tr;
}
function dodajSas(sid,kol){ document.getElementById('n_body').appendChild(sasRow(sid,kol)); }
function fmt(n){ return n.toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2})+' RSD'; }
function calcCost(){
  let kost=0;
  document.querySelectorAll('#n_body tr').forEach(tr=>{
    const sel=tr.querySelector('[name="sastojak_id[]"]'); const opt=sel.selectedOptions[0];
    const nab=parseFloat(opt?.dataset.nab||0); const k=parseFloat(tr.querySelector('[name="kolicina[]"]').value)||0;
    kost+=nab*k;
  });
  const artOpt=document.getElementById('n_art').selectedOptions[0];
  const pro=parseFloat(artOpt?.dataset.pro||0);
  document.getElementById('n_kost').textContent=fmt(kost);
  document.getElementById('n_pro').textContent=fmt(pro);
  document.getElementById('n_marza').textContent = kost>0 ? Math.round((pro-kost)/kost*100)+'%' : '—';
}
function openNorm(n){
  n=n||{};
  document.getElementById('n_title').textContent=n.id?'Izmena normativa':'Novi normativ';
  document.getElementById('n_art').value=n.artikal_id||'';
  document.getElementById('n_napomena').value=n.napomena||'';
  const body=document.getElementById('n_body'); body.innerHTML='';
  if(n.stavke && n.stavke.length) n.stavke.forEach(s=>dodajSas(s.sastojak_id,s.kolicina)); else dodajSas();
  calcCost();
  document.getElementById('mNorm').showModal();
}
function razduzi(id,naziv){
  document.getElementById('r_id').value=id;
  document.getElementById('r_naziv').textContent=naziv;
  document.getElementById('mRazd').showModal();
}
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
