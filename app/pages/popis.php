<?php
/** Popis / inventura */
require_role(['vlasnik','menadzer']);
$lid = current_lokal_id();
$uid = current_user()['id'];

function kolf($v){ return rtrim(rtrim(number_format((float)$v,3,',','.'),'0'),',') ?: '0'; }
function urlq(string $path, array $q): string { return url($path) . (($qs=http_build_query($q))?'?'.$qs:''); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'novi') {
        $artikli = db_all('SELECT id,zaliha,nabavna_cena FROM artikli WHERE lokal_id=? AND aktivan=1', [$lid]);
        if (!$artikli) { flash('error','Nema aktivnih artikala za popis.'); redirect(url('popis')); }
        $pdo = db(); $pdo->beginTransaction();
        try {
            db_run('INSERT INTO popis (lokal_id,datum,status,korisnik_id) VALUES (?,CURDATE(),"otvoren",?)', [$lid,$uid]);
            $pid = (int)$pdo->lastInsertId();
            foreach ($artikli as $a)
                db_run('INSERT INTO popis_stavke (popis_id,artikal_id,sistemska,izbrojano,nabavna) VALUES (?,?,?,?,?)',
                       [$pid,$a['id'],$a['zaliha'],$a['zaliha'],$a['nabavna_cena']]);
            $pdo->commit();
            flash('success','Popis je otvoren. Unesi izbrojano stanje.');
            redirect(urlq('popis',['view'=>$pid]));
        } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); redirect(url('popis')); }
    }

    if ($akcija === 'snimi' || $akcija === 'zavrsi') {
        $pid = (int)($_POST['id'] ?? 0);
        $p = db_row('SELECT * FROM popis WHERE id=? AND lokal_id=?', [$pid,$lid]);
        if (!$p || $p['status']==='zavrsen') { flash('error','Popis nije dostupan.'); redirect(url('popis')); }
        $izb = $_POST['izbrojano'] ?? [];
        $pdo = db(); $pdo->beginTransaction();
        try {
            foreach ($izb as $stavkaId => $val) {
                db_run('UPDATE popis_stavke SET izbrojano=? WHERE id=? AND popis_id=?', [to_num($val),(int)$stavkaId,$pid]);
            }
            if ($akcija === 'zavrsi') {
                $stavke = db_all('SELECT * FROM popis_stavke WHERE popis_id=?', [$pid]);
                foreach ($stavke as $s) {
                    if ((float)$s['izbrojano'] != (float)$s['sistemska']) {
                        db_run('UPDATE artikli SET zaliha=? WHERE id=? AND lokal_id=?', [$s['izbrojano'],$s['artikal_id'],$lid]);
                        db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,"korekcija",?,?,?)',
                               [$lid,$s['artikal_id'],$s['izbrojano'],'Popis #'.$pid,$uid]);
                    }
                }
                db_run('UPDATE popis SET status="zavrsen", napomena=? WHERE id=?', [post('napomena') ?: null,$pid]);
                flash('success','Popis je završen i zalihe su usklađene.');
            } else {
                flash('success','Popis je sačuvan.');
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); flash('error','Greška: '.$e->getMessage()); }
        redirect(urlq('popis',['view'=>$pid]));
    }

    if ($akcija === 'obrisi') {
        db_run('DELETE FROM popis WHERE id=? AND lokal_id=?', [(int)$_POST['id'],$lid]);
        flash('success','Popis je obrisan.');
        redirect(url('popis'));
    }
}

$page_title = 'Popis / inventura';
$active = 'popis';

