<?php
/** Super admin — upravljanje lokalima */
require_role(['super_admin']);

// --- Kreiranje novog lokala + vlasničkog naloga ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'novi_lokal') {
    csrf_check();
    $naziv = trim($_POST['naziv'] ?? '');
    $grad  = trim($_POST['grad'] ?? '');
    $tip   = trim($_POST['tip'] ?? '');
    $pretplata = trim($_POST['pretplata_do'] ?? '') ?: null;

    $v_ime = trim($_POST['v_ime'] ?? '');
    $v_prezime = trim($_POST['v_prezime'] ?? '');
    $v_email = trim($_POST['v_email'] ?? '');
    $v_username = trim($_POST['v_username'] ?? '');
    $v_pass = (string)($_POST['v_password'] ?? '');

    $err = null;
    if ($naziv==='' || $v_ime==='' || $v_email==='' || $v_username==='' || strlen($v_pass)<6) {
        $err = 'Popuni obavezna polja (naziv lokala + podaci vlasnika, lozinka min. 6 karaktera).';
    }
    // Provera zauzetog username/email
    if (!$err) {
        $c = db()->prepare('SELECT COUNT(*) FROM korisnici WHERE username=? OR email=?');
        $c->execute([$v_username, $v_email]);
        if ($c->fetchColumn() > 0) $err = 'Korisničko ime ili email već postoji.';
    }

    if ($err) {
        flash('error', $err);
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO lokali (naziv,tip,grad,pretplata_do) VALUES (?,?,?,?)')
                ->execute([$naziv,$tip ?: null,$grad ?: null,$pretplata]);
            $lokalId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO korisnici (lokal_id,ime,prezime,email,username,password_hash,uloga,status)
                           VALUES (?,?,?,?,?,?,"vlasnik","aktivan")')
                ->execute([$lokalId,$v_ime,$v_prezime,$v_email,$v_username,password_hash($v_pass,PASSWORD_DEFAULT)]);
            $pdo->commit();
            flash('success', 'Lokal „'.$naziv.'“ i vlasnički nalog su kreirani.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Greška: '.$e->getMessage());
        }
    }
    redirect(url('admin/lokali'));
}

// --- Moduli lokala (paketi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'moduli') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $svi = array_keys(moduli_def());
    $izabrani = array_values(array_intersect((array)($_POST['moduli'] ?? []), $svi));
    // svi štiklirani = NULL (podrazumevano sve)
    $val = count($izabrani) === count($svi) ? null : json_encode($izabrani);
    db()->prepare('UPDATE lokali SET moduli=? WHERE id=?')->execute([$val,$id]);
    flash('success','Moduli lokala su sačuvani.');
    redirect(url('admin/lokali'));
}

// --- Suspenduj / aktiviraj ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'status') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $novi = ($_POST['novi'] ?? '') === 'suspendovan' ? 'suspendovan' : 'aktivan';
    db()->prepare('UPDATE lokali SET status=? WHERE id=?')->execute([$novi,$id]);
    flash('success', 'Status lokala je promenjen.');
    redirect(url('admin/lokali'));
}

$lokali = db()->query(
    'SELECT l.*, (SELECT COUNT(*) FROM korisnici k WHERE k.lokal_id=l.id) AS br_korisnika,
            (SELECT CONCAT(k.ime," ",COALESCE(k.prezime,"")) FROM korisnici k WHERE k.lokal_id=l.id AND k.uloga="vlasnik" LIMIT 1) AS vlasnik
     FROM lokali l ORDER BY l.created_at DESC'
)->fetchAll();

$page_title = 'Lokali';
$active = 'lokali';
require __DIR__ . '/../../partials/layout_top.php';
?>
<div class="page-head">
  <div><h1>Lokali</h1><p>Upravljaj svim ugostiteljskim objektima i njihovim pretplatama.</p></div>
  <button class="btn btn--primary" onclick="document.getElementById('modalNovi').showModal()">+ Novi lokal</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Lokal</th><th>Vlasnik</th><th>Grad</th><th>Korisnika</th><th>Status</th><th>Pretplata do</th><th></th></tr></thead>
      <tbody>
      <?php if (!$lokali): ?>
        <tr><td colspan="7"><div class="empty">Još nema lokala. Klikni „Novi lokal“ da kreiraš prvi.</div></td></tr>
      <?php else: foreach ($lokali as $l): ?>
        <tr>
          <td><strong><?= e($l['naziv']) ?></strong><?php if($l['tip']):?><div class="muted" style="font-size:.8rem"><?= e($l['tip']) ?></div><?php endif;?></td>
          <td><?= e($l['vlasnik'] ?: '—') ?></td>
          <td><?= e($l['grad'] ?: '—') ?></td>
          <td><span class="badge badge--muted"><?= (int)$l['br_korisnika'] ?></span></td>
          <td><span class="badge badge--<?= $l['status']==='aktivan'?'ok':'danger' ?>"><?= e(ucfirst($l['status'])) ?></span></td>
          <td>
            <?php
              $p = $l['pretplata_do'];
              if (!$p) { echo '<span class="muted">—</span>'; }
              else {
                $isteklo = strtotime($p) < strtotime('today');
                $uskoro = strtotime($p) <= strtotime('+7 days');
                echo '<span class="badge badge--'.($isteklo?'danger':($uskoro?'warn':'muted')).'">'.datum($p).'</span>';
              }
            ?>
          </td>
          <td class="text-right" style="white-space:nowrap">
            <?php
              $svi = array_keys(moduli_def());
              $mArr = json_decode((string)$l['moduli'], true);
              $ukljuceni = is_array($mArr) ? array_values(array_intersect($mArr,$svi)) : $svi;
            ?>
            <button class="btn btn--ghost btn--sm" onclick='openModuli(<?= (int)$l['id'] ?>, <?= json_encode($l['naziv'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>, <?= json_encode($ukljuceni) ?>)'><?= ico('settings',14) ?> Moduli (<?= count($ukljuceni) ?>/<?= count($svi) ?>)</button>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="akcija" value="status">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <input type="hidden" name="novi" value="<?= $l['status']==='aktivan'?'suspendovan':'aktivan' ?>">
              <button class="btn btn--ghost btn--sm"><?= $l['status']==='aktivan'?'Suspenduj':'Aktiviraj' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: moduli lokala -->
