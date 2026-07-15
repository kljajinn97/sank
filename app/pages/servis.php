<?php
/**
 * WAITER — Servisni ekran POS terminala (skriven: 5× tap na sat).
 * Pristup ISKLJUČIVO super admin nalogom (username + lozinka). Sesija važi 10 min.
 * Podešavanja štampača su PO UREĐAJU i direktno utiču na štampu (papir/kopije/font).
 */
$device = pos_current_device();
if (!$device) { redirect(url('kasa')); }
$lid = (int)$device['lokal_id'];

$ok = !empty($_SESSION['servis_ok']) && (time() - (int)$_SESSION['servis_ok'] < 600);

// ---------- TEST ŠTAMPA (poštuje podešavanja uređaja) ----------
if ($ok && isset($_GET['test'])) {
    $pap = ($device['papir'] ?? '80') === '58' ? 58 : 80;
    $fs  = ($device['font_vel'] ?? 'normal') === 'veliki' ? 15 : 13;
    $kop = max(1, min(3, (int)($device['stampa_kopije'] ?? 1)));
    $lok = db_row('SELECT naziv FROM lokali WHERE id=?', [$lid]);
    ?><!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><title>Test štampa</title>
    <style>*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif}
    body{width:<?= $pap ?>mm;margin:0 auto;padding:10px;color:#000;font-size:<?= $fs ?>px}
    .c{text-align:center}.b{font-weight:800}hr{border:none;border-top:1px dashed #999;margin:8px 0}
    @media print{@page{margin:4mm}}</style></head>
    <body>
      <div class="c b" style="font-size:<?= $fs+3 ?>px">WAITER — TEST ŠTAMPA</div>
      <div class="c"><?= e($lok['naziv'] ?? '') ?> · <?= e($device['naziv']) ?></div>
      <hr>
      <div>Papir: <?= $pap ?>mm · Font: <?= e($device['font_vel'] ?? 'normal') ?> · Kopije: <?= $kop ?></div>
      <div><?= date('d.m.Y. H:i:s') ?></div>
      <hr>
      <div class="c">āčćžšđ 0123456789 — proba karaktera</div>
      <div class="c" style="font-size:11px">Ako se ovo lepo vidi, štampač je spreman.</div>
      <script>var K=<?= $kop ?>;function go(){window.print();}
      window.onafterprint=function(){ if(--K>0) setTimeout(go,300); else setTimeout(function(){window.close()},300); };
      window.onload=go;</script>
    </body></html><?php
    exit;
}

// ---------- AKCIJE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $akcija = $_POST['akcija'] ?? '';

    if ($akcija === 'login') {
        $u = db_row('SELECT * FROM korisnici WHERE username=? AND uloga="super_admin" AND status="aktivan" LIMIT 1', [post('username')]);
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            $_SESSION['servis_ok'] = time();
            audit('servis_pristup','uredjaj',$device['id'],'Servisni ekran otvoren ('.$device['naziv'].')',$lid);
            redirect(url('kasa-servis'));
        }
        flash('error','Pogrešni podaci ili nalog nije super admin.');
        redirect(url('kasa-servis'));
    }

    if (!$ok) redirect(url('kasa-servis'));

    if ($akcija === 'sacuvaj') {
        $pap  = in_array($_POST['papir'] ?? '', ['80','58'], true) ? $_POST['papir'] : '80';
        $kop  = max(1, min(3, (int)($_POST['kopije'] ?? 1)));
        $font = ($_POST['font_vel'] ?? '') === 'veliki' ? 'veliki' : 'normal';
        $as   = $_POST['auto_stampa'] ?? '';
        $asv  = ($as === '' ? null : (int)$as);
        db_run('UPDATE pos_uredjaji SET naziv=?, papir=?, stampa_kopije=?, font_vel=?, auto_stampa=? WHERE id=?',
               [post('naziv') ?: 'POS', $pap, $kop, $font, $asv, $device['id']]);
        flash('success','Podešavanja su sačuvana.');
        redirect(url('kasa-servis'));
    }

    if ($akcija === 'deaktiviraj') {
        audit('servis_deaktivacija','uredjaj',$device['id'],'Uređaj deaktiviran: '.$device['naziv'],$lid);
        db_run('DELETE FROM pos_uredjaji WHERE id=?', [$device['id']]);
        setcookie('sank_pos_token', '', time()-3600, '/');
        unset($_SESSION['pos_uid'], $_SESSION['pos_lokal'], $_SESSION['pos_token'], $_SESSION['servis_ok']);
        redirect(url('kasa'));
    }

    if ($akcija === 'zatvori') { unset($_SESSION['servis_ok']); redirect(url('kasa')); }
    redirect(url('kasa-servis'));
}

$lok = db_row('SELECT naziv,grad,adresa FROM lokali WHERE id=?', [$lid]);
$kod = db_row('SELECT kod, used_at FROM aktivacioni_kodovi WHERE uredjaj_id=? LIMIT 1', [$device['id']]);