// -------- DETALJ POPISA --------
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId) {
    $p = db_row('SELECT * FROM popis WHERE id=? AND lokal_id=?', [$viewId,$lid]);
    if (!$p) { flash('error','Popis ne postoji.'); redirect(url('popis')); }
    $stavke = db_all('SELECT ps.*, a.naziv, a.jedinica_mere FROM popis_stavke ps JOIN artikli a ON a.id=ps.artikal_id
                      WHERE ps.popis_id=? ORDER BY a.naziv', [$viewId]);
    $manjak=0.0; $visak=0.0;
    foreach ($stavke as $s){ $r=((float)$s['izbrojano']-(float)$s['sistemska'])*(float)$s['nabavna']; if($r<0)$manjak+=$r; else $visak+=$r; }
    $zavrsen = $p['status']==='zavrsen';

    require __DIR__ . '/../partials/layout_top.php';
    ?>
    <div class="page-head">
      <div><a href="<?= url('popis') ?>" class="muted" style="font-size:.85rem">← Svi popisi</a>
        <h1>Popis <?= datum($p['datum']) ?></h1>
        <p><span class="badge badge--<?= $zavrsen?'ok':'warn' ?>"><?= $zavrsen?'Završen':'U toku' ?></span></p></div>
    </div>

    <div class="stats mb-2">
      <div class="stat"><div class="stat__label">Manjak (vrednost)</div><div class="stat__value out"><?= novac(abs($manjak)) ?></div></div>
      <div class="stat"><div class="stat__label">Višak (vrednost)</div><div class="stat__value in"><?= novac($visak) ?></div></div>
      <div class="stat"><div class="stat__label">Razlika ukupno</div><div class="stat__value <?= ($manjak+$visak)>=0?'in':'out' ?>"><?= novac($manjak+$visak) ?></div></div>
    </div>

    <form method="post" action="<?= url('popis') ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $viewId ?>">
      <div class="card">
        <div class="card__head"><div class="card__title">Stavke popisa</div>
          <?php if(!$zavrsen):?><span class="muted" style="font-size:.85rem">Unesi stvarno izbrojano stanje</span><?php endif;?></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Artikal</th><th class="num">Sistemska</th><th class="num">Izbrojano</th><th class="num">Razlika</th><th class="num">Vrednost</th></tr></thead>
          <tbody>
          <?php foreach ($stavke as $s):
            $raz=(float)$s['izbrojano']-(float)$s['sistemska']; $vr=$raz*(float)$s['nabavna'];
          ?>
            <tr>
              <td><strong><?= e($s['naziv']) ?></strong> <span class="muted" style="font-size:.8rem"><?= e($s['jedinica_mere']) ?></span></td>
              <td class="num muted"><?= kolf($s['sistemska']) ?></td>
              <td class="num" style="width:130px">
                <?php if($zavrsen): ?><?= kolf($s['izbrojano']) ?>
                <?php else: ?><input class="input" type="number" step="0.001" name="izbrojano[<?= $s['id'] ?>]" value="<?= rtrim(rtrim(number_format((float)$s['izbrojano'],3,'.',''),'0'),'.') ?>" style="text-align:right;padding:6px 8px"><?php endif; ?>
              </td>
              <td class="num <?= $raz<0?'out':($raz>0?'in':'muted') ?>"><?= ($raz>0?'+':'').kolf($raz) ?></td>
              <td class="num <?= $vr<0?'out':'muted' ?>"><?= novac($vr) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
      <?php if (!$zavrsen): ?>
      <div class="toolbar" style="margin-top:16px;justify-content:flex-end">
        <button class="btn btn--ghost" name="akcija" value="snimi">Sačuvaj (nastavi kasnije)</button>
        <button class="btn btn--primary" name="akcija" value="zavrsi" onclick="return confirm('Završiti popis? Zalihe će biti usklađene sa izbrojanim stanjem.')">Završi popis i uskladi zalihe</button>
      </div>
      <?php endif; ?>
    </form>
    <?php
    require __DIR__ . '/../partials/layout_bottom.php';
    return;
}

// -------- LISTA POPISA --------
$popisi = db_all(
   'SELECT p.*, (SELECT COUNT(*) FROM popis_stavke ps WHERE ps.popis_id=p.id) br,
     (SELECT COALESCE(SUM((ps.izbrojano-ps.sistemska)*ps.nabavna),0) FROM popis_stavke ps WHERE ps.popis_id=p.id) razlika
    FROM popis p WHERE p.lokal_id=? ORDER BY p.datum DESC, p.id DESC', [$lid]);

require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Popis / inventura</h1><p>Brojanje stanja i usklađivanje zaliha (manjak/višak).</p></div>
  <form method="post" action="<?= url('popis') ?>"><?= csrf_field() ?><input type="hidden" name="akcija" value="novi">
    <button class="btn btn--primary">+ Novi popis</button></form>
</div>

<div class="card"><div class="table-wrap">
  <table class="table">
    <thead><tr><th>Datum</th><th>Status</th><th class="num">Artikala</th><th class="num">Razlika</th><th></th></tr></thead>
    <tbody>
    <?php if (!$popisi): ?>
      <tr><td colspan="5"><div class="empty">Još nema popisa. Klikni „Novi popis" da snimiš trenutno stanje.</div></td></tr>
    <?php else: foreach ($popisi as $p): ?>
      <tr>
        <td><a href="<?= urlq('popis',['view'=>$p['id']]) ?>"><strong><?= datum($p['datum']) ?></strong></a></td>
        <td><span class="badge badge--<?= $p['status']==='zavrsen'?'ok':'warn' ?>"><?= $p['status']==='zavrsen'?'Završen':'U toku' ?></span></td>
        <td class="num"><?= (int)$p['br'] ?></td>
        <td class="num <?= $p['razlika']<0?'out':($p['razlika']>0?'in':'muted') ?>"><?= novac($p['razlika']) ?></td>
        <td class="text-right" style="white-space:nowrap">
          <a class="btn btn--ghost btn--sm" href="<?= urlq('popis',['view'=>$p['id']]) ?>"><?= $p['status']==='zavrsen'?'Pregled':'Nastavi' ?></a>
          <form method="post" style="display:inline" onsubmit="return confirm('Obrisati popis?')"><?= csrf_field() ?><input type="hidden" name="akcija" value="obrisi"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn--ghost btn--sm" style="color:var(--danger)">×</button></form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div></div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