<dialog id="mModuli" class="modal">
  <form method="post" action="<?= url('admin/lokali') ?>">
    <?= csrf_field() ?><input type="hidden" name="akcija" value="moduli"><input type="hidden" name="id" id="mod_id">
    <div class="card__head"><div class="card__title">Moduli — <span id="mod_naziv"></span></div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="mModuli.close()">✕</button></div>
    <div class="card__body">
      <p class="muted" style="margin-top:0">Uključi module koje ovaj lokal koristi (tvoji paketi). Jezgro sistema je uvek dostupno.</p>
      <div id="mod_list">
        <?php foreach (moduli_def() as $mk => $mn): ?>
          <label class="flex items-center gap-2" style="padding:9px 10px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;background:var(--surface-2)">
            <input type="checkbox" name="moduli[]" value="<?= e($mk) ?>" data-mod="<?= e($mk) ?>">
            <span style="font-weight:600"><?= e($mn) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" onclick="mModuli.close()">Otkaži</button>
      <button class="btn btn--primary">Sačuvaj module</button>
    </div>
  </form>
</dialog>
<script>
function openModuli(id, naziv, ukljuceni){
  document.getElementById('mod_id').value = id;
  document.getElementById('mod_naziv').textContent = naziv;
  document.querySelectorAll('#mod_list input[data-mod]').forEach(function(cb){ cb.checked = ukljuceni.indexOf(cb.dataset.mod) !== -1; });
  document.getElementById('mModuli').showModal();
}
</script>

<!-- Modal: novi lokal -->
<dialog id="modalNovi" style="border:none;border-radius:var(--radius-lg);padding:0;max-width:620px;width:92%;box-shadow:var(--shadow-lg);">
  <form method="post" action="<?= url('admin/lokali') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="akcija" value="novi_lokal">
    <div class="card__head"><div class="card__title">Novi lokal</div>
      <button type="button" class="btn btn--ghost btn--sm" onclick="this.closest('dialog').close()">✕</button></div>
    <div class="card__body">
      <h4 style="margin-top:0;color:var(--brand-700)">Podaci o lokalu</h4>
      <div class="form-row">
        <div class="field"><label class="label">Naziv lokala *</label><input class="input" name="naziv" required placeholder="npr. Kafe Central"></div>
        <div class="field"><label class="label">Tip</label><input class="input" name="tip" placeholder="kafić, restoran, bar…"></div>
      </div>
      <div class="form-row">
        <div class="field"><label class="label">Grad</label><input class="input" name="grad" placeholder="npr. Novi Sad"></div>
        <div class="field"><label class="label">Pretplata plaćena do</label><input class="input" type="date" name="pretplata_do"></div>
      </div>

      <h4 style="color:var(--brand-700)">Vlasnički nalog</h4>
      <p class="help" style="margin-top:-6px;margin-bottom:14px;">Sa ovim podacima se vlasnik prijavljuje u sistem.</p>
      <div class="form-row">
        <div class="field"><label class="label">Ime *</label><input class="input" name="v_ime" required></div>
        <div class="field"><label class="label">Prezime</label><input class="input" name="v_prezime"></div>
      </div>
      <div class="field"><label class="label">Email *</label><input class="input" type="email" name="v_email" required></div>
      <div class="form-row">
        <div class="field"><label class="label">Korisničko ime *</label><input class="input" name="v_username" required></div>
        <div class="field"><label class="label">Lozinka *</label><input class="input" type="password" name="v_password" required placeholder="min. 6 karaktera"></div>
      </div>
    </div>
    <div class="card__head" style="border-top:1px solid var(--border);border-bottom:none;justify-content:flex-end;gap:10px;">
      <button type="button" class="btn btn--ghost" onclick="this.closest('dialog').close()">Otkaži</button>
      <button class="btn btn--primary">Kreiraj lokal</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/../../partials/layout_bottom.php'; ?>