$kasa_title = 'Servis';
require __DIR__ . '/../partials/kasa_top.php';
?>
<?php if (!$ok): ?>
  <div class="kasa-center"><div class="kasa-box">
    <div class="sidebar__logo" style="width:56px;height:56px;margin:0 auto 14px"><?= ico('settings',26) ?></div>
    <h2>Servisni ekran</h2>
    <p class="muted" style="margin-bottom:20px">Pristup ima samo <strong>super administrator</strong> sistema.</p>
    <form method="post" action="<?= url('kasa-servis') ?>">
      <?= csrf_field() ?><input type="hidden" name="akcija" value="login">
      <div class="field"><input class="input" name="username" placeholder="Korisničko ime" required autofocus autocomplete="off"></div>
      <div class="field"><input class="input" type="password" name="password" placeholder="Lozinka" required autocomplete="off"></div>
      <button class="btn btn--primary btn--block">Otključaj servis</button>
    </form>
    <a class="btn btn--ghost btn--block" style="margin-top:10px" href="<?= url('kasa') ?>">Nazad na kasu</a>
  </div></div>

<?php else: ?>
  <div class="page-head">
    <div><h1><?= ico('settings',22) ?> Servis uređaja</h1><p>Vidljivo samo super adminu · sesija ističe za 10 min.</p></div>
    <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="akcija" value="zatvori">
      <button class="btn btn--ghost"><?= ico('lock',16) ?> Zatvori servis</button></form>
  </div>

  <div class="grid-2 mb-2">
    <div class="card">
      <div class="card__head"><div class="card__title">Uređaj i lokacija</div>
        <span class="badge badge--<?= $device['status']==='aktivan'?'ok':'danger' ?>"><?= e(ucfirst($device['status'])) ?></span></div>
      <div class="card__body">
        <table class="table" style="font-size:.92rem">
          <tr><td class="muted" style="width:45%">Lokal (lokacija)</td><td><strong><?= e($lok['naziv']) ?></strong><?= $lok['grad'] ? ' · '.e($lok['grad']) : '' ?></td></tr>
          <tr><td class="muted">Aktivacioni ključ</td><td><code><?= e($kod['kod'] ?? '—') ?></code></td></tr>
          <tr><td class="muted">Aktiviran</td><td><?= datum($device['aktiviran_at']) ?></td></tr>
          <tr><td class="muted">Poslednja aktivnost</td><td><?= $device['poslednja_aktivnost'] ? datum($device['poslednja_aktivnost']) : 'nikad' ?></td></tr>
          <tr><td class="muted">Token uređaja</td><td><code><?= e(substr($device['token'],0,10)) ?>…</code></td></tr>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><div class="card__title"><?= ico('print',17) ?> Štampač (ovaj uređaj)</div></div>
      <div class="card__body">
        <form method="post" action="<?= url('kasa-servis') ?>">
          <?= csrf_field() ?><input type="hidden" name="akcija" value="sacuvaj">
          <div class="field"><label class="label">Naziv uređaja</label><input class="input" name="naziv" value="<?= e($device['naziv']) ?>"></div>
          <div class="form-row">
            <div class="field"><label class="label">Širina papira</label>
              <select class="select" name="papir">
                <option value="80" <?= ($device['papir']??'80')==='80'?'selected':'' ?>>80 mm (standard)</option>
                <option value="58" <?= ($device['papir']??'')==='58'?'selected':'' ?>>58 mm (mali)</option>
              </select></div>
            <div class="field"><label class="label">Broj kopija</label>
              <select class="select" name="kopije">
                <?php for($i=1;$i<=3;$i++): ?><option value="<?= $i ?>" <?= (int)($device['stampa_kopije']??1)===$i?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
              </select></div>
          </div>
          <div class="form-row">
            <div class="field"><label class="label">Veličina fonta</label>
              <select class="select" name="font_vel">
                <option value="normal" <?= ($device['font_vel']??'normal')==='normal'?'selected':'' ?>>Normalan</option>
                <option value="veliki" <?= ($device['font_vel']??'')==='veliki'?'selected':'' ?>>Veliki</option>
              </select></div>
            <div class="field"><label class="label">Auto-štampa posle naplate</label>
              <select class="select" name="auto_stampa">
                <option value="" <?= $device['auto_stampa']===null?'selected':'' ?>>Nasledi od lokala</option>
                <option value="1" <?= $device['auto_stampa']!==null&&(int)$device['auto_stampa']===1?'selected':'' ?>>Uključena</option>
                <option value="0" <?= $device['auto_stampa']!==null&&(int)$device['auto_stampa']===0?'selected':'' ?>>Isključena</option>
              </select></div>
          </div>
          <div class="flex gap-2">
            <button class="btn btn--primary">Sačuvaj</button>
            <a class="btn btn--ghost" href="<?= url('kasa-servis') ?>?test=1" target="_blank"><?= ico('print',16) ?> Test štampa</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card" style="border-color:var(--danger)">
    <div class="card__head"><div class="card__title" style="color:var(--danger)">Opasna zona</div></div>
    <div class="card__body">
      <p class="muted" style="margin-top:0">Deaktivacija uklanja uređaj — za ponovni rad biće potreban nov aktivacioni ključ.</p>
      <form method="post" action="<?= url('kasa-servis') ?>" onsubmit="return ukConfirmSubmit(this,'Deaktivirati OVAJ uređaj? Kasa prestaje da radi dok se ne aktivira ponovo.',{danger:true,ok:'Deaktiviraj',busy:'Deaktiviram…'})">
        <?= csrf_field() ?><input type="hidden" name="akcija" value="deaktiviraj">
        <button class="btn btn--danger"><?= ico('trash',16) ?> Deaktiviraj uređaj</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/kasa_bottom.php'; ?>
