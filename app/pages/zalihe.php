<?php
/** Zalihe — pregled stanja i korekcije */
require_role(['vlasnik','menadzer','konobar']);
$lid = current_lokal_id();
$uid = current_user()['id'];
$mozeMenjati = user_has_role(['vlasnik','menadzer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mozeMenjati) {
    csrf_check();
    if (($_POST['akcija'] ?? '') === 'korekcija') {
        $artId = (int)($_POST['artikal_id'] ?? 0);
        $tip = in_array($_POST['tip'] ?? '', ['ulaz','izlaz','korekcija'], true) ? $_POST['tip'] : 'izlaz';
        $kol = to_num($_POST['kolicina'] ?? 0);
        $razlog = post('razlog') ?: ucfirst($tip);
        $a = db_row('SELECT id,zaliha FROM artikli WHERE id=? AND lokal_id=?', [$artId,$lid]);
        if ($a && $kol > 0) {
            // Ulaz +, izlaz -, korekcija = postavi na tačnu vrednost
            if ($tip === 'ulaz')   $novo = (float)$a['zaliha'] + $kol;
            elseif ($tip === 'izlaz') $novo = (float)$a['zaliha'] - $kol;
            else                   $novo = $kol; // korekcija = novo stanje
            db_run('UPDATE artikli SET zaliha=? WHERE id=? AND lokal_id=?', [$novo,$artId,$lid]);
            db_run('INSERT INTO zalihe_promet (lokal_id,artikal_id,tip,kolicina,razlog,korisnik_id) VALUES (?,?,?,?,?,?)',
                   [$lid,$artId,$tip,$kol,$razlog,$uid]);
            flash('success','Zaliha je ažurirana.');
        } else {
            flash('error','Neispravan artikal ili količina.');
        }
        redirect(url('zalihe'));
    }
}

$artikli = db_all('SELECT a.*, k.naziv AS kat FROM artikli a LEFT JOIN kategorije k ON k.id=a.kategorija_id
                   WHERE a.lokal_id=? AND a.aktivan=1 ORDER BY a.naziv', [$lid]);

$vrednost = 0.0; $niski = 0;
foreach ($artikli as $a) {
    $vrednost += (float)$a['zaliha'] * (float)$a['nabavna_cena'];
    if ($a['min_zaliha'] > 0 && $a['zaliha'] <= $a['min_zaliha']) $niski++;
}

$promet = db_all('SELECT z.*, a.naziv AS art, k.ime FROM zalihe_promet z
                  LEFT JOIN artikli a ON a.id=z.artikal_id LEFT JOIN korisnici k ON k.id=z.korisnik_id
                  WHERE z.lokal_id=? ORDER BY z.datum DESC, z.id DESC LIMIT 15', [$lid]);

function kol($v){ return rtrim(rtrim(number_format((float)$v,3,',','.'),'0'),',') ?: '0'; }

$page_title = 'Zalihe';
$active = 'zalihe';
require __DIR__ . '/../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Zalihe</h1><p>Trenutno stanje robe u magacinu. Fakture automatski povećavaju zalihu.</p></div>
  <?php if ($mozeMenjati): ?><button class="btn btn--primary" onclick="mKor.showModal()">Korekcija / otpis</button><?php endif; ?>
</div>

<div class="stats mb-2">
  <div class="stat"><div class="stat__label">Vrednost zaliha (nabavna)</div><div class="stat__value"><?= novac($vrednost) ?></div></div>
  <div class="stat"><div class="stat__label">Artikala na stanju</div><div class="stat__value"><?= count($artikli) ?></div></div>
  <div class="stat"><div class="stat__label">Niska zaliha</div><div class="stat__value <?= $niski?'out':'' ?>"><?= $niski ?></div>
    <div class="stat__delta"><?= $niski?'Treba nabaviti':'Sve u redu' ?></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div class="card__title">Stanje po artiklima</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Artikal</th><th class="num">Zaliha</th><th class="num">Vrednost</th></tr></thead>
      <tbody>
      <?php if (!$artikli): ?>
        <tr><td colspan="3"><div class="empty">Nema aktivnih artikala.</div></td></tr>
      <?php else: foreach ($artikli as $a):
        $nisko = $a['min_zaliha']>0 && $a['zaliha']<=$a['min_zaliha'];
      ?>
        <tr>
          <td><strong><?= e($a['naziv']) ?></strong> <span class="muted" style="font-size:.8rem"><?= e($a['jedinica_mere']) ?></span></td>
          <td class="num"><?= kol($a['zaliha']) ?> <?php if($nisko):?><span class="badge badge--warn">nisko</span><?php endif;?></td>
          <td class="num muted"><?= novac((float)$a['zaliha']*(float)$a['nabavna_cena']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <div class="card__head"><div class="card__title">Poslednje kretanje</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Artikal</th><th>Tip</th><th class="num">Kol.</th><th>Razlog</th></tr></thead>
      <tbody>
      <?php if (!$promet): ?>
        <tr><td colspan="4"><div class="empty">Još nema kretanja zaliha.</div></td></tr>
      <?php else: foreach ($promet as $z):
        $bcls = $z['tip']==='ulaz'?'ok':($z['tip']==='izlaz'?'danger':'info');
      ?>
        <tr>
          <td><?= e($z['art'] ?: '—') ?><div class="muted" style="font-size:.75rem"><?= datum($z['datum']) ?></div></td>
          <td><span class="badge badge--<?= $bcls ?>"><?= e(ucfirst($z['tip'])) ?></span></td>
          <td class="num"><?= kol($z['kolicina']) ?></td>
          <td class="muted" style="font-size:.85rem"><?= e($z['razlog'] ?: '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php if ($mozeMenjati): ?>
<dialog id="mKor" class="modal">
  <form method="post" action="<?= url('zalihe') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="korekcija">
    <div class="card__head"><div class="card__title">Korekcija zalihe</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mKor.close()">✕</button></div>
    <div class="card__body">
      <div class="field"><label class="label">Artikal *</label>
        <select class="select" name="artikal_id" required>
          <option value="">— izaberi artikal —</option>
          <?php foreach ($artikli as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['naziv']) ?> (stanje: <?= kol($a['zaliha']) ?>)</option><?php endforeach; ?>
        </select></div>
      <div class="form-row">
        <div class="field"><label class="label">Tip</label>
          <select class="select" name="tip">
            <option value="izlaz">Izlaz / otpis (−)</option>
            <option value="ulaz">Ulaz (+)</option>
            <option value="korekcija">Popis — postavi tačno stanje</option>
          </select></div>
        <div class="field"><label class="label">Količina</label><input class="input" type="number" step="0.001" name="kolicina" required></div>
      </div>
      <div class="field"><label class="label">Razlog</label><input class="input" name="razlog" placeholder="npr. Otpis, kalo, popis…"></div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mKor.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>
